<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Reservas\ReservaController;
use Illuminate\Support\Facades\Route;

// ---- Auth ----
// throttle:N,M = máximo N peticiones cada M minutos por IP (si se excede → 429)
Route::prefix('auth')->group(function () {
    Route::post('/registro', [AuthController::class, 'registro']);
    // además del throttle, el código se destruye al 5.º intento fallido
    Route::post('/verificar-correo', [AuthController::class, 'verificarCorreo'])->middleware('throttle:10,1');
    // evita abusar del envío de correos
    Route::post('/reenviar-codigo', [AuthController::class, 'reenviarCodigo'])->middleware('throttle:3,1');
    // frena ataques de fuerza bruta a contraseñas
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    Route::post('/olvide-password', [AuthController::class, 'solicitarRecuperacion'])->middleware('throttle:3,1');
    Route::post('/restablecer-password', [AuthController::class, 'restablecerPassword'])->middleware('throttle:10,1');

    // requieren el header  Authorization: Bearer <token>
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/logout-todos', [AuthController::class, 'logoutTodos']);
        Route::get('/perfil', [AuthController::class, 'perfil']);
        // throttle: con un token robado no se puede adivinar la contraseña actual a fuerza bruta
        Route::post('/cambiar-password', [AuthController::class, 'cambiarPassword'])->middleware('throttle:5,1');
    });
});

// ---- Reservas ----
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/reservas', [ReservaController::class, 'store']);
});


// ---- Catalogo de Eventos ----

Route::get('/eventos', [
    \App\Http\Controllers\Catalogo\CatalogoController::class,
    'index'
]);

