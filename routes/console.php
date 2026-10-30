<?php

use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

Artisan::command('admin:create {email}', function (string $email) {
    $email = strtolower(trim($email));
    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $this->error('Correo electrónico inválido.');
        return 1;
    }
    $name = $this->ask('Nombre del administrador', 'Administrador');
    $password = $this->secret('Contraseña (mínimo 12 caracteres)');
    if (! $password || mb_strlen($password) < 12) {
        $this->error('La contraseña requiere al menos 12 caracteres.');
        return 1;
    }
    User::updateOrCreate(['email' => $email], [
        'name' => $name, 'password' => Hash::make($password), 'is_admin' => true,
    ]);
    $this->info('Administrador creado o actualizado.');
    return 0;
})->purpose('Crear administrador con contraseña interactiva, sin credenciales hardcodeadas');
