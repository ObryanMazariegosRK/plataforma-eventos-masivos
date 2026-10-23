<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Reservas\ReservaController;
use App\Http\Controllers\Catalogo\CatalogoController;
use App\Http\Controllers\Catalogo\DetalleEventoController;
use App\Http\Controllers\Catalogo\BannerController;
use App\Http\Controllers\Precios\PrecioEventoController;
use App\Http\Controllers\Boletos\BoletoController;
use App\Http\Controllers\Pagos\PagoController;
use App\Http\Controllers\Pasarelas\PasarelaController;
use App\Http\Controllers\Inventario\DisponibilidadController;
use App\Http\Controllers\Transacciones\TransaccionController;
use App\Http\Controllers\Notificaciones\PushController;
use App\Http\Controllers\Reembolsos\ReembolsoController;
use App\Http\Controllers\Reembolsos\ReembolsoCompraController;

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

    // Acceso del personal (admin y operador): más estricto con los intentos
    Route::post('/login-personal', [
        AuthController::class,
        'loginPersonal'
    ])->middleware('throttle:5,1');

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

// Fondo de la portada del catálogo.
Route::get('/catalogo/banner', [
    BannerController::class,
    'show'
]);


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
    ])->middleware('throttle:10,1');

    // Consultar cupo disponible por usuario.
    Route::get('/eventos/{id}/mi-cupo', [
        ReservaController::class,
        'miCupo'
    ])->whereNumber('id');

    // Reservas pagadas del usuario.
    Route::get('/mis-compras', [
        ReservaController::class,
        'misCompras'
    ]);

    // Consultar una reserva propia.
    Route::get('/reservas/{id}', [
        ReservaController::class,
        'show'
    ])->whereNumber('id');

    // Cancelar reserva.
    Route::delete('/reservas/{id}', [
        ReservaController::class,
        'destroy'
    ])
    ->whereNumber('id')
    ->middleware('throttle:10,1');

});


// ======================================================
// 4.1 NOTIFICACIONES PUSH (activarlas o quitarlas en este dispositivo)
// ======================================================

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/push/suscribir', [PushController::class, 'suscribir'])->middleware('throttle:10,1');
    Route::post('/push/desuscribir', [PushController::class, 'desuscribir'])->middleware('throttle:10,1');
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

    // QR escaneado: qué ve quien lo escanea (personal, dueño u otra persona).
    Route::post('/boletos/consultar', [
        \App\Http\Controllers\Boletos\EscaneoBoletoController::class,
        'consultar'
    ])->middleware('throttle:60,1');

    // Validar boleto.
    Route::post('/boletos/validar', [
        BoletoController::class,
        'validar'
    ])->middleware('throttle:30,1');

});


// ======================================================
// 6. PASARELAS DE PAGO
// ======================================================

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

    // Mis compras: vista previa y solicitud del reembolso de una compra propia.
    Route::get('/reservas/{id}/reembolso', [
        ReembolsoCompraController::class,
        'show'
    ])->whereNumber('id');

    Route::post('/reservas/{id}/reembolso', [
        ReembolsoCompraController::class,
        'store'
    ])
    ->whereNumber('id')
    ->middleware('throttle:10,1');

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
|--------------------------------------------------------------------------
| Rutas administrativas
|--------------------------------------------------------------------------
| Las rutas administrativas están definidas en:
|
| routes/admin-api.php
|
| Sus operaciones deben estar protegidas con
| auth:sanctum y rol:admin.
|--------------------------------------------------------------------------
*/

require __DIR__ . '/admin-api.php';


// ======================================================
// 11. INVENTARIO DE ASIENTOS
// ======================================================

// Lugares disponibles de un evento.
Route::get('/eventos/{id}/disponibilidad', [
    DisponibilidadController::class,
    'show'
])->whereNumber('id');


// ======================================================
// 12. CONFIGURACIÓN DEL PERFIL - SOIRÉE
// ======================================================

// Cerrar la sesión actual del panel administrativo.
Route::middleware('auth:sanctum')
    ->post('/auth/logout-panel', function (
        \Illuminate\Http\Request $request
    ) {
        $usuario = $request->user();
        $token = $usuario?->currentAccessToken();

        if (!$token || !method_exists($token, 'delete')) {
            return response()->json([
                'message' => 'No se pudo revocar la sesión actual.',
            ], 409);
        }

        $token->delete();

        return response()->json([
            'message' => 'Sesión cerrada correctamente.',
        ]);
    });


// Actualizar datos personales del usuario autenticado.
Route::middleware('auth:sanctum')
    ->patch('/auth/actualizar-mi-perfil', function (
        \Illuminate\Http\Request $request
    ) {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'apellido' => ['required', 'string', 'max:100'],
        ]);

        $usuarioId = $request->user()->id;

        $actualizados = \Illuminate\Support\Facades\DB::table('usuarios')
            ->where('id', $usuarioId)
            ->whereNull('deleted_at')
            ->update([
                'nombre' => trim($datos['nombre']),
                'apellido' => trim($datos['apellido']),
                'updated_by' => $usuarioId,
                'updated_at' => now(),
            ]);

        if (!$actualizados) {
            return response()->json([
                'message' => 'No fue posible actualizar tu perfil.',
            ], 404);
        }

        return response()->json([
            'message' => 'Tu perfil fue actualizado correctamente.',
        ]);
    });


// ======================================================
// 13. COLA VIRTUAL - RF-08
// ======================================================

// Ingreso de un usuario autenticado a la cola de un evento.
Route::post('/cola-virtual/ingresar', [
    \App\Http\Controllers\ColaVirtual\ColaVirtualController::class,
    'ingresar',
])->middleware([
    'auth:sanctum',
    'throttle:10,1'
]);


// ======================================================
// 14. CONSULTA DEL TURNO - RF-09
// ======================================================

// Consultar el turno del usuario para un evento.
Route::get('/cola-virtual/mi-turno/{eventoId}', [
    \App\Http\Controllers\ColaVirtual\ColaVirtualController::class,
    'miTurno',
])
    ->whereNumber('eventoId')
    ->middleware([
        'auth:sanctum',
        'throttle:60,1'
    ]);
