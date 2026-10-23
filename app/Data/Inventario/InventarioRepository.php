<?php
namespace App\Data\Inventario;

use App\Domain\Abstractions\Inventario\IInventarioRepository;
use App\Domain\Entities\Inventario\ItemInventario;
use App\Models\Inventario\ItemInventario as ItemInventarioModel;
use Illuminate\Support\Facades\DB;

class InventarioRepository implements IInventarioRepository
{
    public function buscarPorReserva(int $reservaId, bool $bloquear = false): array
    {
        return ItemInventarioModel::where('reserva_id', $reservaId)
            ->orderBy('id')   // siempre el mismo orden: evita deadlocks entre transacciones
            ->when($bloquear, fn ($q) => $q->lockForUpdate())
            ->get()
            ->map(fn (ItemInventarioModel $m) => $this->aEntidad($m))
            ->all();
    }

    public function guardar(ItemInventario $item): ItemInventario
    {
        $m = $item->getId() ? ItemInventarioModel::findOrFail($item->getId()) : new ItemInventarioModel();
        $m->evento_id       = $item->getEventoId();
        $m->localidad_id    = $item->getLocalidadId();
        $m->asiento_id      = $item->getAsientoId();
        $m->estado          = $item->getEstado();
        $m->reserva_id      = $item->getReservaId();
        $m->bloqueado_hasta = $item->getBloqueadoHasta();
        $m->save();

        return $this->aEntidad($m);
    }

    // ---- Generación ----

    public function existeParaEvento(int $eventoId): bool
    {
        return ItemInventarioModel::withTrashed()->where('evento_id', $eventoId)->exists();
    }

    public function localidadesSinPrecio(int $eventoId): array
    {
        return DB::table('localidades')
            ->join('eventos', 'eventos.recinto_id', '=', 'localidades.recinto_id')
            ->leftJoin('precios', function ($join) use ($eventoId) {
                $join->on('precios.localidad_id', '=', 'localidades.id')
                    ->where('precios.evento_id', '=', $eventoId)
                    ->whereNull('precios.deleted_at');
            })
            ->where('eventos.id', $eventoId)
            ->whereNull('localidades.deleted_at')
            ->where(fn ($q) => $q->whereNull('precios.precio')->orWhere('precios.precio', '<=', 0))
            ->orderBy('localidades.nombre')
            ->pluck('localidades.nombre')
            ->all();
    }

    public function generarParaEvento(int $eventoId): int
    {
        $recintoId = DB::table('eventos')->where('id', $eventoId)->value('recinto_id');
        $localidades = DB::table('localidades')
            ->where('recinto_id', $recintoId)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get(['id', 'tipo', 'capacidad']);

        $ahora = now();
        $fila = fn (int $localidadId, ?int $asientoId) => [
            'evento_id'    => $eventoId,
            'localidad_id' => $localidadId,
            'asiento_id'   => $asientoId,
            'estado'       => 'disponible',
            'created_at'   => $ahora,
            'updated_at'   => $ahora,
        ];

        $filas = [];
        foreach ($localidades as $localidad) {
            if ($localidad->tipo === 'numerada') {
                // un lugar por asiento físico
                $asientos = DB::table('asientos')
                    ->where('localidad_id', $localidad->id)
                    ->whereNull('deleted_at')
                    ->orderBy('fila')->orderBy('numero')
                    ->pluck('id');
                foreach ($asientos as $asientoId) {
                    $filas[] = $fila($localidad->id, $asientoId);
                }
            } else {
                // localidad general: tantos cupos como su capacidad, sin asiento
                for ($i = 0; $i < (int) $localidad->capacidad; $i++) {
                    $filas[] = $fila($localidad->id, null);
                }
            }
        }

        foreach (array_chunk($filas, 500) as $lote) {
            DB::table('inventario')->insert($lote);
        }

        return count($filas);
    }

    // ---- Consulta ----

