<?php

namespace App\Application\UseCases\ColaVirtual;

use App\Application\Abstractions\ColaVirtual\IIngresarColaVirtualUseCase;
use App\Domain\Abstractions\ColaVirtual\ITurnoRepository;
use App\Domain\Entities\ColaVirtual\Turno;
use Illuminate\Support\Facades\DB;
use DomainException;

use App\Domain\Abstractions\Notificaciones\INotificacionRepository;
use App\Domain\Entities\Notificaciones\Notificacion;
use App\Domain\Enums\Notificaciones\CanalNotificacion;
use App\Domain\Enums\Notificaciones\EstadoNotificacion;
use App\Jobs\Notificaciones\EnviarNotificacionCorreoJob;

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

    // 5. Obtener el turno dentro de una transacción.
    // Esto permite guardar también el aviso de expiración
    // si el usuario reingresa antes de que actúe el Scheduler.
    try {
    return DB::transaction(function () use ($eventoId, $usuarioId): Turno {

        $turno = $this->turnos->obtenerOCrearTurno(
            $eventoId,
            $usuarioId
        );

        // Comprobar si existe una primera oportunidad
        // que terminó y fue guardada en el historial.
        $primerIntentoExpirado = DB::table('cola_virtual_intentos')
            ->where('turno_id', $turno->getId())
            ->where('intento', 1)
            ->where('estado', 'expirado')
            ->exists();

        if ($primerIntentoExpirado) {

            // Mantener la clave original del primer intento.
            $clave = 'cola_virtual:expirado:turno:'
                . $turno->getId();

            // Si el procesador ya notificó la expiración,
            // no registrar ni programar otro correo.
            $yaNotificado = DB::table('notificaciones')
                ->where('clave_idempotencia', $clave)
                ->exists();

            if (!$yaNotificado) {

                $notificacion = new Notificacion(
                    id: null,
                    usuarioId: $usuarioId,
                    canal: CanalNotificacion::EMAIL,
                    asunto: 'Tu turno de compra ha expirado',
                    mensaje: 'El tiempo disponible para realizar '
                        . 'tu reserva terminó. Tu turno en la '
                        . 'Cola Virtual ha expirado.',
                    estado: EstadoNotificacion::PENDIENTE
                );

                $guardada = app(INotificacionRepository::class)
                    ->guardarUnica(
                        notificacion: $notificacion,
                        claveIdempotencia: $clave
                    );

                // El correo se programa únicamente después
                // de confirmar la transacción.
                EnviarNotificacionCorreoJob::dispatch(
                    $guardada->getId()
                )->afterCommit();
            }
        }

        return $turno;

    });

    } catch (DomainException $e) {

        // Solo gestionar el rechazo por haber utilizado
        // las dos oportunidades de compra.
        if (
            $e->getMessage() !==
            'Ya utilizaste las dos oportunidades para este evento.'
        ) {
            throw $e;
        }

        // La transacción anterior ya fue revertida.
        // Registrar la expiración en una transacción separada.
        DB::transaction(function () use ($eventoId, $usuarioId): void {

            // Respetar el orden de bloqueos del procesador:
            // primero el evento y después el turno.
            DB::table('eventos')
                ->where('id', $eventoId)
                ->lockForUpdate()
                ->first();

            $turno = DB::table('cola_virtual')
                ->where('evento_id', $eventoId)
                ->where('usuario_id', $usuarioId)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            if (
                !$turno ||
                (int) $turno->intento !== 2 ||
                $turno->estado !== 'habilitado'
            ) {
                return;
            }

            $ahora = now();

            // No expirar un turno que todavía está vigente.
            if (
                $turno->habilitado_hasta !== null &&
                \Illuminate\Support\Carbon::parse(
                    $turno->habilitado_hasta
                )->greaterThan($ahora)
            ) {
                return;
            }

            // El segundo intento realmente está vencido.
            DB::table('cola_virtual')
                ->where('id', $turno->id)
                ->where('estado', 'habilitado')
                ->update([
                    'estado' => 'expirado',
                    'updated_at' => $ahora,
                ]);

            $clave = 'cola_virtual:expirado:turno:'
                . $turno->id
                . ':intento:2';

            // Evitar notificaciones duplicadas.
            if (
                DB::table('notificaciones')
                    ->where('clave_idempotencia', $clave)
                    ->exists()
            ) {
                return;
            }

            $notificacion = new Notificacion(
                id: null,
                usuarioId: $usuarioId,
                canal: CanalNotificacion::EMAIL,
                asunto: 'Tu turno de compra ha expirado',
                mensaje: 'El tiempo disponible para realizar '
                    . 'tu reserva terminó. Tu turno en la '
                    . 'Cola Virtual ha expirado.',
                estado: EstadoNotificacion::PENDIENTE
            );

            $guardada = app(INotificacionRepository::class)
                ->guardarUnica(
                    notificacion: $notificacion,
                    claveIdempotencia: $clave
                );

            EnviarNotificacionCorreoJob::dispatch(
                $guardada->getId()
            )->afterCommit();

        }, 3);

        // Mantener el rechazo: no existe tercera oportunidad.
        throw $e;
    }

    }
}
