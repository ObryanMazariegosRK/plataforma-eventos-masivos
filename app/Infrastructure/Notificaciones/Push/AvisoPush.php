<?php

namespace App\Infrastructure\Notificaciones\Push;

use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

// Un aviso de Soirée en la pantalla del dispositivo (public/sw.js lo muestra y abre su url)
class AvisoPush extends Notification
{
    public function __construct(
        private readonly string $titulo,
        private readonly string $cuerpo,
        private readonly ?string $url,
    ) {}

    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->titulo)
            ->body($this->cuerpo)
            ->icon('/img/notificacion.png')
            ->badge('/img/notificacion-insignia.png')
            ->data(['url' => $this->url ?: '/']);
    }
}
