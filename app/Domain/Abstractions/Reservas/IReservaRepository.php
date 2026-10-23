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

    /**
     * Reservas pagadas (confirmadas) del usuario, con el mismo formato que detalle() más
     * 'pagado_en' y 'referencia' del pago aprobado. Sin orden particular.
     */
    public function comprasDeUsuario(int $usuarioId): array;

    /**
     * Lugares que la persona ya tiene en el evento: de reservas pagadas o apartadas que aún
     * no vencen. Con $bloquear, primero bloquea la fila del usuario (FOR UPDATE) para que dos
     * solicitudes suyas al mismo tiempo se atiendan de una en una.
     */
    public function lugaresDePersonaEnEvento(int $usuarioId, int $eventoId, \DateTimeImmutable $ahora, bool $bloquear = false): int;

    /**
     * Reservas de la persona en el evento que siguen apartadas sin pagar (activas y sin vencer):
     * [{reserva_id, lugares, total, expira_en (UTC 'Y-m-d H:i:s')}], la que vence primero arriba.
     */
    public function apartadasSinPagar(int $usuarioId, int $eventoId, \DateTimeImmutable $ahora): array;
}
