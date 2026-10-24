<?php

namespace App\Application\Abstractions\ColaVirtual;

interface IProcesarColaVirtualUseCase
{
    /**
     * Procesa los turnos de todos los eventos
     * publicados que tienen Cola Virtual activa,
     * o solo los del evento indicado (avance inmediato
     * desde la sala de espera).
     *
     * @return array{expirados: int, habilitados: int}
     */
    public function execute(?int $eventoId = null): array;
}
