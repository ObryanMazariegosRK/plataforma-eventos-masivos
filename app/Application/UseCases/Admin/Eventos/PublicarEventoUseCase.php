<?php

namespace App\Application\UseCases\Admin\Eventos;

use App\Application\Abstractions\Inventario\IGenerarInventarioEventoUseCase;
use App\Domain\Enums\Recintos\EstadoEvento;
use App\Models\Recintos\Evento;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PublicarEventoUseCase
{
    public function __construct(
        // Inventario: crea los lugares a la venta del evento al publicarlo
        private IGenerarInventarioEventoUseCase $generarInventario,
    ) {}

    /**
     * Publica un evento que se encuentra en borrador.
     *
     * Los eventos publicados podrán aparecer en el
     * catálogo público si cumplen sus filtros.
     */
    public function ejecutar(
        int $eventoId,
        int $administradorId
    ): Evento {
        return DB::transaction(function () use (
            $eventoId,
            $administradorId
        ) {
            $evento = Evento::query()
                ->lockForUpdate()
                ->findOrFail($eventoId);

            if ($evento->estado !== EstadoEvento::BORRADOR->value) {
                throw ValidationException::withMessages([
                    'estado' => [
                        'Solo se pueden publicar eventos en borrador.',
                    ],
                ]);
            }

            if (!$evento->fecha_evento ||
                $evento->fecha_evento->isPast()) {
                throw ValidationException::withMessages([
                    'fecha_evento' => [
                        'La fecha del evento debe ser futura.',
                    ],
                ]);
            }

            $recintoActivo = DB::table('recintos')
                ->where('id', $evento->recinto_id)
                ->where('estado', 'activo')
                ->whereNull('deleted_at')
                ->exists();

            if (!$recintoActivo) {
                throw ValidationException::withMessages([
                    'recinto_id' => [
                        'El recinto no existe o no está activo.',
                    ],
                ]);
            }

            // Crea los lugares a la venta (exige precio en todas las localidades).
            // Va dentro de esta transacción: si falla, el evento NO se publica.
            $this->generarInventario->execute($evento->id);

            $evento->estado = EstadoEvento::PUBLICADO->value;
            $evento->fecha_publicacion = now();
            $evento->updated_by = $administradorId;

            $evento->save();

            return $evento->refresh();
        });
    }
}
