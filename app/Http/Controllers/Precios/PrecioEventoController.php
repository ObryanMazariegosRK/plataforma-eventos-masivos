<?php

namespace App\Http\Controllers\Precios;

use App\Http\Controllers\Controller;
use App\Domain\Enums\Recintos\EstadoEvento;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PrecioEventoController extends Controller
{
    /**
     * Consultar los precios de un evento.
     *
     * Esta acción debe utilizarse únicamente
     * desde una ruta protegida con auth:sanctum.
     *
     * Los precios se obtienen directamente de MySQL.
     */
    public function index(int $id): JsonResponse
    {
        // Comprobar que el evento sea público y vigente.
        $evento = DB::table('eventos')
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
            ->whereNull('eventos.deleted_at')
            ->where('recintos.estado', 'activo')
            ->whereNull('recintos.deleted_at')
            ->select([
                'eventos.id',
                'eventos.nombre',
                'eventos.recinto_id',
                'eventos.estado',
            ])
            ->first();

        if (!$evento) {
            return response()->json([
                'message' => 'El evento no está disponible.',
            ], 404);
        }

        // Consultar precios correspondientes
        // únicamente a localidades del mismo recinto.
        $localidades = DB::table('precios')
            ->join(
                'localidades',
                'precios.localidad_id',
                '=',
                'localidades.id'
            )
            ->where('precios.evento_id', $evento->id)
            ->where(
                'localidades.recinto_id',
                $evento->recinto_id
            )
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
            ->map(function ($localidad) {
                return [
                    'id' => (int) $localidad->id,
                    'nombre' => $localidad->nombre,
                    'tipo' => $localidad->tipo,
                    'capacidad' => (int) $localidad->capacidad,
                    'color' => $localidad->color,
                    'precio' => (string) $localidad->precio,
                ];
            })
            ->values();

        return response()->json([
            'evento' => [
                'id' => (int) $evento->id,
                'nombre' => $evento->nombre,
                'estado' => $evento->estado,
            ],
            'moneda' => 'GTQ',
            'localidades' => $localidades,
        ]);
    }
}
