<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Enums\Recintos\EstadoEvento;
use App\Http\Controllers\Controller;
use App\Models\Recintos\Evento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AdminPrecioEventoController extends Controller
{
    /**
     * Consultar localidades de un recinto activo.
     *
     * Permite mostrar los precios antes de crear un evento.
     *
     * GET /api/admin/eventos/recintos/{recintoId}/localidades
     */
    public function localidadesPorRecinto(int $recintoId): JsonResponse
    {
        $recinto = DB::table('recintos')
            ->where('id', $recintoId)
            ->where('estado', 'activo')
            ->whereNull('deleted_at')
            ->first([
                'id',
                'nombre',
                'ciudad',
            ]);

        if ($recinto === null) {
            return response()->json([
                'message' => 'El recinto no existe o no está activo.',
            ], 404);
        }

        $localidades = DB::table('localidades')
            ->where('recinto_id', $recintoId)
            ->whereNull('deleted_at')
            ->orderBy('nombre')
            ->get([
                'id',
                'nombre',
                'tipo',
                'capacidad',
                'color',
            ])
            ->map(fn ($localidad) => [
                'id' => (int) $localidad->id,
                'nombre' => $localidad->nombre,
                'tipo' => $localidad->tipo,
                'capacidad' => (int) $localidad->capacidad,
                'color' => $localidad->color,
            ]);

        return response()->json([
            'recinto' => [
                'id' => (int) $recinto->id,
                'nombre' => $recinto->nombre,
                'ciudad' => $recinto->ciudad,
            ],
            'moneda' => 'GTQ',
            'localidades' => $localidades,
        ]);
    }

    /**
     * Consultar las localidades de un evento existente
     * y los precios que ya tiene configurados.
     *
     * GET /api/admin/eventos/{id}/precios
     */
    public function index(int $id): JsonResponse
    {
        $evento = Evento::query()->findOrFail($id);

        $localidades = DB::table('localidades')
            ->leftJoin('precios', function ($join) use ($id) {
                $join->on(
                    'precios.localidad_id',
                    '=',
                    'localidades.id'
                )
                ->where('precios.evento_id', '=', $id)
                ->whereNull('precios.deleted_at');
            })
            ->where('localidades.recinto_id', $evento->recinto_id)
            ->whereNull('localidades.deleted_at')
            ->orderBy('localidades.nombre')
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
                'id' => (int) $localidad->id,
                'nombre' => $localidad->nombre,
                'tipo' => $localidad->tipo,
                'capacidad' => (int) $localidad->capacidad,
                'color' => $localidad->color,
                'precio' => $localidad->precio === null
                    ? null
                    : (string) $localidad->precio,
            ]);

        return response()->json([
            'evento' => [
                'id' => (int) $evento->id,
                'nombre' => $evento->nombre,
                'estado' => $evento->estado,
                'recinto_id' => (int) $evento->recinto_id,
            ],
            'moneda' => 'GTQ',
            'editable' => $evento->estado ===
                EstadoEvento::BORRADOR->value,
            'localidades' => $localidades,
        ]);
    }

    /**
     * Guardar precios de un evento existente en borrador.
     *
     * PUT /api/admin/eventos/{id}/precios
     */
    public function guardar(
        Request $request,
        int $id
    ): JsonResponse {
        $datos = $request->validate([
            'precios' => [
                'required',
                'array',
                'min:1',
            ],
            'precios.*.localidad_id' => [
                'required',
                'integer',
                'distinct',
            ],
            'precios.*.precio' => [
                'required',
                'numeric',
                'gt:0',
                'decimal:0,2',
                'max:99999999.99',
            ],
        ], [
            'precios.required' =>
                'Debes proporcionar al menos un precio.',
            'precios.*.localidad_id.required' =>
                'Debes seleccionar una localidad.',
            'precios.*.localidad_id.distinct' =>
                'No puedes repetir una localidad.',
            'precios.*.precio.required' =>
                'Todas las localidades enviadas necesitan un precio.',
            'precios.*.precio.gt' =>
                'El precio debe ser mayor que cero.',
            'precios.*.precio.decimal' =>
                'El precio puede tener como máximo dos decimales.',
        ]);

        DB::transaction(function () use ($id, $datos, $request) {
            $evento = Evento::query()
                ->lockForUpdate()
                ->findOrFail($id);

            // Solo se pueden modificar eventos en borrador.
            if ($evento->estado !== EstadoEvento::BORRADOR->value) {
                throw ValidationException::withMessages([
                    'evento' => [
                        'Por ahora solo puedes modificar precios ' .
                        'de eventos en borrador.',
                    ],
                ]);
            }

            // No modificar precios si ya existen reservas.
            if (
                Schema::hasTable('reservas') &&
                Schema::hasColumn('reservas', 'evento_id')
            ) {
                $tieneReservas = DB::table('reservas')
                    ->where('evento_id', $evento->id)
                    ->exists();

                if ($tieneReservas) {
                    throw ValidationException::withMessages([
                        'evento' => [
                            'Este evento tiene reservas registradas. ' .
                            'No se pueden modificar sus tarifas.',
                        ],
                    ]);
                }
            }

            // Obtener identificadores enviados.
            $localidadIds = collect($datos['precios'])
                ->pluck('localidad_id')
                ->map(fn ($valor) => (int) $valor)
                ->all();

            // Comprobar que pertenecen al recinto.
            $validas = DB::table('localidades')
                ->where('recinto_id', $evento->recinto_id)
                ->whereNull('deleted_at')
                ->whereIn('id', $localidadIds)
                ->count();

            if ($validas !== count($localidadIds)) {
                throw ValidationException::withMessages([
                    'precios' => [
                        'Una o más localidades no pertenecen ' .
                        'al recinto de este evento.',
                    ],
                ]);
            }

            $administradorId = (int) $request->user()->id;

            foreach ($datos['precios'] as $item) {
                $localidadId = (int) $item['localidad_id'];

                // Incluir registros eliminados lógicamente
                // para respetar la restricción UNIQUE.
                $existente = DB::table('precios')
                    ->where('evento_id', $evento->id)
                    ->where('localidad_id', $localidadId)
                    ->lockForUpdate()
                    ->first();

                if ($existente) {
                    DB::table('precios')
                        ->where('id', $existente->id)
                        ->update([
                            'precio' => $item['precio'],
                            'deleted_at' => null,
                            'updated_by' => $administradorId,
                            'updated_at' => now(),
                        ]);
                } else {
                    DB::table('precios')->insert([
                        'evento_id' => $evento->id,
                        'localidad_id' => $localidadId,
                        'precio' => $item['precio'],
                        'created_by' => $administradorId,
                        'updated_by' => $administradorId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });

        return response()->json([
            'message' =>
                'Precios por localidad guardados correctamente.',
        ]);
    }
}
