<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// ---- Autenticación (vistas; toda la lógica va por la API /api/auth/*) ----
Route::view('/login', 'autenticacion.login')->name('login');
Route::view('/registro', 'autenticacion.registro')->name('registro');
Route::view('/verificar', 'autenticacion.verificar')->name('verificar');
Route::view('/perfil', 'autenticacion.perfil')->name('perfil');
Route::view('/olvide-password', 'autenticacion.olvide-password')->name('olvide-password');
Route::view('/restablecer-password', 'autenticacion.restablecer-password')->name('restablecer-password');

// ---- Login con Google (OAuth) ----
Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
Route::view('/oauth/google', 'autenticacion.google-callback');   // guarda el token y entra al perfil

// ---- Catalogo de Eventos ----
// Pagina publica para consultar los eventos disponibles.
Route::view('/catalogo', 'catalogo.index')
    ->name('catalogo.index');

