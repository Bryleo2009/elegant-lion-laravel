<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppointmentEvent extends Model
{
    protected $fillable = ['appointment_id', 'user_id', 'from_status', 'to_status', 'note'];
}
