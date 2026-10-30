<?php
namespace Database\Seeders;

use App\Models\Barber;
use App\Models\Service;
use App\Models\WorkSchedule;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Datos iniciales editables desde administración. No se crean usuarios ni contraseñas predecibles.
        foreach (['Barbero principal', 'Segundo barbero'] as $index => $name) {
            $barber = Barber::firstOrCreate(['name' => $name], ['sort_order' => $index, 'active' => true]);
            for ($day = 0; $day <= 6; $day++) {
                WorkSchedule::firstOrCreate(['barber_id' => $barber->id, 'weekday' => $day], [
                    'enabled' => $day !== 0, 'opens_at' => '10:00:00', 'closes_at' => '19:00:00',
                ]);
            }
        }
        foreach ([['Corte clásico', 30, 3500], ['Corte + barba', 60, 5000], ['Arreglo de barba', 30, 2500]] as [$name, $minutes, $cents]) {
            Service::firstOrCreate(['name' => $name], ['duration_minutes' => $minutes, 'price_cents' => $cents]);
        }
    }
}
