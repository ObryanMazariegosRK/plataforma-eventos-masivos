<?php

namespace App\Application\Abstractions\ColaVirtual;

interface IConsultarMiTurnoUseCase
{
    /**
     * Consulta el turno de un usuario en un evento.
     *
     * Devuelve null si el usuario no tiene turno.
     *
     * @return array{
     *     evento_id: int,
     *     posicion: int,
     *     personas_delante: int,
     *     estado: string,
     *     habilitado_hasta: ?string
     * }|null
     */
    public function execute(int $eventoId, int $usuarioId): ?array;
}
