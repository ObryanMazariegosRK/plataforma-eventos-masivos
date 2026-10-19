
<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Pasarelas\PasarelaSimuladaController;

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
