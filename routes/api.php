<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Reservas\ReservaController;
use App\Http\Controllers\Catalogo\CatalogoController;
use App\Http\Controllers\Catalogo\DetalleEventoController;
use Illuminate\Support\Facades\Route;

// ---- Auth ----
// throttle:N,M = máximo N peticiones cada M minutos por IP
Route::prefix('auth')->group(function () {
    Route::post('/registro', [AuthController::class, 'registro']);
    Route::post('/verificar-correo', [AuthController::class, 'verificarCorreo'])->middleware('throttle:10,1');
    Route::post('/reenviar-codigo', [AuthController::class, 'reenviarCodigo'])->middleware('throttle:3,1');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/olvide-password', [AuthController::class, 'solicitarRecuperacion'])->middleware('throttle:3,1');
    Route::post('/restablecer-password', [AuthController::class, 'restablecerPassword'])->middleware('throttle:10,1');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/logout-todos', [AuthController::class, 'logoutTodos']);
        Route::get('/perfil', [AuthController::class, 'perfil']);
        Route::post('/cambiar-password', [AuthController::class, 'cambiarPassword'])->middleware('throttle:5,1');
    });
});

// ---- Reservas ----
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/reservas', [ReservaController::class, 'store']);
});

// ---- Catálogo de eventos públicos ----
Route::get('/eventos', [CatalogoController::class, 'index']);
Route::get('/eventos/{id}', [DetalleEventoController::class, 'show'])
    ->whereNumber('id');
