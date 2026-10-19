<?php
namespace App\Domain\Abstractions\Pagos;

use App\Domain\Entities\Pagos\Pago;

interface IPagoRepository
{
    public function guardar(Pago $pago): Pago;

    public function buscarPorId(int $id): ?Pago;

    /** Igual que buscarPorId, pero bloquea la fila (SELECT ... FOR UPDATE) hasta el fin de la transacción. */
    public function buscarPorIdParaActualizar(int $id): ?Pago;

    public function contarPorReserva(int $reservaId): int;

    public function existeAprobadoParaReserva(int $reservaId): bool;
}
