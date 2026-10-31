<?php
namespace App\Services;

use App\Models\Appointment;
use App\Models\Barber;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AppointmentCompletionService
{
    public const AUDIT_NOTE = 'Cierre automático al finalizar la reserva.';

    /**
     * Cierra únicamente reservas confirmadas cuyo servicio ya terminó.
     * Usa los mismos locks que BookingService::changeStatus() para evitar
     * sobrescribir cancelaciones o inasistencias registradas en paralelo.
     */
    public function completeExpired(): int
    {
        $cutoff = CarbonImmutable::now(config('app.timezone', 'America/Lima'))->format('Y-m-d H:i:s');
        $completed = 0;

        Appointment::query()
            ->where('status', 'confirmed')
            ->where('ends_at', '<=', $cutoff)
            ->select(['id', 'barber_id'])
            ->orderBy('id')
            ->chunkById(100, function ($pending) use ($cutoff, &$completed) {
                foreach ($pending as $item) {
                    $completed += DB::transaction(function () use ($item, $cutoff) {
                        // Orden consistente con la confirmación y los cambios manuales.
                        Barber::whereKey($item->barber_id)->lockForUpdate()->firstOrFail();
                        $appointment = Appointment::whereKey($item->id)->lockForUpdate()->first();

                        if (! $appointment || $appointment->status !== 'confirmed'
                            || $appointment->ends_at->format('Y-m-d H:i:s') > $cutoff) {
                            return 0;
                        }

                        $appointment->update(['status' => 'completed']);
                        $appointment->events()->create([
                            'user_id' => null,
                            'from_status' => 'confirmed',
                            'to_status' => 'completed',
                            'note' => self::AUDIT_NOTE,
                        ]);

                        // Los slots de una cita completada siguen formando parte del historial.
                        return 1;
                    }, 3);
                }
            });

        return $completed;
    }
}