    public function disponibilidad(int $eventoId): ?array
    {
        $evento = DB::table('eventos')
            ->join('recintos', 'recintos.id', '=', 'eventos.recinto_id')
            ->where('eventos.id', $eventoId)
            ->whereNull('eventos.deleted_at')
            ->whereIn('eventos.estado', ['publicado', 'agotado'])
            ->first(['eventos.id', 'eventos.nombre', 'eventos.fecha_evento', 'recintos.nombre as recinto',
                'eventos.imagen_url', 'eventos.imagen_url_2', 'eventos.imagen_url_3']);

        if ($evento === null) {
            return null;
        }

        $lugares = DB::table('inventario')
            ->join('localidades', 'localidades.id', '=', 'inventario.localidad_id')
            ->leftJoin('asientos', 'asientos.id', '=', 'inventario.asiento_id')
            ->leftJoin('precios', function ($join) use ($eventoId) {
                $join->on('precios.localidad_id', '=', 'inventario.localidad_id')
                    ->where('precios.evento_id', '=', $eventoId)
                    ->whereNull('precios.deleted_at');
            })
            ->where('inventario.evento_id', $eventoId)
            ->whereNull('inventario.deleted_at')
            ->orderBy('localidades.id')->orderBy('asientos.fila')->orderBy('asientos.numero')->orderBy('inventario.id')
            ->get([
                'inventario.id', 'inventario.estado', 'localidades.id as localidad_id', 'localidades.nombre',
                'localidades.tipo', 'localidades.color', 'localidades.capacidad', 'precios.precio',
                'asientos.fila', 'asientos.numero',
            ]);

        $localidades = [];
        $resumen = ['total' => 0, 'disponibles' => 0, 'bloqueados' => 0, 'vendidos' => 0];
        $contador = ['disponible' => 'disponibles', 'bloqueado' => 'bloqueados', 'vendido' => 'vendidos'];

        foreach ($lugares as $lugar) {
            $id = (int) $lugar->localidad_id;
            $localidades[$id] ??= [
                'id'          => $id,
                'nombre'      => $lugar->nombre,
                'tipo'        => $lugar->tipo,
                'color'       => $lugar->color,
                'precio'      => $lugar->precio === null ? null : (float) $lugar->precio,
                'capacidad'   => (int) $lugar->capacidad,
                'disponibles' => 0,
                'bloqueados'  => 0,
                'vendidos'    => 0,
                'asientos'    => [],
            ];

            $clave = $contador[$lugar->estado] ?? null;
            if ($clave !== null) {
                $localidades[$id][$clave]++;
                $resumen[$clave]++;
            }
            $resumen['total']++;

            if ($lugar->tipo === 'numerada') {
                $localidades[$id]['asientos'][] = [
                    'inventario_id' => (int) $lugar->id,
                    'fila'          => $lugar->fila,
                    'numero'        => (int) $lugar->numero,
                    'estado'        => $lugar->estado,
                ];
            }
        }

        return [
            'evento' => [
                'id'           => (int) $evento->id,
                'nombre'       => $evento->nombre,
                'fecha_evento' => $evento->fecha_evento,
                'recinto'      => $evento->recinto,
                // Las 3 fotos del evento, en orden (portada y escenas de la experiencia)
                'imagenes'     => [$evento->imagen_url, $evento->imagen_url_2, $evento->imagen_url_3],
            ],
            'localidades' => array_values($localidades),
            'resumen'     => $resumen,
        ];
    }

    public function planoRecinto(int $recintoId): ?array
    {
        $recinto = DB::table('recintos')->where('id', $recintoId)->where('estado', 'activo')->whereNull('deleted_at')
            ->first(['id', 'nombre']);
        if ($recinto === null) {
            return null;
        }

        $localidades = [];
        $total = 0;
        $filas = DB::table('localidades')
            ->leftJoin('asientos', function ($join) {
                $join->on('asientos.localidad_id', '=', 'localidades.id')->whereNull('asientos.deleted_at');
            })
            ->where('localidades.recinto_id', $recintoId)
            ->whereNull('localidades.deleted_at')
            ->orderBy('localidades.id')->orderBy('asientos.fila')->orderBy('asientos.numero')
            ->get(['localidades.id as localidad_id', 'localidades.nombre', 'localidades.tipo', 'localidades.color',
                'localidades.capacidad', 'asientos.id as asiento_id', 'asientos.fila', 'asientos.numero']);

        foreach ($filas as $fila) {
            $id = (int) $fila->localidad_id;
            $localidades[$id] ??= [
                'id' => $id, 'nombre' => $fila->nombre, 'tipo' => $fila->tipo, 'color' => $fila->color,
                'precio' => null, 'capacidad' => (int) $fila->capacidad,
                'disponibles' => $fila->tipo === 'numerada' ? 0 : (int) $fila->capacidad,
                'bloqueados' => 0, 'vendidos' => 0, 'asientos' => [],
            ];
            if ($fila->tipo === 'numerada' && $fila->asiento_id !== null) {
                // Sin inventario todavía: el id del asiento identifica la butaca en el dibujo
                $localidades[$id]['asientos'][] = [
                    'inventario_id' => (int) $fila->asiento_id, 'fila' => $fila->fila,
                    'numero' => (int) $fila->numero, 'estado' => 'disponible',
                ];
                $localidades[$id]['disponibles']++;
            }
        }
        foreach ($localidades as $localidad) {
            $total += $localidad['disponibles'];
        }

        return [
            'recinto' => ['id' => (int) $recinto->id, 'nombre' => $recinto->nombre],
            'localidades' => array_values($localidades),
            'resumen' => ['total' => $total, 'disponibles' => $total, 'bloqueados' => 0, 'vendidos' => 0],
        ];
    }

    private function aEntidad(ItemInventarioModel $m): ItemInventario
    {
        return new ItemInventario(
            id: $m->id,
            eventoId: $m->evento_id,
            localidadId: $m->localidad_id,
            asientoId: $m->asiento_id,
            estado: $m->estado,
            reservaId: $m->reserva_id,
            bloqueadoHasta: $m->bloqueado_hasta?->toDateTimeImmutable(),
        );
    }
}
