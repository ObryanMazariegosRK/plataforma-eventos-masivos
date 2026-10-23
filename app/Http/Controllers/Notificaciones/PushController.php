<?php

namespace App\Http\Controllers\Notificaciones;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// Activar / desactivar las notificaciones push en el dispositivo actual (public/js/notificaciones.js).
// Mismo criterio que en la tienda: el servidor le hace POST al endpoint, así que solo se aceptan
// los servicios de push de los navegadores.
class PushController extends Controller
{
    private const SERVICIOS_PUSH = [
        'fcm.googleapis.com',          // Chrome, Edge, Opera, Brave
        'push.services.mozilla.com',   // Firefox
        'push.apple.com',              // Safari
        'notify.windows.com',          // Windows
    ];

    public function suscribir(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'endpoint' => ['required', 'string', 'max:500', 'url:https', function ($atributo, $valor, $fallar) {
                if (! $this->esServicioPush($valor)) {
                    $fallar('El servicio de notificaciones de este navegador no es válido.');
                }
            }],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
        ]);

        $request->user()->updatePushSubscription($datos['endpoint'], $datos['keys']['p256dh'], $datos['keys']['auth']);

        return response()->json(['message' => 'Notificaciones activadas en este dispositivo.'], 201);
    }

    public function desuscribir(Request $request): JsonResponse
    {
        $datos = $request->validate(['endpoint' => ['required', 'string', 'max:500']]);
        $request->user()->deletePushSubscription($datos['endpoint']);

        return response()->json(['message' => 'Notificaciones desactivadas en este dispositivo.']);
    }

    private function esServicioPush(string $endpoint): bool
    {
        $host = strtolower((string) parse_url($endpoint, PHP_URL_HOST));
        foreach (self::SERVICIOS_PUSH as $servicio) {
            if ($host === $servicio || str_ends_with($host, '.'.$servicio)) {
                return true;
            }
        }

        return false;
    }
}
