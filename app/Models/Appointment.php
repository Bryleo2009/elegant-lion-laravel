<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Appointment extends Model
{
    protected $fillable = [
        'idempotency_key', 'request_hash', 'barber_id', 'service_id', 'client_id',
        'starts_at', 'ends_at', 'status', 'source', 'notes',
    ];
    protected $hidden = ['request_hash', 'idempotency_key'];
    protected function casts(): array { return ['starts_at' => 'datetime', 'ends_at' => 'datetime']; }
    public function barber(): BelongsTo { return $this->belongsTo(Barber::class); }
    public function service(): BelongsTo { return $this->belongsTo(Service::class); }
    public function client(): BelongsTo { return $this->belongsTo(Client::class); }
    public function slots(): HasMany { return $this->hasMany(AppointmentSlot::class); }
    public function events(): HasMany { return $this->hasMany(AppointmentEvent::class); }
}
