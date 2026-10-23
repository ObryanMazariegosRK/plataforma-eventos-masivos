<?php

namespace App\Http\Controllers\Admin;

use App\Application\Abstractions\Finanzas\IGuardarCostosEventoUseCase;
use App\Application\DTOs\Finanzas\GuardarCostosEventoDTO;
use App\Application\UseCases\Admin\Eventos\CrearEventoUseCase;
use App\Application\UseCases\Admin\Eventos\EditarEventoUseCase;
use App\Application\UseCases\Admin\Eventos\PublicarEventoUseCase;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Finanzas\CostoEventoController;
use App\Http\Requests\Admin\GuardarEventoRequest;
use App\Models\Recintos\Evento;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminEventoController extends Controller
{
    /**
     * Lista todos los eventos del panel administrativo.
     */
    public function index(Request $request): JsonResponse
    {
        $eventos = Evento::query()
            ->leftJoin(
                'recintos',
                'eventos.recinto_id',
                '=',
                'recintos.id'
            )
            ->select([
                'eventos.id',
                'eventos.recinto_id',
                'eventos.nombre',
                'eventos.descripcion',
                'eventos.imagen_url',
                'eventos.fecha_evento',
                'eventos.estado',
                'eventos.fecha_publicacion',
                'recintos.nombre as recinto',
                'recintos.ciudad',
            ])
            ->orderByDesc('eventos.created_at')
            ->paginate(10);

        return response()->json($eventos);
    }

    /**
     * Lista recintos activos.
     */
    public function recintos(): JsonResponse
    {
        $recintos = DB::table('recintos')
            ->where('estado', 'activo')
            ->whereNull('deleted_at')
            ->orderBy('nombre')
            ->get([
                'id',
                'nombre',
                'ciudad',
            ]);

        return response()->json([
            'data' => $recintos,
        ]);
    }

    /**
     * Obtiene la información de un evento.
     */
    public function show(int $id): JsonResponse
    {
        $evento = Evento::findOrFail($id);

        return response()->json([
            'data' => $evento,
        ]);
    }

    /**
     * Crea un evento como borrador.
     */
    public function store(
        GuardarEventoRequest $request,
        CrearEventoUseCase $crearEvento,
        IGuardarCostosEventoUseCase $guardarCostos
    ): JsonResponse {
        $precios = $request->validate([
            'precios' => ['required', 'array', 'min:1'],
            'precios.*.localidad_id' => [
                'required', 'integer', 'distinct',
            ],
            'precios.*.precio' => [
                'required',
                'numeric',
                'gt:0',
                'decimal:0,2',
                'max:99999999.99',
            ],
        ]);

        // Costos de producción (opcionales): se validan antes de crear nada
        $costos = $request->validate(
            ['costos' => ['required', 'array', 'min:1']] + CostoEventoController::reglas(),
            ['costos.required' => 'Registra los costos del evento: al menos el alquiler del recinto y los artistas.'],
        )['costos'];

        $datos = array_merge(
            $request->validated(),
            $precios
        );

        $evento = DB::transaction(function () use ($crearEvento, $guardarCostos, $datos, $costos, $request) {
            $evento = $crearEvento->ejecutar(
                $datos,
                (int) $request->user()->id
            );

            $guardarCostos->execute(new GuardarCostosEventoDTO(
                eventoId: (int) $evento->id,
                usuarioId: (int) $request->user()->id,
                costos: $costos,
                maximo: config('finanzas.max_costos_por_evento'),
            ));

            return $evento;
        });

        return response()->json([
            'message' =>
                'Evento y precios guardados correctamente como borrador.',
            'data' => $evento,
        ], 201);
    }

    /**
     * Edita únicamente eventos en borrador.
     */
    public function update(
        GuardarEventoRequest $request,
        int $id,
        EditarEventoUseCase $editarEvento
    ): JsonResponse {
        $evento = $editarEvento->ejecutar(
            $id,
            $request->validated(),
            (int) $request->user()->id
        );

        return response()->json([
            'message' => 'Evento actualizado correctamente.',
            'data' => $evento,
        ]);
    }

    /**
     * Publica un evento.
     */
    public function publicar(
        Request $request,
        int $id,
        PublicarEventoUseCase $publicarEvento
    ): JsonResponse {
        $evento = $publicarEvento->ejecutar(
            $id,
            (int) $request->user()->id
        );

        return response()->json([
            'message' => 'Evento publicado correctamente.',
            'data' => $evento,
        ]);
    }

    /**
     * Modificar contenido informativo de un evento publicado.
     *
     * No modifica fecha, recinto, precios ni inventario.
     * La ruta es exclusiva del Administrador.
     */
    public function actualizarContenidoPublicado(
        Request $request,
        int $id
    ): JsonResponse {
        $datos = $request->validate([
            'nombre' => [
                'required', 'string', 'min:3', 'max:255',
            ],
            'descripcion' => [
                'nullable', 'string', 'max:10000',
            ],
            'imagen_url' => ['required', 'string', new \App\Rules\ImagenDeEvento],
            'imagen_url_2' => ['required', 'string', new \App\Rules\ImagenDeEvento],
            'imagen_url_3' => ['required', 'string', new \App\Rules\ImagenDeEvento],
        ]);

        $evento = DB::transaction(function () use ($request, $id, $datos) {
            $evento = Evento::query()
                ->lockForUpdate()
                ->findOrFail($id);

            if ($evento->estado !== 'publicado') {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'estado' => [
                        'Esta operación solo admite eventos publicados.',
                    ],
                ]);
            }

            $evento->nombre = $datos['nombre'];
            $evento->descripcion = $datos['descripcion'] ?? null;
            $evento->imagen_url = $datos['imagen_url'] ?? null;
            $evento->imagen_url_2 = $datos['imagen_url_2'] ?? null;
            $evento->imagen_url_3 = $datos['imagen_url_3'] ?? null;

            $evento->updated_by = (int) $request->user()->id;
            $evento->save();

            return $evento->refresh();
        });

        return response()->json([
            'message' => 'Información y fotografías actualizadas correctamente.',
            'data' => $evento,
        ]);
    }

    /**
     * Cambia únicamente la fotografía.
     *
     * Se permite actualizar la imagen de eventos
     * publicados sin modificar fecha, recinto,
     * precios, reservas o boletos.
     */
    public function actualizarImagen(
        Request $request,
        int $id
    ): JsonResponse {
        $datos = $request->validate([
            'imagen_url' => [
                'present',
                'nullable',
                'string',
                new \App\Rules\ImagenDeEvento,
            ],
        ], [
            'imagen_url.present' =>
                'Debes enviar el campo imagen_url.',

            'imagen_url.url' =>
                'La dirección de la imagen no es válida.',

            'imagen_url.starts_with' =>
                'La imagen debe utilizar HTTPS.',

            'imagen_url.max' =>
                'La URL de la imagen no puede superar 2048 caracteres.',
        ]);

        $evento = DB::transaction(function () use (
            $id,
            $datos,
            $request
        ) {
            $evento = Evento::query()
                ->lockForUpdate()
                ->findOrFail($id);

            $evento->imagen_url = $datos['imagen_url'];

            $evento->updated_by =
                (int) $request->user()->id;

            $evento->save();

            return $evento->refresh();
        });

        return response()->json([
            'message' =>
                'Fotografía actualizada correctamente.',

            'data' => [
                'id' => (int) $evento->id,
                'nombre' => $evento->nombre,
                'imagen_url' => $evento->imagen_url,
            ],
        ]);
    }

    /**
     * Elimina lógicamente un evento en borrador,
     * siempre que no tenga registros relacionados.
     */
    public function destroy(
        Request $request,
        int $id
    ): JsonResponse {

        DB::transaction(function () use ($request, $id) {

            $evento = Evento::query()
                ->lockForUpdate()
                ->findOrFail($id);

            // Solo el estado borrador admite eliminación.
            if ($evento->estado !== 'borrador') {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'evento' => [
                        'Solo se pueden eliminar eventos en borrador.'
                    ],
                ]);
            }

            // Se revisan también las reservas eliminadas
            // lógicamente para conservar el historial.
            $tieneReservas = DB::table('reservas')
                ->where('evento_id', $evento->id)
                ->exists();

            if ($tieneReservas) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'evento' => [
                        'Este evento tiene reservas registradas. No puede eliminarse.'
                    ],
                ]);
            }

            // El inventario puede relacionarse con boletos
            // mediante inventario_id. Por seguridad,
            // no se elimina un evento que ya tenga inventario.
            $tieneInventario = DB::table('inventario')
                ->where('evento_id', $evento->id)
                ->exists();

            if ($tieneInventario) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'evento' => [
                        'Este evento tiene inventario generado. '
                        . 'Debe revisarse antes de eliminarlo.'
                    ],
                ]);
            }

            $usuarioId = (int) $request->user()->id;

            // Retirar lógicamente los precios del evento.
            DB::table('precios')
                ->where('evento_id', $evento->id)
                ->whereNull('deleted_at')
                ->update([
                    'deleted_at' => now(),
                    'updated_at' => now(),
                    'updated_by' => $usuarioId,
                ]);

            // Soft Delete: no se elimina físicamente.
            $evento->updated_by = $usuarioId;
            $evento->save();
            $evento->delete();
        });

        return response()->json([
            'message' =>
                'Evento eliminado lógicamente correctamente.',
        ]);
    }

}
