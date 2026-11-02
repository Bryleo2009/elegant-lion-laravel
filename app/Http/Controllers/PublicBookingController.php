<?php
namespace App\Http\Controllers;

use App\Models\Barber;
use App\Models\Client;
use Illuminate\Validation\ValidationException;
use App\Models\Service;
use App\Services\BookingConflict;
use App\Services\BookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class PublicBookingController extends Controller
{
    public function setup(): JsonResponse
    {
        return response()->json([
            'barbers' => Barber::where('active', true)->orderBy('sort_order')->orderBy('name')->get(['id','name','subtitle','booking_days_ahead']),
            'services' => Service::where('active', true)->orderBy('price_cents')->get(['id','name','duration_minutes','price_cents']),
            'notice_minutes' => config('booking.notice_minutes'),
            'days_ahead' => config('booking.days_ahead'),
            'today' => now()->toDateString(),
        ]);
    }

    /** Consulta únicamente existencia, sin exponer nombres ni otros datos personales. */
    public function lookupClient(Request $request): JsonResponse
    {
        $request->merge(['phone' => preg_replace('/\\D+/', '', (string) $request->input('phone', ''))]);
        $data = $request->validate([
            'phone' => ['required', 'regex:/^9[0-9]{8}$/'],
        ]);

        return response()->json([
            'exists' => Client::query()->where('phone', $data['phone'])->exists(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function availability(Request $request, BookingService $booking): JsonResponse
    {
        $data = $request->validate([
            'barber_id' => ['required', 'integer', 'exists:barbers,id'],
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'date' => ['required', 'date_format:Y-m-d'],
        ]);
        return response()->json(['slots' => $booking->availability($data['barber_id'], $data['service_id'], $data['date'])]);
    }

    public function store(Request $request, BookingService $booking): JsonResponse
    {
        $request->merge(['phone' => preg_replace('/\D+/', '', (string) $request->input('phone', ''))]);
        $data = $request->validate([
            'barber_id' => ['required', 'integer', 'exists:barbers,id'],
            'service_id' => ['required', 'integer', 'exists:services,id'],
            'date' => ['required', 'date_format:Y-m-d'],
            'time' => ['required', 'date_format:H:i'],
            'client_name' => ['nullable', 'string', 'min:3', 'max:120'],
            'phone' => ['required', 'regex:/^9[0-9]{8}$/'],
            'idempotency_key' => ['required', 'uuid'],
        ]);
        if (! Client::where('phone', $data['phone'])->exists()
            && trim((string) ($data['client_name'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'client_name' => 'Ingresa tu nombre completo para registrarte.',
            ]);
        }
        try {
            $appointment = $booking->book($data);
            return response()->json(['appointment' => [
                'id' => $appointment->id,
                'status' => $appointment->status,
                'date' => $appointment->starts_at->format('Y-m-d'),
                'time' => $appointment->starts_at->format('H:i'),
                'barber' => $appointment->barber->name,
                'service' => $appointment->service->name,
                'name' => $appointment->client->name,
            ]], 201);
        } catch (BookingConflict $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }
    }
}
