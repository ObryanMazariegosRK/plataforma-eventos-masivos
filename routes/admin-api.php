<?php

use App\Http\Controllers\Admin\AdminCatalogoEventosController;
use App\Http\Controllers\Admin\AdministracionController;
use App\Http\Controllers\Admin\AdminBannerController;
use App\Http\Controllers\Admin\AdminEventoController;
use App\Http\Controllers\Admin\AdminPrecioEventoController;
use App\Http\Controllers\Admin\AdminUsuarioController;
use App\Http\Controllers\Admin\AdminReporteDocumentoController;
use App\Http\Controllers\Bitacora\BitacoraController;
use App\Http\Controllers\Reembolsos\AdminReembolsoController;
use App\Http\Controllers\Finanzas\CostoEventoController;
use App\Http\Controllers\Finanzas\FinanzasController;
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

                // FOTOS DEL EVENTO (se suben al elegirlas en el formulario)

                Route::post('/eventos/imagenes', [
                    \App\Http\Controllers\Admin\AdminImagenEventoController::class,
                    'store'
                ])->name('eventos.imagenes');

                Route::post('/eventos', [
                    AdminEventoController::class,
                    'store'
                ])->name('eventos.store');

                // Plano del recinto (para elegirlo al crear un evento)
                Route::get('/eventos/recintos/{recintoId}/plano', [
                    \App\Http\Controllers\Inventario\PlanoRecintoController::class,
                    'show'
                ])
                    ->whereNumber('recintoId')
                    ->name('eventos.recintos.plano');

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

                // COSTOS DE PRODUCCIÓN (los registra también el operador; las ganancias no las ve)

                Route::get('/eventos/{id}/costos', [
                    CostoEventoController::class,
                    'index'
                ])
                    ->whereNumber('id')
                    ->name('eventos.costos');

                Route::put('/eventos/{id}/costos', [
                    CostoEventoController::class,
                    'guardar'
                ])
                    ->whereNumber('id')
                    ->name('eventos.costos.guardar');

                // Cómo va la fila virtual del evento (Ver asientos)
                Route::get('/eventos/{id}/cola', [
                    \App\Http\Controllers\ColaVirtual\ColaVirtualController::class,
                    'resumen'
                ])
                    ->whereNumber('id')
                    ->name('eventos.cola');

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

                // MODIFICAR CONTENIDO DE EVENTO PUBLICADO (incluye aplazar la fecha)
                // Administrador y operador: el operador gestiona los eventos igual que el admin.

                Route::patch('/eventos/{id}/contenido', [
                    AdminEventoController::class,
                    'actualizarContenidoPublicado'
                ])
                    ->whereNumber('id')
                    ->name('eventos.contenido.actualizar');

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

                // FINANZAS: ventas y ganancias por evento

                Route::get('/finanzas', [
                    FinanzasController::class,
                    'index'
                ])->name('finanzas');

                // BITÁCORA (solo lectura): quién hizo qué y cuándo

                Route::get('/bitacora', [
                    BitacoraController::class,
                    'index'
                ])->name('bitacora');

                // REEMBOLSOS: solicitudes y reintento de la devolución
                // (aprobar/rechazar: PATCH /api/reembolsos/{id}/resolver)

                Route::get('/reembolsos', [
                    AdminReembolsoController::class,
                    'index'
                ])->name('reembolsos');

                Route::post('/reembolsos/{id}/procesar', [
                    AdminReembolsoController::class,
                    'procesar'
                ])->whereNumber('id')->middleware('throttle:10,1')->name('reembolsos.procesar');
            });
    });
