<?php
namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\AppointmentEvent;
use App\Models\Barber;
use App\Models\Client;
use App\Models\Service;
use App\Models\TimeOff;
use App\Models\WorkSchedule;
use App\Services\AppointmentCompletionService;
use App\Services\BookingConflict;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required','email'], 'password' => ['required','string']]);
        if (! Auth::attempt($data)) throw ValidationException::withMessages(['email' => 'Credenciales incorrectas.']);
        if (! $request->user()?->is_admin) {
            Auth::logout();
            throw ValidationException::withMessages(['email' => 'No tienes permisos de administración.']);
        }
        $request->session()->regenerate();
        return response()->json(['user' => $request->user()->only(['id','name','email'])]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return response()->json(['ok' => true, 'csrf_token' => csrf_token()]);
    }

    public function session(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user()?->is_admin ? $request->user()->only(['id','name','email']) : null,
        ]);
    }

    public function overview(Request $request, AppointmentCompletionService $completion): JsonResponse
    {
        $completion->completeExpired();
        $filters = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
            'barber_id' => ['sometimes', 'required', 'integer', 'exists:barbers,id'],
        ]);
        $day = $filters['date'] ?? now()->toDateString();
        $query = Appointment::with(['client:id,name,phone','barber:id,name','service:id,name,duration_minutes,price_cents'])
            ->whereDate('starts_at', $day);
        if (isset($filters['barber_id'])) {
            $query->where('barber_id', $filters['barber_id']);
        }
        $query->orderBy('starts_at');
        $appointments = $query->get();
        $autoCompletedIds = AppointmentEvent::query()
            ->whereIn('appointment_id', $appointments->pluck('id'))
            ->where('to_status', 'completed')
            ->where('note', AppointmentCompletionService::AUDIT_NOTE)
            ->pluck('appointment_id')->flip();

        $appointments->each(function (Appointment $item) use ($autoCompletedIds) {
            $item->setAttribute('day', $item->starts_at->format('Y-m-d'));
            $item->setAttribute('time', $item->starts_at->format('H:i'));
            $item->setAttribute('auto_completed', $item->status === 'completed' && $autoCompletedIds->has($item->id));
        });
        return response()->json([
            'appointments' => $appointments,
            'stats' => [
                'total' => $appointments->count(),
                'confirmed' => $appointments->where('status', 'confirmed')->count(),
                'completed' => $appointments->where('status', 'completed')->count(),
                'no_show' => $appointments->where('status', 'no_show')->count(),
            ],
        ]);
    }

    public function manualBooking(Request $request, BookingService $booking): JsonResponse
    {
        $request->merge(['phone' => preg_replace('/\D+/', '', (string) $request->input('phone', ''))]);
        $data = $request->validate([
            'barber_id' => ['required', 'integer', 'exists:barbers,id'],
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'client_name' => ['required', 'string', 'min:3', 'max:120'],
            'phone' => ['required', 'regex:/^9[0-9]{8}$/'],
            'idempotency_key' => ['required', 'uuid'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        try {
            return response()->json(['appointment' => $booking->book($data, 'admin', $request->user()->id)], 201);
        } catch (BookingConflict $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }
    }

    public function status(Request $request, Appointment $appointment, BookingService $booking): JsonResponse
    {
        $data = $request->validate(['status' => ['required', 'in:cancelled,completed,no_show']]);
        try {
            return response()->json(['appointment' => $booking->changeStatus($appointment, $data['status'], $request->user()->id)]);
        } catch (BookingConflict $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }
    }

    public function clients(Request $request): JsonResponse
    {
        $query = Client::query()->withCount(['appointments as visits' => fn ($q) => $q->where('status', 'completed')]);
        if ($search = trim((string) $request->input('search', ''))) {
            $query->where(fn ($q) => $q->where('name', 'like', "%$search%")
                ->orWhere('phone', 'like', "%$search%"));
        }
        return response()->json(['clients' => $query->latest()->limit(200)->get()]);
    }

    public function clientStore(Request $request): JsonResponse
    {
        $request->merge(['phone' => preg_replace('/\D+/', '', (string) $request->input('phone', ''))]);
        $data = $request->validate([
            'name' => ['required','string','min:3','max:120'],
            'phone' => ['required','regex:/^9[0-9]{8}$/','unique:clients,phone'],
            'notes' => ['nullable','string','max:1000'],
        ]);
        return response()->json(['client' => Client::create($data)], 201);
    }

    public function clientUpdate(Request $request, Client $client): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes','required','string','min:3','max:120'],
            'notes' => ['nullable','string','max:1000'],
            'active' => ['sometimes','required','boolean'],
        ]);
        $client->update($data);
        // Archivar no elimina citas ni historial de inasistencias.
        return response()->json(['client' => $client]);
    }

    public function barbers(): JsonResponse { return response()->json(['barbers' => Barber::orderBy('sort_order')->orderBy('name')->get()]); }

    public function barberStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required','string','min:2','max:100'],
            'subtitle' => ['nullable','string','max:120'],
            'booking_days_ahead' => ['sometimes','integer','between:1,90'],
        ]);
        $barber = DB::transaction(function () use ($data) {
            $barber = Barber::create($data);
            foreach (range(1, 6) as $day) WorkSchedule::create([
                'barber_id' => $barber->id, 'weekday' => $day,
                'sort_order' => 0, 'enabled' => true, 'opens_at' => '10:00:00', 'closes_at' => '19:00:00',
            ]);
            return $barber;
        });
        return response()->json(['barber' => $barber], 201);
    }

    public function barberUpdate(Request $request, Barber $barber): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes','required','string','min:2','max:100'],
            'subtitle' => ['nullable','string','max:120'],
            'active' => ['sometimes','required','boolean'],
            'booking_days_ahead' => ['sometimes','required','integer','between:1,90'],
        ]);
        $barber->update($data);
        return response()->json(['barber' => $barber]);
    }

    public function services(): JsonResponse { return response()->json(['services' => Service::orderBy('name')->get()]); }

    public function serviceStore(Request $request): JsonResponse
    {
        $data = $this->validateService($request);
        return response()->json(['service' => Service::create($data)], 201);
    }

    public function serviceUpdate(Request $request, Service $service): JsonResponse
    {
        $data = $this->validateService($request);
        $service->update($data);
        return response()->json(['service' => $service]);
    }

    public function serviceDestroy(Service $service): JsonResponse
    {
        if ($service->appointments()->exists()) {
            return response()->json(['message' => 'Este servicio tiene reservas registradas. Desactívalo para conservar el historial.'], 409);
        }
        $service->delete();
        return response()->json(['ok' => true]);
    }

    private function validateService(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required','string','min:2','max:100'],
            'duration_minutes' => ['required','integer','between:15,180'],
            'price_cents' => ['required','integer','min:0'],
            'active' => ['sometimes','boolean'],
        ]);
        if ($data['duration_minutes'] % 15 !== 0) {
            throw ValidationException::withMessages(['duration_minutes' => 'La duración debe ser múltiplo de 15 minutos.']);
        }
        return $data;
    }

    public function schedules(Request $request): JsonResponse
    {
        $data = $request->validate(['barber_id' => ['required','integer','exists:barbers,id']]);
        $blocks = WorkSchedule::where('barber_id', $data['barber_id'])->where('enabled', true)
            ->orderBy('weekday')->orderBy('sort_order')->get()->groupBy('weekday');
        return response()->json([
            'barber' => Barber::findOrFail($data['barber_id'])->only(['id','name','booking_days_ahead']),
            'rules' => collect(range(0, 6))->map(fn ($weekday) => [
                'weekday' => $weekday,
                'blocks' => ($blocks[$weekday] ?? collect())->map(fn ($block) => [
                    'opens_at' => substr($block->opens_at, 0, 5),
                    'closes_at' => substr($block->closes_at, 0, 5),
                ])->values(),
            ]),
            'time_offs' => TimeOff::where('barber_id', $data['barber_id'])->orderBy('day')->get(),
        ]);
    }

    public function scheduleUpdate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'barber_id' => ['required','integer','exists:barbers,id'],
            'booking_days_ahead' => ['required','integer','between:1,90'],
            'rules' => ['required','array','size:7'],
            'rules.*.weekday' => ['required','integer','between:0,6','distinct'],
            'rules.*.blocks' => ['present','array','max:4'],
            'rules.*.blocks.*.opens_at' => ['required','date_format:H:i'],
            'rules.*.blocks.*.closes_at' => ['required','date_format:H:i'],
        ]);
        foreach ($data['rules'] as $rule) {
            $blocks = collect($rule['blocks'])->sortBy('opens_at')->values();
            foreach ($blocks as $index => $block) {
                if ($block['opens_at'] >= $block['closes_at'] ||
                    (int) substr($block['opens_at'], 3, 2) % 30 !== 0 ||
                    (int) substr($block['closes_at'], 3, 2) % 30 !== 0 ||
                    ($index > 0 && $blocks[$index - 1]['closes_at'] > $block['opens_at'])) {
                    throw ValidationException::withMessages(['rules' => 'Los bloques deben tener pasos de 30 minutos, fin posterior al inicio y no solaparse.']);
                }
            }
        }
        DB::transaction(function () use ($data) {
            $barber = Barber::whereKey($data['barber_id'])->lockForUpdate()->firstOrFail();
            $barber->update(['booking_days_ahead' => $data['booking_days_ahead']]);
            WorkSchedule::where('barber_id', $data['barber_id'])->delete();
            foreach ($data['rules'] as $rule) {
                foreach (collect($rule['blocks'])->sortBy('opens_at')->values() as $index => $block) {
                    WorkSchedule::create([
                        'barber_id' => $data['barber_id'], 'weekday' => $rule['weekday'], 'sort_order' => $index,
                        'enabled' => true, 'opens_at' => $block['opens_at'], 'closes_at' => $block['closes_at'],
                    ]);
                }
            }
        });
        return response()->json(['ok' => true, 'message' => 'Los cambios aplican a nuevas reservas; no cancelan citas existentes.']);
    }

    public function timeOffStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'barber_id' => ['required','integer','exists:barbers,id'],
            'day' => ['required','date_format:Y-m-d'],
            'ends_on' => ['required','date_format:Y-m-d','after_or_equal:day'],
            'reason' => ['nullable','string','max:200'],
        ]);
        try {
            $off = DB::transaction(function () use ($data) {
                Barber::whereKey($data['barber_id'])->lockForUpdate()->firstOrFail();
                if (Appointment::where('barber_id', $data['barber_id'])
                    ->whereBetween('starts_at', [$data['day'].' 00:00:00', $data['ends_on'].' 23:59:59'])
                    ->where('status', 'confirmed')->exists()) {
                    throw new BookingConflict('Hay citas confirmadas dentro del rango. Cancélalas antes de bloquearlo.');
                }
                return TimeOff::updateOrCreate(
                    ['barber_id' => $data['barber_id'], 'day' => $data['day']],
                    ['ends_on' => $data['ends_on'], 'reason' => $data['reason'] ?? null]
                );
            });
            return response()->json(['time_off' => $off], 201);
        } catch (BookingConflict $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }
    }

    public function timeOffDestroy(TimeOff $timeOff): JsonResponse
    {
        DB::transaction(function () use ($timeOff) {
            Barber::whereKey($timeOff->barber_id)->lockForUpdate()->firstOrFail();
            $timeOff->delete();
        });
        return response()->json(['ok' => true]);
    }
}
