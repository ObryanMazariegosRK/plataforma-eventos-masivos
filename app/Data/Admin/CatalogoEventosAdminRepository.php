<?php
namespace App\Data\Admin;

use App\Domain\Abstractions\Admin\ICatalogoEventosAdminRepository;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CatalogoEventosAdminRepository implements ICatalogoEventosAdminRepository
{
    public function buscar(array $filtros, int $limite, int $desde): array
    {
        // Ocupación por evento en una sola consulta agregada (no una por tarjeta)
        $ocupacion = DB::table('inventario')
            ->whereNull('deleted_at')
            ->select('evento_id', DB::raw('COUNT(*) as total'), DB::raw("SUM(CASE WHEN estado = 'vendido' THEN 1 ELSE 0 END) as vendidos"))
            ->groupBy('evento_id');

        return $this->consulta($filtros)
            ->leftJoinSub($ocupacion, 'ocupacion', 'ocupacion.evento_id', '=', 'eventos.id')
            ->select(
                'eventos.id', 'eventos.nombre', 'eventos.fecha_evento', 'eventos.estado', 'eventos.imagen_url',
                'recintos.nombre as recinto', 'recintos.ciudad',
                DB::raw('COALESCE(ocupacion.total, 0) as lugares'),
                DB::raw('COALESCE(ocupacion.vendidos, 0) as vendidos'),
            )
            // Primero los próximos (el más cercano arriba), al final los que ya pasaron
            ->orderByRaw('CASE WHEN eventos.fecha_evento >= ? THEN 0 ELSE 1 END', [now()])
            ->orderBy('eventos.fecha_evento')
            ->orderBy('eventos.id')
            ->skip($desde)
            ->take($limite)
            ->get()
            ->map(fn ($e) => [
                'id'           => (int) $e->id,
                'nombre'       => $e->nombre,
                // ISO con zona: el navegador la muestra en hora de Guatemala
                'fecha_evento' => $e->fecha_evento ? Carbon::parse($e->fecha_evento)->toIso8601String() : null,
                'estado'       => $e->estado,
                'imagen_url'   => $e->imagen_url,
                'recinto'      => $e->recinto,
                'ciudad'       => $e->ciudad,
                'lugares'      => (int) $e->lugares,
                'vendidos'     => (int) $e->vendidos,
            ])
            ->all();
    }

    public function contar(array $filtros): int
    {
        return $this->consulta($filtros)->count();
    }

    public function contarPorEstado(): array
    {
        return DB::table('eventos')->whereNull('deleted_at')
            ->select('estado')->selectRaw('COUNT(*) as total')->groupBy('estado')
            ->pluck('total', 'estado')
            ->map(fn ($total) => (int) $total)
            ->all();
    }

    private function consulta(array $filtros): Builder
    {
        $consulta = DB::table('eventos')
            ->leftJoin('recintos', 'recintos.id', '=', 'eventos.recinto_id')
            ->whereNull('eventos.deleted_at');

        if (!empty($filtros['nombre'])) {
            $consulta->where('eventos.nombre', 'like', '%'.$filtros['nombre'].'%');
        }
        if (!empty($filtros['estado'])) {
            $consulta->where('eventos.estado', $filtros['estado']);
        }
        if (!empty($filtros['recinto_id'])) {
            $consulta->where('eventos.recinto_id', $filtros['recinto_id']);
        }
        if (!empty($filtros['desde'])) {
            $consulta->whereDate('eventos.fecha_evento', '>=', $filtros['desde']);
        }
        if (!empty($filtros['hasta'])) {
            $consulta->whereDate('eventos.fecha_evento', '<=', $filtros['hasta']);
        }

        return $consulta;
    }
}
