<?php

namespace App\Domain\Abstractions\Notificaciones;

interface IEnviadorPush
{
    /**
     * Manda una notificación push a todos los dispositivos donde la persona la activó.
     * Devuelve a cuántos dispositivos se envió (0 si no activó ninguno).
     */
    public function enviar(int $usuarioId, string $titulo, string $cuerpo, ?string $url): int;
}
