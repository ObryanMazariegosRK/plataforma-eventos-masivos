<?php

namespace App\Application\Abstractions\Notificaciones;

interface IMarcarAvisosLeidosUseCase
{
    /** Uno ($notificacionId) o todos (null) los avisos de la persona. Devuelve cuántos no leídos le quedan. */
    public function execute(int $usuarioId, ?int $notificacionId): int;
}
