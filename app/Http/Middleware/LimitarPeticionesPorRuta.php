<?php

namespace App\Http\Middleware;

use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * El "throttle:N,M" de Laravel usa como llave solo a la persona (o la IP), así que TODAS las rutas
 * limitadas compartían un mismo contador: alguien en la fila virtual que consulta su turno cada
 * pocos segundos (60/min) agotaba sin saberlo el de reservar (10/min) y recibía "Demasiados
 * intentos" justo cuando le tocaba. Aquí la llave incluye además el método y la ruta, para que
 * cada límite cuente solo sus propias peticiones. Se registra como alias "throttle" en bootstrap/app.php.
 */
class LimitarPeticionesPorRuta extends ThrottleRequests
{
    protected function resolveRequestSignature($request)
    {
        $ruta = $request->route()?->uri() ?? $request->path();

        return sha1(parent::resolveRequestSignature($request).'|'.$request->method().' '.$ruta);
    }
}
