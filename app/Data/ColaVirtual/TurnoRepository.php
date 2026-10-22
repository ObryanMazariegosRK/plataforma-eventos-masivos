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

                $intentoActual = (int) $existente->intento;


                // Detectar si el turno ya venció, aunque el Scheduler
                // todavía no haya actualizado su estado.
                $vencidoSinScheduler =
                    $existente->estado === EstadoTurno::HABILITADO->value
                    && (
                        $existente->habilitado_hasta === null
                        || Carbon::parse($existente->habilitado_hasta)
                            ->lessThanOrEqualTo(now())
                    );

                // Estado que debemos utilizar para decidir el reingreso.
                $estadoEfectivo = $vencidoSinScheduler
                    ? EstadoTurno::EXPIRADO->value
                    : $existente->estado;


                // Solo se permite reingresar después del primer turno expirado.
                if ($estadoEfectivo === EstadoTurno::EXPIRADO->value) {

                    // La segunda oportunidad es la última.
                    if ($intentoActual >= 2) {
                        throw new DomainException(
                            'Ya utilizaste las dos oportunidades para este evento.'
                        );
                    }

                    // Conservar el historial del primer intento.
                    DB::table('cola_virtual_intentos')->insert([
                        'turno_id' => $existente->id,
                        'intento' => $intentoActual,
                        'posicion' => $existente->posicion,
                        'estado' => $estadoEfectivo,
                        'habilitado_hasta' => $existente->habilitado_hasta,
                        'finalizado_en' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    // Buscar la última posición asignada en el evento.
                    $ultimaPosicion = DB::table('cola_virtual')
                        ->where('evento_id', $eventoId)
                        ->max('posicion');

                    // El usuario regresa al final de la cola.
                    $nuevaPosicion = ((int) $ultimaPosicion) + 1;
                    $nuevoToken = bin2hex(random_bytes(32));

                    DB::table('cola_virtual')
                        ->where('id', $existente->id)
                        ->update([
                            'posicion' => $nuevaPosicion,
                            'token' => $nuevoToken,
                            'estado' => EstadoTurno::ESPERANDO->value,
                            'habilitado_hasta' => null,
                            'intento' => 2,
                            'updated_at' => now(),
                        ]);

                    // Recuperar el turno actualizado.
                    $actualizado = DB::table('cola_virtual')
                        ->where('id', $existente->id)
                        ->first();

                    return $this->aEntidad($actualizado);
                }

                // Los turnos atendidos no pueden volver a ingresar.
                if ($existente->estado === EstadoTurno::ATENDIDO->value) {
                    throw new DomainException(
                        'Ya utilizaste tu turno de compra para este evento.'
                    );
                }

                // Si está esperando o habilitado, conservar el turno.
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
