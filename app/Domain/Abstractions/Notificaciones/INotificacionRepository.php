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

    public function guardarUnica(
        Notificacion $notificacion,
        string $claveIdempotencia,
        ?int $registradoPor = null
    ): Notificacion;
}

