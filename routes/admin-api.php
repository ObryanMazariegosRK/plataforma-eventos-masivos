<?php

use App\Http\Controllers\Admin\AdminCatalogoEventosController;
use App\Http\Controllers\Admin\AdministracionController;
use App\Http\Controllers\Admin\AdminBannerController;
use App\Http\Controllers\Admin\AdminEventoController;
use App\Http\Controllers\Admin\AdminPrecioEventoController;
use App\Http\Controllers\Admin\AdminUsuarioController;
use App\Http\Controllers\Admin\AdminNotificacionController;
use App\Http\Controllers\Admin\AdminReporteDocumentoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| SOIRÉE - API ADMINISTRATIVA
|--------------------------------------------------------------------------
|
| Administrador:
|   Acceso completo.
|
| Organizador / Agente Operativo:
|   Eventos y Banner.
|
| Cliente:
|   Sin acceso administrativo.
|
*/

Route::middleware(['auth:sanctum'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        /*
        |--------------------------------------------------------------------------
        | ADMINISTRADOR Y AGENTE OPERATIVO
        |--------------------------------------------------------------------------
        */

        Route::middleware('rol:admin,organizador')
            ->group(function () {

                // EVENTOS

                Route::get('/eventos', [
                    AdministracionController::class,
                    'eventos'
                ])->name('eventos');

                // Catálogo de eventos: búsqueda con filtros sobre todos los eventos
                Route::get('/eventos/catalogo', [
                    AdminCatalogoEventosController::class,
                    'index'
                ])->name('eventos.catalogo');

                Route::get('/eventos/recintos', [
                    AdminEventoController::class,
                    'recintos'
                ])->name('eventos.recintos');

                // Localidades del recinto seleccionado.
                Route::get('/eventos/recintos/{recintoId}/localidades', [
                    AdminPrecioEventoController::class,
                    'localidadesPorRecinto'
                ])
                    ->whereNumber('recintoId')
                    ->name('eventos.recintos.localidades');

                Route::post('/eventos', [
                    AdminEventoController::class,
                    'store'
                ])->name('eventos.store');

                // PRECIOS POR LOCALIDAD

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

                // CONSULTAR EVENTO

                Route::get('/eventos/{id}', [
                    AdminEventoController::class,
                    'show'
                ])
                    ->whereNumber('id')
                    ->name('eventos.show');

                // EDITAR EVENTO

                Route::put('/eventos/{id}', [
                    AdminEventoController::class,
                    'update'
                ])
                    ->whereNumber('id')
                    ->name('eventos.update');

                // PUBLICAR EVENTO

                Route::patch('/eventos/{id}/publicar', [
                    AdminEventoController::class,
                    'publicar'
                ])
                    ->whereNumber('id')
                    ->name('eventos.publicar');

                // CAMBIAR IMAGEN

                Route::patch('/eventos/{id}/imagen', [
                    AdminEventoController::class,
                    'actualizarImagen'
                ])
                    ->whereNumber('id')
                    ->name('eventos.imagen');

                // BANNER

                Route::get('/banner', [
                    AdminBannerController::class,
                    'show'
                ])->name('banner');

                Route::post('/banner', [
                    AdminBannerController::class,
                    'update'
                ])->name('banner.actualizar');

                Route::delete('/banner', [
                    AdminBannerController::class,
                    'destroy'
                ])->name('banner.eliminar');
            });

        /*
        |--------------------------------------------------------------------------
        | EXCLUSIVO DEL ADMINISTRADOR
        |--------------------------------------------------------------------------
        */

        Route::middleware('rol:admin')
            ->group(function () {

                // ELIMINAR EVENTOS
                // Exclusivo del Administrador.
                // Solo borradores sin actividad relacionada.

                Route::delete('/eventos/{id}', [
                    AdminEventoController::class,
                    'destroy'
                ])
                    ->whereNumber('id')
                    ->name('eventos.eliminar');

                // MODIFICAR CONTENIDO DE EVENTO PUBLICADO
                // Exclusivo del Administrador.

                Route::patch('/eventos/{id}/contenido', [
                    AdminEventoController::class,
                    'actualizarContenidoPublicado'
                ])
                    ->whereNumber('id')
                    ->name('eventos.contenido.actualizar');

                // ACTIVIDAD Y NOTIFICACIONES ADMINISTRATIVAS

                Route::get('/notificaciones', [
                    AdminNotificacionController::class,
                    'index'
                ])->name('notificaciones');

                // RESUMEN

                Route::get('/resumen', [
                    AdministracionController::class,
                    'resumen'
                ])->name('resumen');

                // USUARIOS

                Route::get('/usuarios', [
                    AdministracionController::class,
                    'usuarios'
                ])->name('usuarios');

                Route::post('/usuarios', [
                    AdminUsuarioController::class,
                    'crear'
                ])->name('usuarios.crear');

                Route::get('/usuarios/{usuario}', [
                    AdminUsuarioController::class,
                    'mostrar'
                ])
                    ->whereNumber('usuario')
                    ->name('usuarios.mostrar');

                Route::put('/usuarios/{usuario}', [
                    AdminUsuarioController::class,
                    'actualizar'
                ])
                    ->whereNumber('usuario')
                    ->name('usuarios.actualizar');

                Route::patch('/usuarios/{usuario}/rol', [
                    AdminUsuarioController::class,
                    'cambiarRol'
                ])
                    ->whereNumber('usuario')
                    ->name('usuarios.rol');

                Route::patch('/usuarios/{usuario}/estado', [
                    AdminUsuarioController::class,
                    'cambiarEstado'
                ])
                    ->whereNumber('usuario')
                    ->name('usuarios.estado');

                Route::delete('/usuarios/{usuario}', [
                    AdminUsuarioController::class,
                    'eliminar'
                ])
                    ->whereNumber('usuario')
                    ->name('usuarios.eliminar');

                Route::get('/reservas/{id}', [
                    AdministracionController::class,
                    'reservaDetalle'
                ])
                    ->whereNumber('id')
                    ->name('reservas.detalle');

                // RESERVAS

                Route::get('/reservas', [
                    AdministracionController::class,
                    'reservas'
                ])->name('reservas');

                Route::get('/pagos/{id}', [
                    AdministracionController::class,
                    'pagoDetalle'
                ])
                    ->whereNumber('id')
                    ->name('pagos.detalle');

                // PAGOS

                Route::get('/pagos', [
                    AdministracionController::class,
                    'pagos'
                ])->name('pagos');

                // REPORTES PROFESIONALES
                // Exclusivos del Administrador.

                Route::get('/reportes/excel', [
                    AdminReporteDocumentoController::class,
                    'excel'
                ])->name('reportes.excel');

                Route::get('/reportes/pdf', [
                    AdminReporteDocumentoController::class,
                    'pdf'
                ])->name('reportes.pdf');

                // REPORTES CSV

                Route::get('/reportes/csv', [
                    AdministracionController::class,
                    'reporteCsv'
                ])->name('csv');
            });
    });
