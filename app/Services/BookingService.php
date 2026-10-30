<?php
namespace App\Services;

use App\Models\Appointment;
use App\Models\AppointmentSlot;
use App\Models\Barber;
use App\Models\Client;
use App\Models\Service;
use App\Models\TimeOff;
use App\Models\WorkSchedule;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class BookingConflict extends RuntimeException {}

class BookingService
{
    private function timezone(): string { return config('app.timezone', 'America/Lima'); }

    private function periods(CarbonImmutable $start, int $duration): array
    {
        $slots = [];
        for ($minute = 0; $minute < $duration; $minute += config('booking.slot_minutes', 15)) {
            $slots[] = $start->addMinutes($minute)->format('H:i:s');
        }
        return $slots;
    }

    private function timestamp(string $day, string $time): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m-d H:i', "$day $time", $this->timezone());
    }

    private function validTime(Barber $barber, Service $service, CarbonImmutable $start, array $occupied): bool
    {
        $now = CarbonImmutable::now($this->timezone());
        $selectableDays = max(1, (int) ($barber->booking_days_ahead ?: config('booking.days_ahead', 3)));
        if (! $barber->active || ! $service->active || $start->lt($now->addMinutes(config('booking.notice_minutes')))
            || $start->startOfDay()->gt($now->startOfDay()->addDays($selectableDays - 1))) {
            return false;
        }
        if ((int) $start->format('i') % config('booking.interval_minutes') !== 0 || (int) $start->format('s') !== 0) {
            return false;
        }
        if (TimeOff::where('barber_id', $barber->id)
            ->whereDate('day', '<=', $start->toDateString())
            ->where(function ($query) use ($start) {
                $query->whereDate('ends_on', '>=', $start->toDateString())
                    ->orWhere(fn ($single) => $single->whereNull('ends_on')->whereDate('day', $start->toDateString()));
            })
            ->exists()) return false;
        $schedules = WorkSchedule::where('barber_id', $barber->id)->where('weekday', $start->dayOfWeek)
            ->where('enabled', true)->orderBy('sort_order')->get();
        $fitsBlock = $schedules->contains(function (WorkSchedule $schedule) use ($start, $service) {
            $opens = $this->timestamp($start->toDateString(), substr($schedule->opens_at, 0, 5));
            $closes = $this->timestamp($start->toDateString(), substr($schedule->closes_at, 0, 5));
            return ! $start->lt($opens) && ! $start->addMinutes($service->duration_minutes)->gt($closes);
        });
        if (! $fitsBlock) return false;
        foreach ($this->periods($start, $service->duration_minutes) as $period) {
            if (isset($occupied[$period])) return false;
        }
        return true;
    }

    /** Disponibilidad calculada en servidor. Solo es indicativa: POST siempre revalida dentro de la transacción. */
    public function availability(int $barberId, int $serviceId, string $day): array
    {
        $barber = Barber::findOrFail($barberId);
        $service = Service::findOrFail($serviceId);
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $day, $this->timezone());
        if (! $barber->active || ! $service->active) return [];
        $schedules = WorkSchedule::where('barber_id', $barberId)->where('weekday', $date->dayOfWeek)
            ->where('enabled', true)->orderBy('sort_order')->get();
        if ($schedules->isEmpty()) return [];
        $busy = array_fill_keys(
            AppointmentSlot::where('barber_id', $barberId)->where('day', $day)
                ->pluck('slot_time')->map(fn ($time) => substr($time, 0, 8))->all(),
            true
        );
        $times = [];
        foreach ($schedules as $schedule) {
            $start = $this->timestamp($day, substr($schedule->opens_at, 0, 5));
            $closes = $this->timestamp($day, substr($schedule->closes_at, 0, 5));
            for ($candidate = $start; $candidate->lt($closes); $candidate = $candidate->addMinutes(config('booking.interval_minutes'))) {
                if ($this->validTime($barber, $service, $candidate, $busy)) $times[$candidate->format('H:i')] = true;
            }
        }
        $result = array_keys($times);
        sort($result);
        return $result;
    }

    private function checkExisting(string $key, string $hash): ?Appointment
    {
        $existing = Appointment::where('idempotency_key', $key)->first();
        if ($existing && ! hash_equals($existing->request_hash, $hash)) {
            throw new BookingConflict('La clave de solicitud ya corresponde a otra reserva.');
        }
        return $existing;
    }

    /**
     * El registro de barbero se bloquea (SELECT ... FOR UPDATE) y serializa confirmaciones
     * para ese barbero. UNIQUE(barber_id,day,slot_time) es una defensa adicional en MySQL.
     */
    public function book(array $data, string $source = 'web', ?int $adminId = null): Appointment
    {
        $phone = preg_replace('/\D+/', '', $data['phone']);
        $hash = hash('sha256', json_encode([
            $data['barber_id'], $data['service_id'], $data['date'], $data['time'], $phone, $source,
        ], JSON_UNESCAPED_UNICODE));
        $key = $data['idempotency_key'];
        try {
            return DB::transaction(function () use ($data, $source, $adminId, $phone, $hash, $key) {
                if ($existing = $this->checkExisting($key, $hash)) return $existing->load(['client', 'service', 'barber']);
                $barber = Barber::whereKey($data['barber_id'])->lockForUpdate()->firstOrFail();
                // Segundo chequeo: otro request puede haber confirmado mientras esperábamos el lock.
                if ($existing = $this->checkExisting($key, $hash)) return $existing->load(['client', 'service', 'barber']);
                $service = Service::findOrFail($data['service_id']);
                $start = $this->timestamp($data['date'], $data['time']);
                $occupied = array_fill_keys(
                    AppointmentSlot::where('barber_id', $barber->id)->where('day', $data['date'])
                        ->pluck('slot_time')->map(fn ($time) => substr($time, 0, 8))->all(), true
                );
                if (! $this->validTime($barber, $service, $start, $occupied)) {
                    throw new BookingConflict('Ese horario ya no está disponible. Elige otro.');
                }
                $client = Client::firstOrCreate(['phone' => $phone], ['name' => trim($data['client_name']), 'active' => true]);
                if (! $client->active) $client->update(['active' => true]);
                $appointment = Appointment::create([
                    'idempotency_key' => $key, 'request_hash' => $hash,
                    'barber_id' => $barber->id, 'service_id' => $service->id, 'client_id' => $client->id,
                    'starts_at' => $start->format('Y-m-d H:i:s'),
                    'ends_at' => $start->addMinutes($service->duration_minutes)->format('Y-m-d H:i:s'),
                    'status' => 'confirmed', 'source' => $source, 'notes' => $data['notes'] ?? null,
                ]);
                $rows = [];
                foreach ($this->periods($start, $service->duration_minutes) as $period) {
                    $rows[] = [
                        'appointment_id' => $appointment->id, 'barber_id' => $barber->id,
                        'day' => $data['date'], 'slot_time' => $period,
                    ];
                }
                AppointmentSlot::insert($rows);
                $appointment->events()->create(['user_id' => $adminId, 'from_status' => null, 'to_status' => 'confirmed']);
                return $appointment->load(['barber', 'service', 'client']);
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            // Una colisión de idempotencia devuelve la reserva previa; una de franjas devuelve 409.
            if ($existing = $this->checkExisting($key, $hash)) return $existing->load(['barber', 'service', 'client']);
            throw new BookingConflict('Ese horario acaba de ser reservado. Selecciona otro.');
        }
    }

    public function changeStatus(Appointment $appointment, string $status, int $adminId): Appointment
    {
        return DB::transaction(function () use ($appointment, $status, $adminId) {
            // Orden de locks idéntico al de book(): barbero y después reserva.
            Barber::whereKey($appointment->barber_id)->lockForUpdate()->firstOrFail();
            $appointment = Appointment::whereKey($appointment->id)->lockForUpdate()->firstOrFail();
            if ($appointment->status === $status) return $appointment;
            if ($appointment->status !== 'confirmed') {
                throw new BookingConflict('Solo se puede cambiar el estado de una cita confirmada.');
            }
            if (in_array($status, ['no_show', 'completed'], true) && $appointment->starts_at->isFuture()) {
                throw new BookingConflict('Aún no puedes marcar como completada o inasistencia una cita futura.');
            }
            if (! in_array($status, ['cancelled', 'completed', 'no_show'], true)) {
                throw new BookingConflict('Estado no permitido.');
            }
            $previous = $appointment->status;
            $appointment->update(['status' => $status]);
            if (in_array($status, ['cancelled', 'no_show'], true)) $appointment->slots()->delete();
            $appointment->events()->create([
                'user_id' => $adminId, 'from_status' => $previous, 'to_status' => $status,
            ]);
            return $appointment->load(['client', 'service', 'barber']);
        }, 3);
    }
}
