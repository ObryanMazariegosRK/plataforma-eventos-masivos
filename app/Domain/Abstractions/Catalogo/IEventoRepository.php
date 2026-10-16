<?php

namespace App\Domain\Abstractions\Catalogo;

interface IEventoRepository
{
    public function listarPublicados(array $filtros = []): array;

    /**
     * Devuelve un evento público y las localidades que tienen precio
     * configurado para ese evento. Retorna null si no se puede publicar.
     */
    public function obtenerPublicadoPorId(int $id): ?array;
}
