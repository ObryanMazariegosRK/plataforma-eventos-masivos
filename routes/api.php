<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Reservas\ReservaController;
use App\Http\Controllers\Catalogo\CatalogoController;
use App\Http\Controllers\Catalogo\DetalleEventoController;
use App\Http\Controllers\Precios\PrecioEventoController;
use App\Http\Controllers\Boletos\BoletoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API DE PULSO - PLATAFORMA DE EVENTOS MASIVOS
|--------------------------------------------------------------------------
|
| Rutas de autenticación, reservas, boletos, transacciones,
| reembolsos, catálogo, precios y administración.
|
*/

// ======================================================
// AUTENTICACIÓN
// ======================================================

Route::prefix('auth')->group(function () {

    Route::post('/registro', [
        AuthController::class,
        'registro'
    ]);

    Route::post('/verificar-correo', [
        AuthController::class,
        'verificarCorreo'
    ])->middleware('throttle:10,1');

    Route::post('/reenviar-codigo', [
        AuthController::class,
        'reenviarCodigo'
    ])->middleware('throttle:3,1');

    Route::post('/login', [
        AuthController::class,
        'login'
    ])->middleware('throttle:10,1');

    Route::post('/olvide-password', [
        AuthController::class,
        'solicitarRecuperacion'
    ])->middleware('throttle:3,1');

    Route::post('/restablecer-password', [
        AuthController::class,
        'restablecerPassword'
    ])->middleware('throttle:10,1');

    // Acciones para usuarios autenticados.
    Route::middleware('auth:sanctum')->group(function () {

        Route::post('/logout', [
            AuthController::class,
            'logout'
        ]);

        Route::post('/logout-todos', [
            AuthController::class,
            'logoutTodos'
        ]);

        Route::get('/perfil', [
            AuthController::class,
            'perfil'
        ]);

        Route::post('/cambiar-password', [
            AuthController::class,
            'cambiarPassword'
        ])->middleware('throttle:5,1');

    });

});

// ======================================================
// RESERVAS
// ======================================================

Route::middleware('auth:sanctum')->group(function () {

    Route::post('/reservas', [
        ReservaController::class,
        'store'
    ]);

});

// ======================================================
// BOLETOS
// ======================================================

Route::middleware('auth:sanctum')->group(function () {

    // Consultar código QR de un boleto.
    Route::get('/boletos/{id}/qr', [
        BoletoController::class,
        'qr'
    ])->whereNumber('id');

    // Validar boleto en el acceso al evento.
    Route::post('/boletos/validar', [
        BoletoController::class,
        'validar'
    ])->middleware('throttle:30,1');

});

// ======================================================
// TRANSACCIONES E HISTORIAL
// ======================================================

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/transacciones', [
        \App\Http\Controllers\Transacciones\TransaccionController::class,
        'index'
    ]);

    Route::get('/historial-operaciones', [
        \App\Http\Controllers\Transacciones\TransaccionController::class,
        'operaciones'
    ]);

});

// ======================================================
// REEMBOLSOS
// ======================================================

Route::middleware('auth:sanctum')->group(function () {

    // Cliente solicita reembolso.
    Route::post('/reembolsos', [
        \App\Http\Controllers\Reembolsos\ReembolsoController::class,
        'store'
    ])->middleware('throttle:10,1');

    // Administrador resuelve reembolso.
    Route::patch('/reembolsos/{id}/resolver', [
        \App\Http\Controllers\Reembolsos\ReembolsoController::class,
        'resolver'
    ])
        ->whereNumber('id')
        ->middleware('throttle:10,1');

    // Cancelación de evento con su proceso de reembolsos.
    Route::post('/eventos/{id}/cancelar', [
        \App\Http\Controllers\Reembolsos\ReembolsoController::class,
        'cancelarEvento'
    ])
        ->whereNumber('id')
        ->middleware('throttle:5,1');

});

// ======================================================
// CATÁLOGO PÚBLICO
// ======================================================

// Consultar eventos publicados.
Route::get('/eventos', [
    CatalogoController::class,
    'index'
]);

// Consultar detalles públicos de un evento.
Route::get('/eventos/{id}', [
    DetalleEventoController::class,
    'show'
])->whereNumber('id');

// ======================================================
// PRECIOS POR LOCALIDAD - REQUIERE INICIAR SESIÓN
// ======================================================

/*
|--------------------------------------------------------------------------
| GET /api/eventos/{id}/precios
|--------------------------------------------------------------------------
|
| Devuelve las localidades de un evento y sus precios
| correspondientes en quetzales.
|
| Solamente usuarios autenticados pueden consultar
| esta información mediante Laravel Sanctum.
|
| IMPORTANTE:
| Los precios también deben retirarse de las respuestas
| del catálogo público, para evitar que sean accesibles
| sin autenticación.
|
*/

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/eventos/{id}/precios', [
        PrecioEventoController::class,
        'index'
    ])
        ->whereNumber('id')
        ->name('eventos.precios');

});

// ======================================================
// ADMINISTRACIÓN DE PULSO
// ======================================================

// Incluye las rutas protegidas con auth:sanctum y rol:admin.
require __DIR__ . '/admin-api.php';
