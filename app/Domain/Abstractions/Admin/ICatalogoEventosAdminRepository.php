<?php
namespace App\Domain\Abstractions\Admin;

interface ICatalogoEventosAdminRepository
{
    /**
     * Eventos de cualquier estado para el catálogo del panel, con su recinto y ocupación
     * (lugares vendidos / total). Filtros: nombre, estado, recinto_id, desde, hasta (Y-m-d,
     * sobre la fecha del evento). Ordenados del más próximo al más lejano.
     */
    public function buscar(array $filtros, int $limite, int $desde): array;

    public function contar(array $filtros): int;
}
