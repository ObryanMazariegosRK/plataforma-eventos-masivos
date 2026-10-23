<?php

namespace App\Domain\Abstractions\Finanzas;

use App\Domain\Entities\Finanzas\CostoEvento;

interface ICostoEventoRepository
{
    public function existeEvento(int $eventoId): bool;

    /** @return CostoEvento[] */
    public function deEvento(int $eventoId): array;

    /**
     * Deja exactamente esta lista como los costos del evento (los anteriores quedan
     * eliminados con borrado lógico, para conservar el historial).
     *
     * @param  CostoEvento[]  $costos
     */
    public function reemplazar(int $eventoId, array $costos, int $usuarioId): void;
}
