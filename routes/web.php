<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Pasarelas\PasarelaSimuladaController;
use App\Http\Controllers\Inventario\ExperienciaEventoController;

/*
|--------------------------------------------------------------------------
| RUTAS WEB - SOIRÉE
|--------------------------------------------------------------------------
| Vistas públicas, autenticación, catálogo,
| administración y pasarelas de pago.
|--------------------------------------------------------------------------
*/


// ======================================================
// 1. PÁGINA PRINCIPAL
// ======================================================

Route::get('/', function () {
    return view('welcome');
});


// ======================================================
// 2. AUTENTICACIÓN
// ======================================================

Route::view('/login', 'autenticacion.login')
    ->name('login');

Route::view('/registro', 'autenticacion.registro')
    ->name('registro');

Route::view('/verificar', 'autenticacion.verificar')
    ->name('verificar');

Route::view('/perfil', 'autenticacion.perfil')
    ->name('perfil');

Route::view('/olvide-password', 'autenticacion.olvide-password')
    ->name('olvide-password');

Route::view('/restablecer-password', 'autenticacion.restablecer-password')
    ->name('restablecer-password');


// ======================================================
// 3. AUTENTICACIÓN CON GOOGLE
// ======================================================

Route::get('/auth/google/redirect', [
    GoogleAuthController::class,
    'redirect'
])->name('auth.google.redirect');

Route::get('/auth/google/callback', [
    GoogleAuthController::class,
    'callback'
])->name('auth.google.callback');

Route::view('/oauth/google', 'autenticacion.google-callback');


// ======================================================
// 4. CATÁLOGO PÚBLICO
// ======================================================

Route::view('/catalogo', 'catalogo.index')
    ->name('catalogo.index');

Route::view('/eventos/{id}', 'catalogo.detalle')
    ->whereNumber('id')
    ->name('catalogo.detalle');


// ======================================================
// 5. PANEL ADMINISTRATIVO - SOIRÉE
// ======================================================

Route::view('/admin', 'admin.index')
    ->name('admin.index');


// ======================================================
// 6. VISTAS DE PAGOS
// ======================================================

Route::view('/pagar', 'pagos.pagar')
    ->name('pagos.pagar');

Route::view('/pagos/retorno', 'pagos.retorno')
    ->name('pagos.retorno');

Route::view('/pagos/cancelado', 'pagos.cancelado')
    ->name('pagos.cancelado');

// Historial de compras del cliente (los datos vienen de GET /api/mis-compras)
Route::view('/mis-compras', 'reservas.mis-compras')
    ->name('reservas.mis-compras');

// Boletos de una compra: uno por asiento, con su QR (GET /api/reservas/{id} + /api/boletos/{id}/qr)
Route::view('/mis-compras/{reservaId}/boletos', 'reservas.boletos')
    ->whereNumber('reservaId')
    ->name('reservas.boletos');


// ======================================================
// 7. PASARELA SIMULADA
// ======================================================

// Mostrar la pasarela simulada.
Route::get('/pasarela-simulada/{orden}', [
    PasarelaSimuladaController::class,
    'mostrar'
])->name('pasarela-simulada.mostrar');

// Procesar decisión en la pasarela simulada.
Route::post('/pasarela-simulada/{orden}', [
    PasarelaSimuladaController::class,
    'decidir'
])->name('pasarela-simulada.decidir');


// ======================================================
// 8. LANDING (recorrido por scroll)
// ======================================================

Route::view('/experiencia', 'landing.experiencia')
    ->name('landing.experiencia');


// ======================================================
// 9. MAPA DE ASIENTOS
// ======================================================

// Cliente: elegir asientos (el mapa es público; para reservar se pide iniciar sesión).
Route::view('/eventos/{eventoId}/asientos', 'asientos.seleccion')
    ->whereNumber('eventoId')
    ->name('asientos.seleccion');

// Admin: ocupación en vivo (la vista comprueba el rol con la API).
Route::view('/admin/eventos/{eventoId}/asientos', 'asientos.admin')
    ->whereNumber('eventoId')
    ->name('asientos.admin');

// Experiencia del evento: recorrido por el teatro y, al final, el plano para elegir asientos.
// (El catálogo enlaza aquí desde "Ver evento".)
Route::get('/eventos/{eventoId}/experiencia', [ExperienciaEventoController::class, 'show'])
    ->whereNumber('eventoId')
    ->name('eventos.experiencia');
