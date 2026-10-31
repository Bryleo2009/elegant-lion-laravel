<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\PublicBookingController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'app');
Route::view('/admin', 'app');
Route::redirect('/login', '/admin')->name('login');

Route::prefix('api')->group(function () {
    Route::get('/public/setup', [PublicBookingController::class, 'setup']);
    Route::get('/public/availability', [PublicBookingController::class, 'availability'])->middleware('throttle:availability');
    Route::post('/bookings', [PublicBookingController::class, 'store'])->middleware('throttle:booking');

    Route::post('/admin/login', [AdminController::class, 'login'])->middleware('throttle:login');
    Route::get('/admin/session', [AdminController::class, 'session']);
    Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
        Route::post('/logout', [AdminController::class, 'logout']);
        Route::get('/appointments', [AdminController::class, 'overview']);
        Route::post('/appointments', [AdminController::class, 'manualBooking']);
        Route::patch('/appointments/{appointment}/status', [AdminController::class, 'status']);
        Route::get('/clients', [AdminController::class, 'clients']);
        Route::post('/clients', [AdminController::class, 'clientStore']);
        Route::patch('/clients/{client}', [AdminController::class, 'clientUpdate']);
        Route::get('/barbers', [AdminController::class, 'barbers']);
        Route::post('/barbers', [AdminController::class, 'barberStore']);
        Route::patch('/barbers/{barber}', [AdminController::class, 'barberUpdate']);
        Route::delete('/barbers/{barber}', [AdminController::class, 'barberDestroy']);
        Route::get('/services', [AdminController::class, 'services']);
        Route::post('/services', [AdminController::class, 'serviceStore']);
        Route::patch('/services/{service}', [AdminController::class, 'serviceUpdate']);
        Route::delete('/services/{service}', [AdminController::class, 'serviceDestroy']);
        Route::get('/schedules', [AdminController::class, 'schedules']);
        Route::put('/schedules', [AdminController::class, 'scheduleUpdate']);
        Route::post('/time-offs', [AdminController::class, 'timeOffStore']);
        Route::delete('/time-offs/{timeOff}', [AdminController::class, 'timeOffDestroy']);
    });
});
