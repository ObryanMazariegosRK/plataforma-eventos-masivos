<?php

namespace App\Data\Catalogo;

use App\Domain\Abstractions\Catalogo\IEventoRepository;
use App\Domain\Enums\Recintos\EstadoEvento;
use App\Models\Recintos\Evento;
use Illuminate\Support\Facades\DB;

class EventoRepository implements IEventoRepository
{
    /**
     * Lista los eventos publicados y vigentes.
     *
     * Incluye la imagen de cada evento,
     * pero no expone precios al público.
     */
    public function listarPublicados(array $filtros = [], ?int $limite = null, int $desde = 0): array
    {
        $consulta = $this->consultaPublicados($filtros)
            ->select([
                'eventos.id',
                'eventos.nombre',
                'eventos.descripcion',
                'eventos.imagen_url',
                'eventos.fecha_evento',
                'eventos.estado',
                'eventos.cola_virtual_activa',   // venta con fila virtual (la tienda lo muestra)
                'recintos.nombre as recinto',
                'recintos.ciudad',
            ])
            ->orderBy('eventos.fecha_evento')
            ->orderBy('eventos.id');   // desempate estable: la paginación no repite ni salta eventos

        if ($limite !== null) {
            $consulta->skip($desde)->take($limite);
        }

        return $consulta->get()->toArray();
    }

    public function contarPublicados(array $filtros = []): int
    {
        return $this->consultaPublicados($filtros)->count();
    }

    // Eventos publicados (o agotados) y vigentes, de recintos activos, con los filtros aplicados
    private function consultaPublicados(array $filtros): \Illuminate\Database\Eloquent\Builder
    {
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
            ->where('eventos.fecha_evento', '>=', now());

        if (!empty($filtros['nombre'])) {
            $consulta->where(
                'eventos.nombre',
                'like',
                '%' . $filtros['nombre'] . '%'
            );
        }

        if (!empty($filtros['ciudad'])) {
            $consulta->where(
                'recintos.ciudad',
                'like',
                '%' . $filtros['ciudad'] . '%'
            );
        }

        if (!empty($filtros['fecha'])) {
            $consulta->whereDate(
                'eventos.fecha_evento',
                $filtros['fecha']
            );
        }

        return $consulta;
    }

    /**
     * Obtiene el detalle público de un evento.
     *
     * Devuelve su fotografía y las localidades,
     * pero NO devuelve los precios.
     *
     * Los precios se consultan por:
     * GET /api/eventos/{id}/precios
     * con autenticación Sanctum.
     */
    public function obtenerPublicadoPorId(int $id): ?array
    {
        $evento = Evento::query()
            ->join(
                'recintos',
                'eventos.recinto_id',
                '=',
                'recintos.id'
            )
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
                'eventos.imagen_url',
                'eventos.fecha_evento',
                'eventos.estado',
                'eventos.cola_virtual_activa',   // venta con fila virtual (la tienda lo muestra)
                'recintos.nombre as recinto',
                'recintos.ciudad',
                'recintos.direccion',
            ])
            ->first();

        if (!$evento) {
            return null;
        }

        /*
         * Localidades públicas.
         *
         * No consultar la tabla precios aquí.
         */
        $localidades = DB::table('localidades')
            ->where('recinto_id', $evento->recinto_id)
            ->whereNull('deleted_at')
            ->orderBy('nombre')
            ->select([
                'id',
                'nombre',
                'tipo',
                'color',
            ])
            ->get()
            ->map(fn ($localidad) => [
                'id' => (int) $localidad->id,
                'nombre' => $localidad->nombre,
                'tipo' => $localidad->tipo,
                'color' => $localidad->color,
            ])
            ->all();

        return [
            'id' => (int) $evento->id,
            'nombre' => $evento->nombre,
            'descripcion' => $evento->descripcion,

            // Fotografía guardada en MySQL.
            'imagen_url' => $evento->imagen_url,

            'fecha_evento' =>
                $evento->fecha_evento?->toIso8601String(),

            'estado' => $evento->estado,
            'recinto' => $evento->recinto,
            'ciudad' => $evento->ciudad,
            'direccion' => $evento->direccion,

            // Localidades sin precios.
            'localidades' => $localidades,
        ];
    }
}
