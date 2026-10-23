<?php

use App\Domain\Exceptions\Auth\CodigoVerificacionInvalidoException;
use App\Domain\Exceptions\Auth\CorreoNoVerificadoException;
use App\Domain\Exceptions\Auth\CorreoYaRegistradoException;
use App\Domain\Exceptions\Auth\CorreoYaVerificadoException;
use App\Domain\Exceptions\Auth\CodigoRecuperacionInvalidoException;
use App\Domain\Exceptions\Auth\CredencialesInvalidasException;
use App\Domain\Exceptions\Auth\IntentosAgotadosException;
use App\Domain\Exceptions\Auth\PasswordActualIncorrectaException;
use App\Domain\Exceptions\Auth\PasswordNuevaIgualException;
use App\Domain\Exceptions\Auth\UsuarioBloqueadoException;
use App\Domain\Exceptions\Auth\UsuarioNoEncontradoException;
use App\Domain\Exceptions\Pagos\PagoNoEncontradoException;
use App\Domain\Exceptions\Pagos\PagoRechazadoException;
use App\Domain\Exceptions\Pagos\PasarelaNoDisponibleException;
use App\Domain\Exceptions\Pagos\ReservaNoEncontradaException;
use App\Domain\Exceptions\Pagos\ReservaNoPagableException;
use App\Domain\Exceptions\Pagos\ReservaYaPagadaException;
use App\Domain\Exceptions\Admin\ReporteDemasiadoGrandeException;
use App\Domain\Exceptions\Bitacora\BitacoraNoDisponibleException;
use App\Domain\Exceptions\Finanzas\EventoSinCostosEditablesException;
use App\Domain\Exceptions\Catalogo\ArchivoBannerInvalidoException;
use App\Domain\Exceptions\Inventario\EventoNoDisponibleException;
use App\Domain\Exceptions\Reservas\LimitePorPersonaException;
use App\Domain\Exceptions\Inventario\LocalidadesSinPrecioException;
use App\Domain\Exceptions\Inventario\RecintoSinLugaresException;
use App\Domain\Exceptions\ReglaDeNegocioException;
use App\Http\Middleware\RegistrarBitacora;
use App\Http\Middleware\VerificarRol;
use App\Http\Middleware\VerificarUsuarioActivo;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'rol' => VerificarRol::class,
        ]);

        // Detrás de un proxy (ngrok, Coolify) la visita llega por https pero el proxy la pasa por http.
        // Confiar en sus cabeceras X-Forwarded-* hace que Laravel genere los enlaces con https.
        $middleware->trustProxies(at: '*');

        // En todas las rutas /api: un usuario bloqueado pierde el acceso aunque su token siga vigente.
        $middleware->api(append: [VerificarUsuarioActivo::class, RegistrarBitacora::class]);

        // Bitácora: inicios de sesión, compras, cambios en el panel... (ver CatalogoBitacora).
        // En web solo registra el regreso del login con Google.
        $middleware->web(append: [RegistrarBitacora::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Excepción de negocio => [código HTTP, código de error para el frontend].
        // Al crear una excepción nueva, se registra aquí y los controllers no necesitan try/catch.
        $mapa = [
            CredencialesInvalidasException::class       => [401, 'credenciales_invalidas'],
            CorreoNoVerificadoException::class          => [403, 'correo_no_verificado'],
            UsuarioBloqueadoException::class            => [403, 'usuario_bloqueado'],
            UsuarioNoEncontradoException::class         => [404, 'usuario_no_encontrado'],
            CorreoYaRegistradoException::class          => [409, 'correo_ya_registrado'],
            CorreoYaVerificadoException::class          => [409, 'correo_ya_verificado'],
            CodigoVerificacionInvalidoException::class  => [422, 'codigo_invalido'],
            CodigoRecuperacionInvalidoException::class  => [422, 'codigo_recuperacion_invalido'],
            IntentosAgotadosException::class            => [422, 'intentos_agotados'],
            // 422 y no 401: un 401 haría que el frontend creyera que la sesión expiró
            PasswordActualIncorrectaException::class    => [422, 'password_actual_incorrecta'],
            PasswordNuevaIgualException::class          => [422, 'password_igual'],
            // ---- Pagos ----
            ReservaNoEncontradaException::class         => [404, 'reserva_no_encontrada'],
            PagoNoEncontradoException::class            => [404, 'pago_no_encontrado'],
            ReservaNoPagableException::class            => [409, 'reserva_no_pagable'],
            ReservaYaPagadaException::class             => [409, 'reserva_ya_pagada'],
            PasarelaNoDisponibleException::class        => [422, 'pasarela_no_disponible'],
            PagoRechazadoException::class               => [402, 'pago_rechazado'],   // 402 = Payment Required
            BitacoraNoDisponibleException::class        => [503, 'bitacora_no_disponible'],
            ReporteDemasiadoGrandeException::class      => [422, 'reporte_demasiado_grande'],
            EventoSinCostosEditablesException::class    => [404, 'evento_no_encontrado'],
            // ---- Catálogo ----
            ArchivoBannerInvalidoException::class       => [422, 'banner_archivo_invalido'],
            // ---- Reservas ----
            LimitePorPersonaException::class            => [422, 'limite_por_persona'],
            // ---- Inventario ----
            EventoNoDisponibleException::class          => [404, 'evento_no_disponible'],
            LocalidadesSinPrecioException::class        => [422, 'localidades_sin_precio'],
            RecintoSinLugaresException::class           => [422, 'recinto_sin_lugares'],
        ];

        // Callback de Google: es una redirección del navegador, no una petición JSON.
        // Cualquier error (cuenta bloqueada, Google rechazó el código...) regresa al login con un mensaje.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->routeIs('auth.google.callback')) {
                return null;
            }

            $mensaje = $e instanceof DomainException
                ? $e->getMessage()                                  // regla de negocio: mensaje para el usuario
                : 'No se pudo iniciar sesión con Google. Inténtalo de nuevo.';   // error técnico (se registra en el log)

            return redirect('/login?tipo=error&mensaje='.urlencode($mensaje));
        });

        // Página web de un evento que no está a la venta → 404 (en la API ya responde JSON 404)
        $exceptions->render(function (EventoNoDisponibleException $e, Request $request) {
            if ($request->is('api/*')) {
                return null;
            }

            // se convierte en un 404 normal de Laravel, que muestra su página de "no encontrado"
            return app(\Illuminate\Contracts\Debug\ExceptionHandler::class)
                ->render($request, new \Symfony\Component\HttpKernel\Exception\NotFoundHttpException($e->getMessage(), $e));
        });

        // Las reglas de negocio son respuestas ESPERADAS (contraseña incorrecta, código vencido...),
        // no fallos del sistema: no se escriben en storage/logs/laravel.log.
        $exceptions->dontReport([ReglaDeNegocioException::class]);

        // DomainException cubre también las validaciones de las entidades (validar()):
        // si no está en el mapa, responde 422 en lugar de un 500.
        $exceptions->render(function (DomainException $e, Request $request) use ($mapa) {
            if (! $request->is('api/*')) {
                return null;   // fuera de la API, Laravel decide
            }

            [$status, $error] = $mapa[$e::class] ?? [422, 'regla_de_negocio'];

            return response()->json(['message' => $e->getMessage(), 'error' => $error], $status);
        });
    })->create();
