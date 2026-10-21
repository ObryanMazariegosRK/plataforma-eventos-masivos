<?php

namespace App\Application\UseCases\ColaVirtual;

use App\Application\Abstractions\ColaVirtual\IIngresarColaVirtualUseCase;
use App\Domain\Abstractions\ColaVirtual\ITurnoRepository;
use App\Domain\Entities\ColaVirtual\Turno;
use Illuminate\Support\Facades\DB;
use DomainException;

class IngresarColaVirtualUseCase implements IIngresarColaVirtualUseCase
{
    public function __construct(
        private ITurnoRepository $turnos
    ) {}

    public function execute(
        int $eventoId,
        int $usuarioId
    ): Turno {

        // 1. Validar los identificadores.
        if ($eventoId <= 0 || $usuarioId <= 0) {
            throw new DomainException(
                'Los identificadores del evento y usuario no son válidos.'
            );
        }

        // 2. Buscar el evento.
        $evento = DB::table('eventos')
            ->where('id', $eventoId)
            ->whereNull('deleted_at')
            ->first();

        if (!$evento) {
            throw new DomainException('El evento no existe.');
        }

        // 3. Comprobar que esté publicado.
        if ($evento->estado !== 'publicado') {
            throw new DomainException(
                'El evento no está disponible para ingresar a la cola.'
            );
        }

        // 4. Comprobar que requiera Cola Virtual.
        if (!(bool) $evento->cola_virtual_activa) {
            throw new DomainException(
                'La Cola Virtual no está activada para este evento.'
            );
        }

        // 5. Obtener el turno existente o crear uno nuevo.
        return $this->turnos->obtenerOCrearTurno(
            $eventoId,
            $usuarioId
        );
    }
}
