<?php

namespace App\Domain\Abstractions\Notificaciones;

use App\Domain\Entities\Notificaciones\Notificacion;

interface INotificacionRepository
{
    public function guardar(
        Notificacion $notificacion,
        ?int $registradoPor = null
    ): Notificacion;

    public function buscarPorId(int $id): ?Notificacion;

    /** El push de esta notificación ya salió (no se repite si el correo se reintenta). */
    public function marcarPushEnviado(int $id): void;

    /** Si ya salió su push. */
    public function pushEnviado(int $id): bool;

    public function guardarUnica(
        Notificacion $notificacion,
        string $claveIdempotencia,
        ?int $registradoPor = null
    ): Notificacion;
}

