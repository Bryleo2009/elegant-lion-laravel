<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentSlot extends Model
{
    public $timestamps = false;
    protected $fillable = ['appointment_id', 'barber_id', 'day', 'slot_time'];
}
