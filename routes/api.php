
<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Reservas\ReservaController;
use App\Http\Controllers\Catalogo\CatalogoController;
use App\Http\Controllers\Catalogo\DetalleEventoController;
use App\Http\Controllers\Precios\PrecioEventoController;
use App\Http\Controllers\Boletos\BoletoController;
use App\Http\Controllers\Pagos\PagoController;
use App\Http\Controllers\Pasarelas\PasarelaController;
use App\Http\Controllers\Transacciones\TransaccionController;
use App\Http\Controllers\Reembolsos\ReembolsoController;

/*
|--------------------------------------------------------------------------
| API - SOIRÉE
|--------------------------------------------------------------------------
| Plataforma de Eventos Masivos
|
| Autenticación, catálogo, reservas, boletos, precios,
| pagos, pasarelas, transacciones, reembolsos y administración.
|--------------------------------------------------------------------------
*/


// ======================================================
// 1. AUTENTICACIÓN
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


    // Acciones de usuarios autenticados.

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
// 2. CATÁLOGO PÚBLICO
// ======================================================

Route::get('/eventos', [
    CatalogoController::class,
    'index'
]);

Route::get('/eventos/{id}', [
    DetalleEventoController::class,
    'show'
])->whereNumber('id');


// ======================================================
// 3. PRECIOS POR LOCALIDAD
// ======================================================

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/eventos/{id}/precios', [
        PrecioEventoController::class,
        'index'
    ])
    ->whereNumber('id')
    ->name('eventos.precios');

});


// ======================================================
// 4. RESERVAS
// ======================================================

Route::middleware('auth:sanctum')->group(function () {

    // Crear reserva.
    Route::post('/reservas', [
        ReservaController::class,
        'store'
    ]);

    // Cancelar reserva.
    Route::delete('/reservas/{id}', [
        ReservaController::class,
        'destroy'
    ])
    ->whereNumber('id')
    ->middleware('throttle:10,1');

});


// ======================================================
// 5. BOLETOS
// ======================================================

Route::middleware('auth:sanctum')->group(function () {

    // Obtener QR del boleto.
    Route::get('/boletos/{id}/qr', [
        BoletoController::class,
        'qr'
    ])->whereNumber('id');

    // Validar boleto.
    Route::post('/boletos/validar', [
        BoletoController::class,
        'validar'
    ])->middleware('throttle:30,1');

});


// ======================================================
// 6. PASARELAS DE PAGO
// ======================================================

// Consultar pasarelas disponibles.
Route::get('/pasarelas', [
    PasarelaController::class,
    'index'
]);


// ======================================================
// 7. PAGOS
// ======================================================

Route::middleware('auth:sanctum')->group(function () {

    // Iniciar pago.
    Route::post('/pagos', [
        PagoController::class,
        'iniciar'
    ])->middleware('throttle:10,1');

    // Confirmar pago.
    Route::post('/pagos/{id}/confirmar', [
        PagoController::class,
        'confirmar'
    ])
    ->whereNumber('id')
    ->middleware('throttle:20,1');

    // Consultar pago.
    Route::get('/pagos/{id}', [
        PagoController::class,
        'mostrar'
    ])->whereNumber('id');

});


// ======================================================
// 8. TRANSACCIONES E HISTORIAL
// ======================================================

Route::middleware('auth:sanctum')->group(function () {

    Route::get('/transacciones', [
        TransaccionController::class,
        'index'
    ]);

    Route::get('/historial-operaciones', [
        TransaccionController::class,
        'operaciones'
    ]);

});


// ======================================================
// 9. REEMBOLSOS
// ======================================================

Route::middleware('auth:sanctum')->group(function () {

    // Solicitar reembolso.
    Route::post('/reembolsos', [
        ReembolsoController::class,
        'store'
    ])->middleware('throttle:10,1');

    // Resolver reembolso.
    Route::patch('/reembolsos/{id}/resolver', [
        ReembolsoController::class,
        'resolver'
    ])
    ->whereNumber('id')
    ->middleware('throttle:10,1');

    // Cancelar evento y procesar reembolsos.
    Route::post('/eventos/{id}/cancelar', [
        ReembolsoController::class,
        'cancelarEvento'
    ])
    ->whereNumber('id')
    ->middleware('throttle:5,1');

});


// ======================================================
// 10. ADMINISTRACIÓN - SOIRÉE
// ======================================================

/*
| Las rutas administrativas se encuentran en:
|
| routes/admin-api.php
|
| Sus operaciones deben estar protegidas mediante
| auth:sanctum y rol:admin.
|
*/

require __DIR__ . '/admin-api.php';
