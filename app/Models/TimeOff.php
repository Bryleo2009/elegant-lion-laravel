<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TimeOff extends Model
{
    protected $fillable = ['barber_id', 'day', 'ends_on', 'reason'];
    protected function casts(): array { return ['day' => 'date:Y-m-d', 'ends_on' => 'date:Y-m-d']; }
}
