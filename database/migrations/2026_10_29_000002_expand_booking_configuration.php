<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('barbers', 'booking_days_ahead')) {
            Schema::table('barbers', function (Blueprint $table) {
                $table->unsignedSmallInteger('booking_days_ahead')->default(3)->after('sort_order');
            });
        }

        // MySQL/Percona usa el índice único original para respaldar la FK
        // work_schedules.barber_id -> barbers.id. El índice no único debe existir
        // en un ALTER anterior antes de poder retirar la restricción de unicidad.
        if (! Schema::hasIndex('work_schedules', 'work_schedules_barber_id_weekday_index')) {
            Schema::table('work_schedules', function (Blueprint $table) {
                $table->index(
                    ['barber_id', 'weekday'],
                    'work_schedules_barber_id_weekday_index'
                );
            });
        }

        if (Schema::hasIndex('work_schedules', 'work_schedules_barber_id_weekday_unique')) {
            Schema::table('work_schedules', function (Blueprint $table) {
                $table->dropUnique('work_schedules_barber_id_weekday_unique');
            });
        }

        if (! Schema::hasColumn('work_schedules', 'sort_order')) {
            Schema::table('work_schedules', function (Blueprint $table) {
                $table->unsignedTinyInteger('sort_order')->default(0)->after('weekday');
            });
        }

        if (! Schema::hasColumn('time_offs', 'ends_on')) {
            Schema::table('time_offs', function (Blueprint $table) {
                $table->date('ends_on')->nullable()->after('day');
            });
        }

        DB::table('time_offs')->whereNull('ends_on')->update(['ends_on' => DB::raw('day')]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('time_offs', 'ends_on')) {
            Schema::table('time_offs', function (Blueprint $table) {
                $table->dropColumn('ends_on');
            });
        }

        if (! Schema::hasIndex('work_schedules', 'work_schedules_barber_id_weekday_unique')) {
            Schema::table('work_schedules', function (Blueprint $table) {
                $table->unique(
                    ['barber_id', 'weekday'],
                    'work_schedules_barber_id_weekday_unique'
                );
            });
        }

        if (Schema::hasIndex('work_schedules', 'work_schedules_barber_id_weekday_index')) {
            Schema::table('work_schedules', function (Blueprint $table) {
                $table->dropIndex('work_schedules_barber_id_weekday_index');
            });
        }

        if (Schema::hasColumn('work_schedules', 'sort_order')) {
            Schema::table('work_schedules', function (Blueprint $table) {
                $table->dropColumn('sort_order');
            });
        }

        if (Schema::hasColumn('barbers', 'booking_days_ahead')) {
            Schema::table('barbers', function (Blueprint $table) {
                $table->dropColumn('booking_days_ahead');
            });
        }
    }
};
