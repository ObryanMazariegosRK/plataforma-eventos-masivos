<?php

namespace App\Data\Catalogo;

use App\Domain\Abstractions\Catalogo\IEventoRepository;
use App\Domain\Enums\Recintos\EstadoEvento;
use App\Models\Recintos\Evento;
use Illuminate\Support\Facades\DB;

class EventoRepository implements IEventoRepository
{
    public function listarPublicados(array $filtros = []): array
    {
        $consulta = Evento::query()
            ->join('recintos', 'eventos.recinto_id', '=', 'recintos.id')
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

        if (!empty($filtros['nombre'])) {
            $consulta->where('eventos.nombre', 'like', '%' . $filtros['nombre'] . '%');
        }

        if (!empty($filtros['ciudad'])) {
            $consulta->where('recintos.ciudad', 'like', '%' . $filtros['ciudad'] . '%');
        }

        if (!empty($filtros['fecha'])) {
            $consulta->whereDate('eventos.fecha_evento', $filtros['fecha']);
        }

        return $consulta
            ->orderBy('eventos.fecha_evento')
            ->get()
            ->toArray();
    }

    public function obtenerPublicadoPorId(int $id): ?array
    {
        // La consulta utiliza el modelo con SoftDeletes: eventos eliminados
        // no aparecen. Se exige el mismo criterio del listado público.
        $evento = Evento::query()
            ->join('recintos', 'eventos.recinto_id', '=', 'recintos.id')
            ->where('eventos.id', $id)
            ->whereIn('eventos.estado', [
                EstadoEvento::PUBLICADO->value,
                EstadoEvento::AGOTADO->value,
            ])
            ->where('eventos.fecha_evento', '>=', now())
            ->where('recintos.estado', 'activo')
            ->whereNull('recintos.deleted_at')
            ->select([
                'eventos.id',
                'eventos.recinto_id',
                'eventos.nombre',
                'eventos.descripcion',
                'eventos.fecha_evento',
                'eventos.estado',
                'recintos.nombre as recinto',
                'recintos.ciudad',
                'recintos.direccion',
            ])
            ->first();

        if (!$evento) {
            return null;
        }

        // Un precio solo puede mostrarse si pertenece a una localidad
        // del mismo recinto del evento. Se respetan los SoftDeletes.
        $localidades = DB::table('precios')
            ->join('localidades', 'precios.localidad_id', '=', 'localidades.id')
            ->where('precios.evento_id', $evento->id)
            ->where('localidades.recinto_id', $evento->recinto_id)
            ->whereNull('precios.deleted_at')
            ->whereNull('localidades.deleted_at')
            ->orderBy('precios.precio')
            ->select([
                'localidades.id',
                'localidades.nombre',
                'localidades.tipo',
                'localidades.capacidad',
                'localidades.color',
                'precios.precio',
            ])
            ->get()
            ->map(fn ($localidad) => [
                'id' => $localidad->id,
                'nombre' => $localidad->nombre,
                'tipo' => $localidad->tipo,
                // Capacidad física configurada, NO cupos disponibles.
                'capacidad' => (int) $localidad->capacidad,
                'color' => $localidad->color,
                'precio' => (string) $localidad->precio,
            ])
            ->all();

        return [
            'id' => $evento->id,
            'nombre' => $evento->nombre,
            'descripcion' => $evento->descripcion,
            'fecha_evento' => $evento->fecha_evento?->toIso8601String(),
            'estado' => $evento->estado,
            'recinto' => $evento->recinto,
            'ciudad' => $evento->ciudad,
            'direccion' => $evento->direccion,
            // Campo preparado para la API de administración: cuando
            // se implemente, se podrá consultar desde este repositorio.
            'imagen_url' => null,
            'localidades' => $localidades,
        ];
    }
}
