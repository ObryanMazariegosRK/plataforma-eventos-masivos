<?php

namespace App\Data\Catalogo;

use App\Domain\Abstractions\Catalogo\IEventoRepository;
use App\Domain\Enums\Recintos\EstadoEvento;
use App\Models\Recintos\Evento;

class EventoRepository implements IEventoRepository
{
    public function listarPublicados(
        array $filtros = []
    ): array {

        $consulta = Evento::query()
            ->join(
                'recintos',
                'eventos.recinto_id',
                '=',
                'recintos.id'
            )
            ->whereIn('eventos.estado', [
                EstadoEvento::PUBLICADO->value,
                EstadoEvento::AGOTADO->value,
            ])
            ->where('recintos.estado', 'activo')
            ->whereNull('recintos.deleted_at')
            ->where('eventos.fecha_evento', '>=', now())
            ->select([
                'eventos.id',
                'eventos.nombre',
                'eventos.descripcion',
                'eventos.fecha_evento',
                'eventos.estado',
                'recintos.nombre as recinto',
                'recintos.ciudad',
            ]);

        // Buscar por nombre
        if (!empty($filtros['nombre'])) {
            $consulta->where(
                'eventos.nombre',
                'like',
                '%' . $filtros['nombre'] . '%'
            );
        }

        // Filtrar por ciudad
        if (!empty($filtros['ciudad'])) {
            $consulta->where(
                'recintos.ciudad',
                'like',
                '%' . $filtros['ciudad'] . '%'
            );
        }

        // Filtrar por fecha
        if (!empty($filtros['fecha'])) {
            $consulta->whereDate(
                'eventos.fecha_evento',
                $filtros['fecha']
            );
        }

        return $consulta
            ->orderBy('eventos.fecha_evento')
            ->get()
            ->toArray();
    }
}
