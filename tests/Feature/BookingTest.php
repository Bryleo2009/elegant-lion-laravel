<?php
namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\AppointmentSlot;
use App\Models\Barber;
use App\Models\Service;
use App\Models\User;
use App\Models\TimeOff;
use App\Models\WorkSchedule;
use App\Services\BookingConflict;
use App\Services\BookingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    private function setupCatalog(): array
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        $barber = Barber::firstOrFail();
        $barber->update(['booking_days_ahead' => 14]);
        return [$barber, Service::where('duration_minutes', 30)->firstOrFail()];
    }

    private function findAvailable(Barber $barber, Service $service): array
    {
        $engine = app(BookingService::class);
        for ($day = 2; $day <= 10; $day++) {
            $date = CarbonImmutable::now(config('app.timezone'))->addDays($day)->toDateString();
            $slots = $engine->availability($barber->id, $service->id, $date);
            if (count($slots) >= 2) return [$date, $slots[0], $slots[1]];
        }
        $this->fail('No hay horarios de prueba');
    }

    private function bookingData(Barber $barber, Service $service): array
    {
        [$date, $time] = $this->findAvailable($barber, $service);
        return [
            'barber_id' => $barber->id, 'service_id' => $service->id,
            'date' => $date, 'time' => $time, 'client_name' => 'Cliente Prueba',
            'phone' => '987654321', 'idempotency_key' => (string) Str::uuid(),
        ];
    }

    public function test_repeating_identical_request_does_not_create_duplicate(): void
    {
        [$barber, $service] = $this->setupCatalog();
        $data = $this->bookingData($barber, $service);
        $this->postJson('/api/bookings', $data)->assertCreated();
        $this->postJson('/api/bookings', $data)->assertCreated();
        $this->assertDatabaseCount('appointments', 1);
        $this->assertDatabaseCount('appointment_slots', 2);
    }

    public function test_different_keys_cannot_book_same_time(): void
    {
        [$barber, $service] = $this->setupCatalog();
        $data = $this->bookingData($barber, $service);
        $this->postJson('/api/bookings', $data)->assertCreated();
        $data['idempotency_key'] = (string) Str::uuid();
        $data['phone'] = '987654322';
        $this->postJson('/api/bookings', $data)->assertStatus(409);
        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_same_key_cannot_be_reused_with_different_details(): void
    {
        [$barber, $service] = $this->setupCatalog();
        $data = $this->bookingData($barber, $service);
        $this->postJson('/api/bookings', $data)->assertCreated();
        $data['phone'] = '987654322';
        $this->postJson('/api/bookings', $data)->assertStatus(409);
    }

    public function test_overlapping_long_service_blocks_short_service(): void
    {
        [$barber, $short] = $this->setupCatalog();
        $long = Service::where('duration_minutes', 60)->firstOrFail();
        $long->update(['duration_minutes' => 90]);
        [$date, $time, $next] = $this->findAvailable($barber, $long);
        $data = [
            'barber_id'=>$barber->id,'service_id'=>$long->id,'date'=>$date,'time'=>$time,
            'client_name'=>'Cliente Uno','phone'=>'987654321','idempotency_key'=>(string) Str::uuid(),
        ];
        $this->postJson('/api/bookings',$data)->assertCreated();
        $data['service_id'] = $short->id;
        $data['time'] = $next;
        $data['phone'] = '987654322';
        $data['idempotency_key'] = (string) Str::uuid();
        $this->postJson('/api/bookings',$data)->assertStatus(409);
    }

    public function test_cancel_releases_slot_and_audits_status(): void
    {
        [$barber, $service] = $this->setupCatalog();
        $data = $this->bookingData($barber, $service);
        $res = $this->postJson('/api/bookings', $data)->assertCreated();
        $admin = User::create(['name'=>'Owner','email'=>'owner@example.test','password'=>'a-long-password-for-tests','is_admin'=>true]);
        $id = $res->json('appointment.id');
        $this->actingAs($admin)->patchJson("/api/admin/appointments/$id/status", ['status'=>'cancelled'])->assertOk();
        $this->assertDatabaseCount('appointment_slots', 0);
        $this->assertDatabaseHas('appointment_events', ['appointment_id'=>$id,'to_status'=>'cancelled']);
        $data['phone'] = '987654322';
        $data['idempotency_key'] = (string) Str::uuid();
        $this->postJson('/api/bookings', $data)->assertCreated();
        $this->assertDatabaseCount('appointments', 2);
    }


    public function test_no_show_releases_slots_without_erasing_client_history(): void
    {
        [$barber, $service] = $this->setupCatalog();
        $data = $this->bookingData($barber, $service);
        $response = $this->postJson('/api/bookings', $data)->assertCreated();
        $id = $response->json('appointment.id');
        $appointment = Appointment::findOrFail($id);
        // La cita ya pasó: el administrador marca inasistencia. Se conserva el historial.
        $appointment->update(['starts_at' => now()->subDay(), 'ends_at' => now()->subDay()->addMinutes(30)]);
        $admin = User::create(['name'=>'Owner','email'=>'owner2@example.test','password'=>'a-long-password-for-tests','is_admin'=>true]);
        $this->actingAs($admin)->patchJson("/api/admin/appointments/$id/status", ['status'=>'no_show'])->assertOk();
        $this->assertDatabaseHas('appointments', ['id'=>$id,'status'=>'no_show']);
        $this->assertDatabaseHas('appointment_events', ['appointment_id'=>$id,'to_status'=>'no_show']);
        $this->assertDatabaseCount('appointment_slots', 0);
        $this->assertDatabaseHas('clients', ['phone' => '987654321']);
    }

    public function test_time_off_prevents_booking(): void
    {
        [$barber, $service] = $this->setupCatalog();
        $data = $this->bookingData($barber, $service);
        TimeOff::create(['barber_id'=>$barber->id,'day'=>$data['date'],'reason'=>'Descanso']);
        $this->postJson('/api/bookings', $data)->assertStatus(409);
    }

    public function test_public_cannot_manage_appointments(): void
    {
        $this->getJson('/api/admin/appointments')->assertUnauthorized();
    }

    public function test_slot_unique_constraint_guards_against_double_booking(): void
    {
        [$barber, $service] = $this->setupCatalog();
        $data = $this->bookingData($barber, $service);
        $a = app(BookingService::class)->book($data);
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        AppointmentSlot::create([
            'appointment_id'=>$a->id, 'barber_id'=>$barber->id,
            'day'=>$data['date'], 'slot_time'=>$data['time'].':00',
        ]);
    }

    public function test_barber_booking_horizon_is_enforced_by_server(): void
    {
        [$barber, $service] = $this->setupCatalog();
        $barber->update(['booking_days_ahead' => 3]);
        $date = CarbonImmutable::now(config('app.timezone'))->addDays(4)->toDateString();

        $response = $this->postJson('/api/bookings', [
            'barber_id' => $barber->id, 'service_id' => $service->id,
            'date' => $date, 'time' => '10:00', 'client_name' => 'Cliente Horizonte',
            'phone' => '987654329', 'idempotency_key' => (string) Str::uuid(),
        ]);

        $response->assertStatus(409);
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_availability_combines_multiple_non_overlapping_blocks(): void
    {
        [$barber, $service] = $this->setupCatalog();
        $barber->update(['booking_days_ahead' => 14]);
        $date = CarbonImmutable::now(config('app.timezone'))->addDays(2);
        WorkSchedule::where('barber_id', $barber->id)->where('weekday', $date->dayOfWeek)->delete();
        WorkSchedule::create(['barber_id'=>$barber->id,'weekday'=>$date->dayOfWeek,'opens_at'=>'09:00','closes_at'=>'12:00','sort_order'=>0]);
        WorkSchedule::create(['barber_id'=>$barber->id,'weekday'=>$date->dayOfWeek,'opens_at'=>'15:00','closes_at'=>'18:00','sort_order'=>1]);

        $slots = app(BookingService::class)->availability($barber->id, $service->id, $date->toDateString());

        $this->assertContains('09:00', $slots);
        $this->assertContains('15:00', $slots);
        $this->assertNotContains('12:30', $slots);
    }

    public function test_public_availability_starts_every_hour_even_when_schedule_opens_at_half_hour(): void
    {
        [$barber, $service] = $this->setupCatalog();
        $date = CarbonImmutable::now(config('app.timezone'))->addDays(2);
        WorkSchedule::where('barber_id', $barber->id)->where('weekday', $date->dayOfWeek)->delete();
        WorkSchedule::create(['barber_id'=>$barber->id,'weekday'=>$date->dayOfWeek,'opens_at'=>'09:30','closes_at'=>'12:30','sort_order'=>0]);

        $slots = app(BookingService::class)->availability($barber->id, $service->id, $date->toDateString());

        $this->assertSame(['10:00', '11:00', '12:00'], $slots);
    }

    public function test_public_booking_rejects_half_hour_start_time(): void
    {
        [$barber, $service] = $this->setupCatalog();
        $data = $this->bookingData($barber, $service);
        $data['time'] = substr($data['time'], 0, 2).':30';

        $this->postJson('/api/bookings', $data)->assertStatus(409);
        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_time_off_range_blocks_every_included_day(): void
    {
        [$barber, $service] = $this->setupCatalog();
        $barber->update(['booking_days_ahead' => 14]);
        $start = CarbonImmutable::now(config('app.timezone'))->addDays(2);
        TimeOff::create(['barber_id'=>$barber->id,'day'=>$start->toDateString(),'ends_on'=>$start->addDays(2)->toDateString(),'reason'=>'Vacaciones']);

        foreach (range(0, 2) as $offset) {
            $this->assertSame([], app(BookingService::class)->availability($barber->id, $service->id, $start->addDays($offset)->toDateString()));
        }
    }

    public function test_unused_service_can_be_deleted_but_historical_service_is_preserved(): void
    {
        [$barber, $service] = $this->setupCatalog();
        $admin = User::create(['name'=>'Owner','email'=>'services@example.test','password'=>'a-long-password-for-tests','is_admin'=>true]);
        $unused = Service::create(['name'=>'Servicio temporal','duration_minutes'=>30,'price_cents'=>1000,'active'=>true]);

        $this->actingAs($admin)->deleteJson("/api/admin/services/{$unused->id}")->assertOk();
        $this->assertDatabaseMissing('services', ['id'=>$unused->id]);

        app(BookingService::class)->book($this->bookingData($barber, $service));
        $this->actingAs($admin)->deleteJson("/api/admin/services/{$service->id}")->assertStatus(409);
        $this->assertDatabaseHas('services', ['id'=>$service->id]);
    }

    public function test_admin_can_save_multiple_blocks_and_booking_window(): void
    {
        [$barber] = $this->setupCatalog();
        $admin = User::create(['name'=>'Owner','email'=>'schedule@example.test','password'=>'a-long-password-for-tests','is_admin'=>true]);
        $rules = collect(range(0, 6))->map(fn ($weekday) => [
            'weekday' => $weekday,
            'blocks' => $weekday === 1 ? [
                ['opens_at'=>'09:00','closes_at'=>'13:00'],
                ['opens_at'=>'15:00','closes_at'=>'19:00'],
            ] : [],
        ])->all();

        $this->actingAs($admin)->putJson('/api/admin/schedules', [
            'barber_id'=>$barber->id,
            'booking_days_ahead'=>5,
            'rules'=>$rules,
        ])->assertOk();

        $this->assertDatabaseHas('barbers', ['id'=>$barber->id,'booking_days_ahead'=>5]);
        $this->assertSame(2, WorkSchedule::where('barber_id', $barber->id)->count());
    }

    public function test_admin_can_create_a_time_off_range(): void
    {
        [$barber] = $this->setupCatalog();
        $admin = User::create(['name'=>'Owner','email'=>'range@example.test','password'=>'a-long-password-for-tests','is_admin'=>true]);
        $start = CarbonImmutable::now(config('app.timezone'))->addDays(4)->toDateString();
        $end = CarbonImmutable::now(config('app.timezone'))->addDays(6)->toDateString();

        $this->actingAs($admin)->postJson('/api/admin/time-offs', [
            'barber_id'=>$barber->id,'day'=>$start,'ends_on'=>$end,'reason'=>'Vacaciones',
        ])->assertCreated();

        $this->assertDatabaseHas('time_offs', ['barber_id'=>$barber->id,'day'=>$start,'ends_on'=>$end]);
    }
}
