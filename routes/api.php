<?php

use App\Http\Controllers\Reservas\ReservaController;
use Illuminate\Support\Facades\Route;

// Reservas (sin middleware de autenticación por ahora)
Route::post('/reservas', [ReservaController::class, 'store']);
