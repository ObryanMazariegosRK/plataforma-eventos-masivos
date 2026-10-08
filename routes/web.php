<?php

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
