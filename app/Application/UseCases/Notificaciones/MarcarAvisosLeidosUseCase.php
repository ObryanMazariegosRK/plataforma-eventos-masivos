<?php

namespace App\Application\UseCases\Notificaciones;

use App\Application\Abstractions\Notificaciones\IMarcarAvisosLeidosUseCase;
use App\Domain\Abstractions\Notificaciones\IBandejaRepository;

class MarcarAvisosLeidosUseCase implements IMarcarAvisosLeidosUseCase
{
    public function __construct(private readonly IBandejaRepository $bandeja) {}

    public function execute(int $usuarioId, ?int $notificacionId): int
    {
        $this->bandeja->marcarLeidas($usuarioId, $notificacionId);

        return $this->bandeja->deUsuario($usuarioId, 1, 1)['no_leidas'];
    }
}
