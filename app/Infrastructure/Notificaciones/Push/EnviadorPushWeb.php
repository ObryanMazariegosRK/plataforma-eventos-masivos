<?php

namespace App\Infrastructure\Notificaciones\Push;

use App\Domain\Abstractions\Notificaciones\IEnviadorPush;
use App\Models\Auth\Usuario;

// Web Push (laravel-notification-channels/webpush, llaves VAPID en .env). Las suscripciones que el
// servicio de push da por muertas (dispositivo desinstalado, permiso retirado) las borra la librería.
class EnviadorPushWeb implements IEnviadorPush
{
    public function enviar(int $usuarioId, string $titulo, string $cuerpo, ?string $url): int
    {
        $usuario = Usuario::query()->find($usuarioId);
        $dispositivos = $usuario?->pushSubscriptions()->count() ?? 0;

        if ($dispositivos === 0 || ! config('webpush.vapid.public_key')) {
            return 0;
        }

        $usuario->notifyNow(new AvisoPush($titulo, $cuerpo, $url));

        return $dispositivos;
    }
}
