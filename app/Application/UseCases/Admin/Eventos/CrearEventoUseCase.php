<?php

namespace App\Application\UseCases\Admin\Eventos;

use App\Domain\Enums\Recintos\EstadoEvento;
use App\Models\Recintos\Evento;
use Illuminate\Support\Facades\DB;

class CrearEventoUseCase
{
    /**
     * Crea un evento en estado borrador.
     *
     * Los datos deben haber sido validados mediante
     * GuardarEventoRequest.
     */
    public function ejecutar(array $datos, int $administradorId): Evento
    {
        return DB::transaction(function () use ($datos, $administradorId) {

            $evento = new Evento();

            $evento->recinto_id = $datos['recinto_id'];
            $evento->nombre = $datos['nombre'];
            $evento->descripcion = $datos['descripcion'] ?? null;

            // URL HTTPS de la fotografía del evento.
            // Puede ser null cuando no se proporciona una imagen.
            $evento->imagen_url = $datos['imagen_url'] ?? null;
            $evento->imagen_url_2 = $datos['imagen_url_2'] ?? null;
            $evento->imagen_url_3 = $datos['imagen_url_3'] ?? null;

            $evento->fecha_evento = $datos['fecha_evento'];

            // Por seguridad, el evento inicia como borrador.
            $evento->estado = EstadoEvento::BORRADOR->value;
            $evento->fecha_publicacion = null;

            // Auditoría de creación.
            $evento->created_by = $administradorId;
            $evento->updated_by = $administradorId;

            $evento->save();

            return $evento;
        });
    }
}
