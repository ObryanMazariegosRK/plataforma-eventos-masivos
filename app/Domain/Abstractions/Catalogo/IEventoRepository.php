<?php

namespace App\Domain\Abstractions\Catalogo;

interface IEventoRepository
{
    /**
     * Eventos publicados y vigentes, ordenados por fecha. Con $limite devuelve solo esa
     * tanda a partir de $desde (paginación en la base: LIMIT/OFFSET); sin él, todos.
     */
    public function listarPublicados(array $filtros = [], ?int $limite = null, int $desde = 0): array;

    // Cuántos eventos cumplen los filtros (para saber si hay más páginas)
    public function contarPublicados(array $filtros = []): int;

    /**
     * Devuelve un evento público y las localidades que tienen precio
     * configurado para ese evento. Retorna null si no se puede publicar.
     */
    public function obtenerPublicadoPorId(int $id): ?array;
}
