<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Barber extends Model
{
    protected $fillable = ['name', 'subtitle', 'active', 'sort_order', 'booking_days_ahead'];
    protected function casts(): array { return ['active' => 'boolean']; }
    public function schedules(): HasMany { return $this->hasMany(WorkSchedule::class); }
    public function timeOffs(): HasMany { return $this->hasMany(TimeOff::class); }
}
