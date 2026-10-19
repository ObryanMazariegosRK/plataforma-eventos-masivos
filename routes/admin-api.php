<?php

use App\Http\Controllers\Admin\AdministracionController;
use App\Http\Controllers\Admin\AdminEventoController;
use App\Http\Controllers\Admin\AdminPrecioEventoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| PULSO - API DEL PANEL ADMINISTRATIVO
|--------------------------------------------------------------------------
|
| Todas las rutas requieren autenticación Sanctum
| y permisos de administrador.
|
*/

Route::middleware(['auth:sanctum', 'rol:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        // =================================================
        // RESUMEN
        // =================================================

        Route::get('/resumen', [
            AdministracionController::class,
            'resumen'
        ])->name('resumen');

        // =================================================
        // USUARIOS
        // =================================================

        Route::get('/usuarios', [
            AdministracionController::class,
            'usuarios'
        ])->name('usuarios');

        // =================================================
        // EVENTOS
        // =================================================

        // Mantener el listado utilizado por admin.js.
        Route::get('/eventos', [
            AdministracionController::class,
            'eventos'
        ])->name('eventos');

        Route::get('/eventos/recintos', [
            AdminEventoController::class,
            'recintos'
        ])->name('eventos.recintos');

        Route::post('/eventos', [
            AdminEventoController::class,
            'store'
        ])->name('eventos.store');

        // =================================================
        // PRECIOS POR LOCALIDAD
        // =================================================

        Route::get('/eventos/{id}/precios', [
            AdminPrecioEventoController::class,
            'index'
        ])
            ->whereNumber('id')
            ->name('eventos.precios');

        Route::put('/eventos/{id}/precios', [
            AdminPrecioEventoController::class,
            'guardar'
        ])
            ->whereNumber('id')
            ->name('eventos.precios.guardar');

        // =================================================
        // CONSULTAR, EDITAR Y PUBLICAR
        // =================================================

        Route::get('/eventos/{id}', [
            AdminEventoController::class,
            'show'
        ])
            ->whereNumber('id')
            ->name('eventos.show');

        Route::put('/eventos/{id}', [
            AdminEventoController::class,
            'update'
        ])
            ->whereNumber('id')
            ->name('eventos.update');

        Route::patch('/eventos/{id}/publicar', [
            AdminEventoController::class,
            'publicar'
        ])
            ->whereNumber('id')
            ->name('eventos.publicar');

        // =================================================
        // ACTUALIZAR FOTOGRAFÍA DEL EVENTO
        // =================================================

        Route::patch('/eventos/{id}/imagen', [
            AdminEventoController::class,
            'actualizarImagen'
        ])
            ->whereNumber('id')
            ->name('eventos.imagen');

        // =================================================
        // RESERVAS
        // =================================================

        Route::get('/reservas', [
            AdministracionController::class,
            'reservas'
        ])->name('reservas');

        // =================================================
        // PAGOS
        // =================================================

        Route::get('/pagos', [
            AdministracionController::class,
            'pagos'
        ])->name('pagos');

        // =================================================
        // REPORTES
        // =================================================

        Route::get('/reportes/csv', [
            AdministracionController::class,
            'reporteCsv'
        ])->name('csv');
    });
