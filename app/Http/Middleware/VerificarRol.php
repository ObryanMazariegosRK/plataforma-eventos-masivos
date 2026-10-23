<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uso en rutas (siempre DESPUÉS de auth:sanctum):
 *   ->middleware(['auth:sanctum', 'rol:admin'])
 *   ->middleware(['auth:sanctum', 'rol:admin,organizador'])
 */
class VerificarRol
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $usuario = $request->user();

        if ($usuario === null || ! in_array($usuario->rol->value, $roles, true)) {
            return response()->json(['message' => 'No tienes permisos para realizar esta acción.'], 403);
        }

        return $next($request);
    }
}
