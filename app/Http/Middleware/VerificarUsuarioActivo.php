<?php
namespace App\Http\Middleware;

use App\Domain\Enums\Auth\EstadoUsuario;
use App\Domain\Exceptions\Auth\UsuarioBloqueadoException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Corre en TODAS las rutas /api (registrado en bootstrap/app.php),
 * así nadie puede olvidarse de ponerlo en una ruta nueva.
 *
 * Si la petición trae un token válido de un usuario BLOQUEADO, se rechaza con 403
 * aunque el token aún no haya vencido. Sin token, no hace nada.
 */
class VerificarUsuarioActivo
{
    public function handle(Request $request, Closure $next): Response
    {
        // user('sanctum') resuelve el token por su cuenta, sin depender de que
        // auth:sanctum ya se haya ejecutado en esta ruta.
        if ($request->user('sanctum')?->estado === EstadoUsuario::BLOQUEADO) {
            throw new UsuarioBloqueadoException();
        }

        return $next($request);
    }
}
