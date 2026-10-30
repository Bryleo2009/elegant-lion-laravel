<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkSchedule extends Model
{
    public $timestamps = false;
    protected $fillable = ['barber_id', 'weekday', 'sort_order', 'enabled', 'opens_at', 'closes_at'];
    protected function casts(): array { return ['enabled' => 'boolean']; }
}
