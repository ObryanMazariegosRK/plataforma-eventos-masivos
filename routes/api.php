<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Reservas\ReservaController;
use App\Http\Controllers\Catalogo\CatalogoController;
use App\Http\Controllers\Catalogo\DetalleEventoController;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Boletos\BoletoController;


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

        Route::delete('/reservas/{id}', [ReservaController::class, 'destroy'])
            ->whereNumber('id')
            ->middleware('throttle:10,1');
    });



    // ---- Boletos ----
    Route::middleware('auth:sanctum')->group(function () {

        // Consultar el QR del boleto.
        Route::get('/boletos/{id}/qr', [BoletoController::class, 'qr'])
            ->whereNumber('id');

        // Validar un boleto en el acceso al evento.
        Route::post('/boletos/validar', [BoletoController::class, 'validar'])
            ->middleware('throttle:30,1');

    });




// ---- Transacciones e Historial ----
Route::middleware('auth:sanctum')->group(function () {

    Route::get(
        '/transacciones',
        [
            \App\Http\Controllers\Transacciones\TransaccionController::class,
            'index'
        ]
    );

});


// ---- Historial de Operaciones ----
Route::middleware('auth:sanctum')->get(
    '/historial-operaciones',
    [
        \App\Http\Controllers\Transacciones\TransaccionController::class,
        'operaciones'
    ]
);



// ---- Reembolsos ----
Route::middleware('auth:sanctum')->group(function () {

    // Cliente: solicitar un reembolso.
    Route::post(
        '/reembolsos',
        [
            \App\Http\Controllers\Reembolsos\ReembolsoController::class,
            'store'
        ]
    )->middleware('throttle:10,1');

    // Administrador: aprobar o rechazar.
    Route::patch(
        '/reembolsos/{id}/resolver',
        [
            \App\Http\Controllers\Reembolsos\ReembolsoController::class,
            'resolver'
        ]
    )->whereNumber('id')
     ->middleware('throttle:10,1');

    // RF-20: Administrador cancela un evento completo.
    Route::post(
        '/eventos/{id}/cancelar',
        [
            \App\Http\Controllers\Reembolsos\ReembolsoController::class,
            'cancelarEvento'
        ]
    )->whereNumber('id')
    ->middleware('throttle:5,1');

});

// ---- Catálogo de eventos públicos ----
Route::get('/eventos', [CatalogoController::class, 'index']);
Route::get('/eventos/{id}', [DetalleEventoController::class, 'show'])
    ->whereNumber('id');
