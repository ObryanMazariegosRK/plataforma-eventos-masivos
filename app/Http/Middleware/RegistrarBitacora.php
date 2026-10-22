<?php

namespace App\Http\Middleware;

use App\Application\Abstractions\Bitacora\IRegistrarEnBitacoraUseCase;
use App\Application\DTOs\Bitacora\RegistrarEnBitacoraDTO;
use App\Domain\Enums\Bitacora\CategoriaBitacora;
use App\Infrastructure\Bitacora\CatalogoBitacora;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

// Registra en la bitácora las acciones de CatalogoBitacora, y los accesos denegados al panel.
// Si la bitácora falla, la operación del usuario sigue normal: nunca se interrumpe una compra por esto.
class RegistrarBitacora
{
    public function __construct(
        private CatalogoBitacora $catalogo,
        private IRegistrarEnBitacoraUseCase $registrar,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! config('bitacora.habilitada')) {
            return $next($request);
        }

        $definicion = rescue(fn () => $this->catalogo->buscar($request), null, false);

        // Cómo estaba el registro antes del cambio (se lee antes de ejecutar la acción)
        $antes = isset($definicion['antes'])
            ? rescue(fn () => ($definicion['antes'])($request), null, false)
            : null;

        $response = $next($request);

        rescue(function () use ($request, $response, $definicion, $antes) {
            if ($this->accesoDenegadoAlPanel($request, $response)) {
                $this->registrarAccesoDenegado($request);
            } elseif ($definicion && ($this->exito($definicion, $response) || ! empty($definicion['registrarFallos']))) {
                $this->registrarAccion($request, $response, $definicion, $antes);
            }
        });

        return $response;
    }

    private function registrarAccion(Request $request, Response $response, array $definicion, ?array $antes): void
    {
        $json = $this->json($response);
        $exito = $this->exito($definicion, $response);
        $usuario = $this->usuario($request, $response);
        $id = isset($definicion['id'])
            ? ($definicion['id'])($request, $json)
            : ($request->route('id') ?? $json['data']['id'] ?? $json['id'] ?? null);

        $error = $exito ? null : ($json['message'] ?? $json['mensaje'] ?? null);
        $codigo = $exito ? null : ($json['error'] ?? ($response->getStatusCode() === 429 ? 'demasiados_intentos' : null));
        $accion = $definicion['accion'].(! $exito && str_starts_with($definicion['accion'], 'sesion.inicio') ? '_fallido' : '');

        $this->registrar->execute(new RegistrarEnBitacoraDTO(
            categoria: $definicion['categoria'],
            accion: $accion,
            exito: $exito,
            descripcion: ($definicion['describir'])($request, $antes, $id === null ? null : (string) $id, $exito, $error, $codigo),
            usuarioId: $usuario?->id,
            usuarioNombre: $usuario ? trim("{$usuario->nombre} {$usuario->apellido}") : null,
            // Sin sesión (ej. contraseña incorrecta) se guarda el correo que se intentó
            usuarioCorreo: $usuario->email ?? $request->input('email'),
            usuarioRol: $usuario?->rol?->value,
            entidad: $definicion['entidad'],
            entidadId: $id !== null ? (string) $id : ($definicion['entidad'] === 'usuario' && $usuario ? (string) $usuario->id : null),
            datosAntes: $antes,
            datosDespues: $request->only($definicion['campos']) ?: null,
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        ));
    }

    // Un operador (o un cliente) que intenta usar una función del panel que no le corresponde
    private function accesoDenegadoAlPanel(Request $request, Response $response): bool
    {
        return $response->getStatusCode() === Response::HTTP_FORBIDDEN
            && $request->is('api/admin/*')
            && $request->user() !== null;
    }

    private function registrarAccesoDenegado(Request $request): void
    {
        $usuario = $request->user();

        $this->registrar->execute(new RegistrarEnBitacoraDTO(
            categoria: CategoriaBitacora::SESION,
            accion: 'sesion.acceso_denegado',
            exito: false,
            descripcion: "Intentó usar una función del panel sin permiso ({$request->method()} /{$request->path()})",
            usuarioId: $usuario->id,
            usuarioNombre: trim("{$usuario->nombre} {$usuario->apellido}"),
            usuarioCorreo: $usuario->email,
            usuarioRol: $usuario->rol?->value,
            ip: $request->ip(),
            userAgent: $request->userAgent(),
        ));
    }

    private function exito(array $definicion, Response $response): bool
    {
        // El login con Google siempre redirige: tuvo éxito solo si la redirección trae el token
        if ($definicion['accion'] === 'sesion.inicio_google') {
            return str_contains((string) $response->headers->get('Location'), '#token=');
        }

        return $response->isSuccessful();
    }

    // Quién hizo la acción: el usuario autenticado o, al iniciar sesión, la cuenta que entró
    private function usuario(Request $request, Response $response): ?object
    {
        if ($usuario = $request->user()) {
            return $usuario;
        }

        // Login con correo o verificación: la respuesta trae el token recién creado
        $token = $this->json($response)['token'] ?? null;
        // Login con Google: el token viaja en el fragmento de la redirección
        if (! $token && preg_match('/#token=([^&]+)/', (string) $response->headers->get('Location'), $m)) {
            $token = urldecode($m[1]);
        }

        return $token ? PersonalAccessToken::findToken($token)?->tokenable : null;
    }

    private function json(Response $response): array
    {
        $tipo = (string) $response->headers->get('Content-Type');

        return str_contains($tipo, 'json')
            ? (json_decode((string) $response->getContent(), true) ?: [])
            : [];
    }
}
