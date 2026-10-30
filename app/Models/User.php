<?php
namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $fillable = ['name', 'email', 'password', 'is_admin'];
    protected $hidden = ['password', 'remember_token'];
    protected function casts(): array { return ['is_admin' => 'boolean', 'password' => 'hashed', 'email_verified_at' => 'datetime']; }
}
