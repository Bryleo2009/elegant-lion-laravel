<?php
namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Barber;
use App\Models\Service;
use App\Models\User;
use App\Services\AppointmentCompletionService;
use App\Services\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AppointmentAutoCompletionTest extends TestCase
{
    use RefreshDatabase;

    private function futureAppointment(): Appointment
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        $barber = Barber::firstOrFail();
        $barber->update(['booking_days_ahead' => 14]);
        $service = Service::where('duration_minutes', 30)->firstOrFail();

        for ($offset = 2; $offset <= 10; $offset++) {
            $day = CarbonImmutable::now(config('app.timezone'))->addDays($offset)->toDateString();
            $slots = app(BookingService::class)->availability($barber->id, $service->id, $day);
            if ($slots) {
                return app(BookingService::class)->book([
                    'barber_id' => $barber->id,
                    'service_id' => $service->id,
                    'date' => $day,
                    'time' => $slots[0],
                    'client_name' => 'Cliente Prueba',
                    'phone' => '987654321',
                    'idempotency_key' => (string) Str::uuid(),
                ]);
            }
        }

        $this->fail('No existe un horario disponible de prueba.');
    }

    public function test_only_finished_confirmed_appointments_are_auto_completed_once(): void
    {
        $appointment = $this->futureAppointment();
        $now = CarbonImmutable::now(config('app.timezone'));
        $appointment->update([
            'starts_at' => $now->subHour(),
            'ends_at' => $now->addMinutes(5),
        ]);

        $completion = app(AppointmentCompletionService::class);
        $this->assertSame(0, $completion->completeExpired());
        $this->assertSame('confirmed', $appointment->fresh()->status);

        $appointment->update(['ends_at' => $now->subMinute()]);
        $this->assertSame(1, $completion->completeExpired());
        $this->assertSame(0, $completion->completeExpired());
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => 'completed']);
        $this->assertDatabaseHas('appointment_events', [
            'appointment_id' => $appointment->id,
            'user_id' => null,
            'from_status' => 'confirmed',
            'to_status' => 'completed',
            'note' => AppointmentCompletionService::AUDIT_NOTE,
        ]);
        $this->assertSame(1, $appointment->events()->where('to_status', 'completed')->count());
        $this->assertSame(2, $appointment->slots()->count());
    }

    public function test_no_show_is_not_overwritten_by_automatic_completion(): void
    {
        $appointment = $this->futureAppointment();
        $now = CarbonImmutable::now(config('app.timezone'));
        $appointment->update([
            'starts_at' => $now->subHours(2),
            'ends_at' => $now->subHour(),
        ]);
        $admin = User::create([
            'name' => 'Owner', 'email' => 'owner@example.test',
            'password' => 'a-long-password-for-tests', 'is_admin' => true,
        ]);
        app(BookingService::class)->changeStatus($appointment, 'no_show', $admin->id);

        $this->assertSame(0, app(AppointmentCompletionService::class)->completeExpired());
        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => 'no_show']);
    }

    public function test_admin_can_correct_an_automatic_completion_to_no_show(): void
    {
        $appointment = $this->futureAppointment();
        $now = CarbonImmutable::now(config('app.timezone'));
        $appointment->update([
            'starts_at' => $now->subHours(2),
            'ends_at' => $now->subHour(),
        ]);
        $admin = User::create([
            'name' => 'Owner', 'email' => 'owner@example.test',
            'password' => 'a-long-password-for-tests', 'is_admin' => true,
        ]);

        $response = $this->actingAs($admin)->getJson('/api/admin/appointments?date='.$now->toDateString());
        $response->assertOk()
            ->assertJsonPath('appointments.0.status', 'completed')
            ->assertJsonPath('appointments.0.auto_completed', true)
            ->assertJsonPath('stats.completed', 1);

        $this->patchJson("/api/admin/appointments/{$appointment->id}/status", [
            'status' => 'no_show',
        ])->assertOk();

        $this->assertDatabaseHas('appointments', ['id' => $appointment->id, 'status' => 'no_show']);
        $this->assertSame(0, $appointment->slots()->count());
        $this->assertSame(0, app(AppointmentCompletionService::class)->completeExpired());
    }

    public function test_manually_completed_appointment_cannot_be_reclassified(): void
    {
        $appointment = $this->futureAppointment();
        $now = CarbonImmutable::now(config('app.timezone'));
        $appointment->update([
            'starts_at' => $now->subHours(2),
            'ends_at' => $now->subHour(),
        ]);
        $admin = User::create([
            'name' => 'Owner', 'email' => 'owner@example.test',
            'password' => 'a-long-password-for-tests', 'is_admin' => true,
        ]);

        $this->actingAs($admin)->patchJson("/api/admin/appointments/{$appointment->id}/status", [
            'status' => 'completed',
        ])->assertOk();
        $this->patchJson("/api/admin/appointments/{$appointment->id}/status", [
            'status' => 'no_show',
        ])->assertStatus(409);
        $this->assertSame(0, app(AppointmentCompletionService::class)->completeExpired());
    }
}
