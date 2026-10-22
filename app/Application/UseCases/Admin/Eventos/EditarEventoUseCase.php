<?php

namespace App\Application\UseCases\Admin\Eventos;

use App\Domain\Enums\Recintos\EstadoEvento;
use App\Models\Recintos\Evento;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EditarEventoUseCase
{
    /**
     * Actualiza los datos de un evento en borrador.
     *
     * No modifica eventos publicados, agotados,
     * finalizados o cancelados.
     */
    public function ejecutar(
        int $eventoId,
        array $datos,
        int $administradorId
    ): Evento {
        return DB::transaction(function () use (
            $eventoId,
            $datos,
            $administradorId
        ) {

            // Bloquear el registro durante la actualización.
            $evento = Evento::query()
                ->lockForUpdate()
                ->findOrFail($eventoId);

            // Conservar la restricción de edición de borradores.
            if ($evento->estado !== EstadoEvento::BORRADOR->value) {
                throw ValidationException::withMessages([
                    'estado' => [
                        'Por ahora solo se pueden editar eventos en borrador.'
                    ]
                ]);
            }

            // Actualizar información general.
            $evento->nombre = $datos['nombre'];

            $evento->descripcion =
                $datos['descripcion'] ?? null;

            $evento->recinto_id = $datos['recinto_id'];

            $evento->fecha_evento = $datos['fecha_evento'];

            // Actualizar fotografía del evento.
            // El campo es opcional y admite null.
            $evento->imagen_url =
                $datos['imagen_url'] ?? null;

            $evento->imagen_url_2 =
                $datos['imagen_url_2'] ?? null;

            $evento->imagen_url_3 =
                $datos['imagen_url_3'] ?? null;

            // Auditoría.
            $evento->updated_by = $administradorId;

            $evento->save();

            return $evento->refresh();
        });
    }
}
