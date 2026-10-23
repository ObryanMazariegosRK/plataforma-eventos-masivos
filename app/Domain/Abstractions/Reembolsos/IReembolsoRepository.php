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

    /**
     * Datos del pago, su reserva y su evento que necesita un reembolso.
     * @return array{pago_id: int, monto_pagado: float, pago_estado: string, referencia_cobro: ?string,
     *               pasarela: ?string, reserva_id: int, reserva_estado: string, usuario_id: int,
     *               evento_id: int, evento: string, evento_estado: string, fecha_evento: string,
     *               boletos_no_disponibles: int}|null
     */
    public function contextoDePago(int $pagoId): ?array;

    /** Igual que contextoDePago(), buscando el pago aprobado de una reserva. */
    public function contextoDeReserva(int $reservaId): ?array;

    /**
     * Al aprobar: anula los boletos emitidos de la reserva y, si $liberar, devuelve sus lugares
     * a la venta (el boleto anulado conserva qué lugar era).
     * @return array{boletos_anulados: int, lugares_liberados: int}
     */
    public function anularBoletosYLiberarLugares(int $reservaId, int $registradoPor, bool $liberar): array;

    /** @return int[] ids de los reembolsos de un evento en ese estado */
    public function idsDeEvento(int $eventoId, \App\Domain\Enums\Reembolsos\EstadoReembolso $estado): array;

    /**
     * Panel de administración: del más reciente al más antiguo.
     * @return array{reembolsos: list<array>, total: int, por_estado: array<string, int>}
     */
    public function listar(?string $estado, int $pagina, int $porPagina): array;
}
