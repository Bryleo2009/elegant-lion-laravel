<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->boolean('is_admin')->default(false);
            $table->rememberToken();
            $table->timestamps();
        });
        Schema::create('barbers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('subtitle', 120)->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->unsignedSmallInteger('duration_minutes');
            $table->unsignedInteger('price_cents')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('phone', 20)->unique();
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('work_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barber_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday'); // 0 = domingo, 6 = sábado.
            $table->boolean('enabled')->default(true);
            $table->time('opens_at')->default('09:00:00');
            $table->time('closes_at')->default('19:00:00');
            $table->unique(['barber_id', 'weekday']);
        });
        Schema::create('time_offs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('barber_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->string('reason', 200)->nullable();
            $table->timestamps();
            $table->unique(['barber_id', 'day']);
        });
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->uuid('idempotency_key')->unique();
            $table->char('request_hash', 64);
            $table->foreignId('barber_id')->constrained()->restrictOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('status', 20)->default('confirmed');
            $table->string('source', 15)->default('web');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index(['barber_id', 'starts_at']);
            $table->index(['status', 'starts_at']);
        });
        Schema::create('appointment_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('barber_id')->constrained()->restrictOnDelete();
            $table->date('day');
            $table->time('slot_time');
            // Segunda barrera contra doble reserva, incluso con solicitudes simultáneas.
            $table->unique(['barber_id', 'day', 'slot_time'], 'unique_barber_timeslot');
            $table->index('appointment_id');
        });
        Schema::create('appointment_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['appointment_events', 'appointment_slots', 'appointments', 'time_offs', 'work_schedules', 'clients', 'services', 'barbers', 'users'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
