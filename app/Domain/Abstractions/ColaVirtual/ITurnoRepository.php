<?php

namespace App\Domain\Abstractions\ColaVirtual;

use App\Domain\Entities\ColaVirtual\Turno;

interface ITurnoRepository
{
    /**
     * Busca el turno de un usuario dentro de un evento.
     */
    public function buscarPorEventoYUsuario(
        int $eventoId,
        int $usuarioId
    ): ?Turno;

    /**
     * Obtiene el turno existente o crea uno nuevo.
     *
     * La implementación debe evitar duplicados
     * y asignar posiciones de forma segura
     * ante solicitudes concurrentes.
     */
    public function obtenerOCrearTurno(
        int $eventoId,
        int $usuarioId
    ): Turno;
}
