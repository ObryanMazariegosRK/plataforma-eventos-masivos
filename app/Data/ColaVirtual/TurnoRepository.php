<?php

namespace App\Data\ColaVirtual;

use App\Domain\Abstractions\ColaVirtual\ITurnoRepository;
use App\Domain\Entities\ColaVirtual\Turno;
use App\Domain\Enums\ColaVirtual\EstadoTurno;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use DomainException;

class TurnoRepository implements ITurnoRepository
{
    public function buscarPorEventoYUsuario(
        int $eventoId,
        int $usuarioId
    ): ?Turno {
        $registro = DB::table('cola_virtual')
            ->where('evento_id', $eventoId)
            ->where('usuario_id', $usuarioId)
            ->whereNull('deleted_at')
            ->first();

        return $registro ? $this->aEntidad($registro) : null;
    }

    public function obtenerOCrearTurno(
        int $eventoId,
        int $usuarioId
    ): Turno {
        return DB::transaction(function () use ($eventoId, $usuarioId) {

            // Serializamos las entradas a la cola de este evento.
            $evento = DB::table('eventos')
                ->where('id', $eventoId)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            if (!$evento) {
                throw new DomainException('El evento no existe.');
            }

            // Comprobamos si el usuario ya tiene un turno.
            $existente = DB::table('cola_virtual')
                ->where('evento_id', $eventoId)
                ->where('usuario_id', $usuarioId)
                ->first();

            if ($existente) {
                if ($existente->deleted_at !== null) {
                    throw new DomainException(
                        'El usuario tiene un turno eliminado que requiere revisión.'
                    );
                }

                return $this->aEntidad($existente);
            }

            // Obtenemos la siguiente posición disponible.
            $ultimaPosicion = DB::table('cola_virtual')
                ->where('evento_id', $eventoId)
                ->max('posicion');

            $posicion = ((int) $ultimaPosicion) + 1;

            // Token generado criptográficamente.
            $token = bin2hex(random_bytes(32));

            $id = DB::table('cola_virtual')->insertGetId([
                'evento_id' => $eventoId,
                'usuario_id' => $usuarioId,
                'posicion' => $posicion,
                'token' => $token,
                'estado' => EstadoTurno::ESPERANDO->value,
                'habilitado_hasta' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return new Turno(
                id: $id,
                eventoId: $eventoId,
                usuarioId: $usuarioId,
                posicion: $posicion,
                token: $token,
                estado: EstadoTurno::ESPERANDO
            );
        });
    }

    private function aEntidad(object $registro): Turno
    {
        return new Turno(
            id: (int) $registro->id,
            eventoId: (int) $registro->evento_id,
            usuarioId: (int) $registro->usuario_id,
            posicion: (int) $registro->posicion,
            token: $registro->token,
            estado: EstadoTurno::from($registro->estado),
            habilitadoHasta: $registro->habilitado_hasta
                ? Carbon::parse($registro->habilitado_hasta)
                    ->toDateTimeImmutable()
                : null
        );
    }
}
