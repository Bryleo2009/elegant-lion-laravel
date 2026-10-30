<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class MigrationRecoveryTest extends TestCase
{
    public function test_expansion_migration_recovers_from_partially_applied_mysql_ddl(): void
    {
        $originalConnection = DB::getDefaultConnection();

        config()->set('database.connections.migration_recovery', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        DB::purge('migration_recovery');
        DB::setDefaultConnection('migration_recovery');

        try {
            $initialMigration = require database_path('migrations/2026_10_29_000001_create_barbershop_tables.php');
            $initialMigration->up();

            DB::table('barbers')->insert([
                'id' => 1,
                'name' => 'Barbero principal',
                'active' => true,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('work_schedules')->insert([
                'barber_id' => 1,
                'weekday' => 1,
                'enabled' => true,
                'opens_at' => '09:00:00',
                'closes_at' => '13:00:00',
            ]);
            DB::table('time_offs')->insert([
                'barber_id' => 1,
                'day' => '2026-11-02',
                'reason' => 'Descanso',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Simula el primer ALTER persistido por MySQL antes del error 1553.
            Schema::table('barbers', function ($table) {
                $table->unsignedSmallInteger('booking_days_ahead')->default(3);
            });

            $expansionMigration = require database_path('migrations/2026_10_29_000002_expand_booking_configuration.php');
            $expansionMigration->up();

            $schema = Schema::getFacadeRoot();

            $this->assertTrue($schema->hasColumn('barbers', 'booking_days_ahead'));
            $this->assertTrue($schema->hasColumn('work_schedules', 'sort_order'));
            $this->assertTrue($schema->hasIndex('work_schedules', 'work_schedules_barber_id_weekday_index'));
            $this->assertFalse($schema->hasIndex('work_schedules', 'work_schedules_barber_id_weekday_unique'));

            $foreignKeys = collect($schema->getForeignKeys('work_schedules'));
            $this->assertTrue($foreignKeys->contains(
                fn (array $foreignKey) => $foreignKey['columns'] === ['barber_id']
                    && $foreignKey['foreign_table'] === 'barbers'
            ));

            DB::table('work_schedules')->insert([
                'barber_id' => 1,
                'weekday' => 1,
                'sort_order' => 1,
                'enabled' => true,
                'opens_at' => '15:00:00',
                'closes_at' => '19:00:00',
            ]);

            $this->assertSame(2, DB::table('work_schedules')->where('barber_id', 1)->where('weekday', 1)->count());
            $this->assertSame('2026-11-02', DB::table('time_offs')->value('ends_on'));
        } finally {
            DB::disconnect('migration_recovery');
            DB::setDefaultConnection($originalConnection);
            DB::purge('migration_recovery');
        }
    }
}
