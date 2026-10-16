<?php

namespace App\Domain\Abstractions\Reembolsos;

use App\Domain\Entities\Reembolsos\Reembolso;

interface IReembolsoRepository
{
    public function guardar(
        Reembolso $reembolso,
        ?int $registradoPor = null
    ): Reembolso;

    public function buscarPorId(int $id): ?Reembolso;

    /** @return Reembolso[] */
    public function buscarPorPago(int $pagoId): array;
}
