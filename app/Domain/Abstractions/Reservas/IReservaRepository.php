<?php
namespace App\Domain\Abstractions\Reservas;

use App\Domain\Entities\Reservas\Reserva;

interface IReservaRepository
{
    public function guardar(Reserva $reserva): Reserva;

    public function buscarPorId(int $id): ?Reserva;

    /** Igual que buscarPorId, pero bloquea la fila (SELECT ... FOR UPDATE) hasta el fin de la transacción. */
    public function buscarPorIdParaActualizar(int $id): ?Reserva;

    /**
     * Resumen para mostrar al comprador: la reserva con su evento y sus lugares.
     * ['id', 'usuario_id', 'estado', 'total', 'evento' => [id, nombre, fecha_evento, recinto],
     *  'lugares' => [[localidad, tipo, fila, numero]]]; null si no existe.
     */
    public function detalle(int $id): ?array;
}
