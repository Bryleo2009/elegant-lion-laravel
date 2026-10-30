<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Client extends Model
{
    protected $fillable = ['name', 'phone', 'active', 'notes'];
    public function appointments(): HasMany { return $this->hasMany(Appointment::class); }
    protected function casts(): array { return ['active' => 'boolean']; }
}
