<?php

namespace App\Application\UseCases\Admin\Eventos;

use App\Domain\Enums\Recintos\EstadoEvento;
use App\Models\Recintos\Evento;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CrearEventoUseCase
{
    public function ejecutar(array $datos, int $administradorId): Evento
    {
        return DB::transaction(function () use ($datos, $administradorId) {

            $recintoId = (int) $datos['recinto_id'];

            // Las localidades pertenecen al recinto seleccionado.
            $localidades = DB::table('localidades')
                ->where('recinto_id', $recintoId)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->sort()
                ->values()
                ->all();

            if (count($localidades) === 0) {
                throw ValidationException::withMessages([
                    'recinto_id' => [
                        'El recinto seleccionado no tiene localidades configuradas.'
                    ],
                ]);
            }

            $precios = $datos['precios'] ?? [];

            if (!is_array($precios)) {
                throw ValidationException::withMessages([
                    'precios' => ['Debes configurar los precios del evento.'],
                ]);
            }

            $idsEnviados = collect($precios)
                ->pluck('localidad_id')
                ->map(fn ($id) => (int) $id)
                ->sort()
                ->values()
                ->all();

            // Cada localidad debe aparecer exactamente una vez.
            if ($idsEnviados !== $localidades) {
                throw ValidationException::withMessages([
                    'precios' => [
                        'Debes asignar un precio a cada localidad del recinto, sin repetir ni agregar otras.'
                    ],
                ]);
            }

            $evento = new Evento();

            $evento->recinto_id = $recintoId;
            $evento->nombre = $datos['nombre'];
            $evento->descripcion = $datos['descripcion'] ?? null;
            // Encabeza la primera escena de la experiencia (opcional)
            $evento->frase_destacada = $datos['frase_destacada'] ?? null;

            $evento->imagen_url = $datos['imagen_url'] ?? null;
            $evento->imagen_url_2 = $datos['imagen_url_2'] ?? null;
            $evento->imagen_url_3 = $datos['imagen_url_3'] ?? null;

            $evento->fecha_evento = $datos['fecha_evento'];
            $evento->estado = EstadoEvento::BORRADOR->value;
            $evento->fecha_publicacion = null;

            $evento->created_by = $administradorId;
            $evento->updated_by = $administradorId;

            $evento->save();

            foreach ($precios as $item) {
                DB::table('precios')->insert([
                    'evento_id' => $evento->id,
                    'localidad_id' => (int) $item['localidad_id'],
                    'precio' => $item['precio'],
                    'created_by' => $administradorId,
                    'updated_by' => $administradorId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $evento->refresh();
        });
    }
}
