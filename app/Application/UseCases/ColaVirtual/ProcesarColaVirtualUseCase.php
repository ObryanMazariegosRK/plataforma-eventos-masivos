<?php

namespace App\Application\UseCases\ColaVirtual;

use App\Application\Abstractions\ColaVirtual\IProcesarColaVirtualUseCase;
use Illuminate\Support\Facades\DB;
use App\Domain\Abstractions\Notificaciones\INotificacionRepository;
use App\Domain\Entities\Notificaciones\Notificacion;
use App\Domain\Enums\Notificaciones\CanalNotificacion;
use App\Domain\Enums\Notificaciones\EstadoNotificacion;
use App\Jobs\Notificaciones\EnviarNotificacionCorreoJob;

class ProcesarColaVirtualUseCase implements IProcesarColaVirtualUseCase
{
    public function __construct(
    private readonly INotificacionRepository $notificaciones
    ) {}
    public function execute(): array
    {
        $limite = max(1, (int) config('cola_virtual.max_turnos_simultaneos', 5));
        $minutos = max(1, (int) config('cola_virtual.ventana_compra_minutos', 5));

        $resultado = [
            'expirados' => 0,
            'habilitados' => 0,
        ];

        // Procesar solamente eventos publicados con cola activa.
        $eventoIds = DB::table('eventos')
            ->where('estado', 'publicado')
            ->where('cola_virtual_activa', true)
            ->whereNull('deleted_at')
            ->pluck('id');

        foreach ($eventoIds as $eventoId) {

            $parcial = DB::transaction(function () use (
                $eventoId,
                $limite,
                $minutos
            ): array {

                // Bloquear el evento para evitar que dos procesos
                // administren simultáneamente la misma cola.
                $evento = DB::table('eventos')
                    ->where('id', $eventoId)
                    ->lockForUpdate()
                    ->first();

                if (
                    !$evento ||
                    $evento->deleted_at !== null ||
                    $evento->estado !== 'publicado' ||
                    !(bool) $evento->cola_virtual_activa
                ) {
                    return ['expirados' => 0, 'habilitados' => 0];
                }

                $ahora = now();

                // 1. Expirar las ventanas de compra vencidas.
                
                // 1. Identificar los turnos cuya ventana de compra venció.
                $turnosVencidos = DB::table('cola_virtual')
                    ->where('evento_id', $eventoId)
                    ->where('estado', 'habilitado')
                    ->whereNull('deleted_at')
                    ->where(function ($consulta) use ($ahora) {
                        $consulta
                            ->where('habilitado_hasta', '<=', $ahora)
                            ->orWhereNull('habilitado_hasta');
                    })
                    ->lockForUpdate()
                    ->get(['id', 'usuario_id', 'intento']);

                $expirados = 0;

                foreach ($turnosVencidos as $turnoVencido) {

                    // Expirar únicamente un turno todavía habilitado.
                    $actualizados = DB::table('cola_virtual')
                        ->where('id', $turnoVencido->id)
                        ->where('estado', 'habilitado')
                        ->update([
                            'estado' => 'expirado',
                            'updated_at' => $ahora,
                        ]);

                    if ($actualizados !== 1) {
                        continue;
                    }

                    $expirados++;

                    // Registrar el aviso de expiración.
                    $notificacion = new Notificacion(
                        id: null,
                        usuarioId: (int) $turnoVencido->usuario_id,
                        canal: CanalNotificacion::EMAIL,
                        asunto: 'Tu turno de compra ha expirado',
                        mensaje: 'El tiempo disponible para realizar '
                            . 'tu reserva terminó. Tu turno en la '
                            . 'Cola Virtual ha expirado.',
                        estado: EstadoNotificacion::PENDIENTE
                    );

                    // Evitar registros duplicados por turno.

                    // Identificar la notificación de expiración.
                    $clave = 'cola_virtual:expirado:turno:'
                        . $turnoVencido->id;

                    // El segundo intento necesita su propia clave.
                    // Conservamos la clave original del intento 1.
                    if ((int) $turnoVencido->intento === 2) {
                        $clave .= ':intento:2';
                    }

                    $guardada = $this->notificaciones->guardarUnica(
                        notificacion: $notificacion,
                        claveIdempotencia: $clave
                    );


                    // Programar el envío después de confirmar
                    // la transacción.
                    if (
                        $guardada->getEstado()
                        === EstadoNotificacion::PENDIENTE
                    ) {
                        EnviarNotificacionCorreoJob::dispatch(
                            $guardada->getId()
                        )->afterCommit();
                    }
                }

                // 2. Contar compradores con una ventana vigente.
                $activos = DB::table('cola_virtual')
                    ->where('evento_id', $eventoId)
                    ->where('estado', 'habilitado')
                    ->where('habilitado_hasta', '>', $ahora)
                    ->whereNull('deleted_at')
                    ->count();


                // Calcular espacios disponibles para nuevos compradores.
                $espacios = max(0, $limite - $activos);


                // Contar los boletos disponibles del evento.
                // Si no hay inventario, el resultado será cero.
                $boletosDisponibles = DB::table('inventario')
                    ->where('evento_id', $eventoId)
                    ->where('estado', 'disponible')
                    ->whereNull('deleted_at')
                    ->count();

                // No habilitar más compradores que boletos disponibles.
                // Si no existen boletos, nadie será habilitado.
                $espacios = min($espacios, $boletosDisponibles);

                // 3. Seleccionar usuarios según su posición.

                // Seleccionar turnos solamente si hay espacios disponibles.
                // Si la cola está llena, continuar con las notificaciones.
                $turnoIds = $espacios > 0
                    ? DB::table('cola_virtual')
                        ->where('evento_id', $eventoId)
                        ->where('estado', 'esperando')
                        ->whereNull('deleted_at')
                        ->orderBy('posicion')
                        ->orderBy('id')
                        ->limit($espacios)
                        ->pluck('id')
                    : collect();


                // 4. Habilitar solamente los turnos seleccionados.
                $habilitados = 0;

                if ($turnoIds->isNotEmpty()) {
                    $habilitados = DB::table('cola_virtual')
                        ->whereIn('id', $turnoIds)
                        ->where('estado', 'esperando')
                        ->whereNull('deleted_at')
                        ->update([
                            'estado' => 'habilitado',
                            'habilitado_hasta' => $ahora
                                ->copy()
                                ->addMinutes($minutos),
                            'updated_at' => $ahora,
                        ]);
                        
                    // Recuperar los turnos que acabamos de habilitar.
                    $turnosHabilitados = DB::table('cola_virtual')
                        ->whereIn('id', $turnoIds)
                        ->where('estado', 'habilitado')
                        ->whereNull('deleted_at')
                        ->get(['id', 'usuario_id', 'intento']);

                    foreach ($turnosHabilitados as $turnoHabilitado) {

                        // Crear el aviso para el usuario.
                        $notificacion = new Notificacion(
                            id: null,
                            usuarioId: (int) $turnoHabilitado->usuario_id,
                            canal: CanalNotificacion::EMAIL,
                            asunto: '¡Tu turno de compra está habilitado!',
                            mensaje: 'Ya puedes realizar tu reserva. '
                                . 'Tienes ' . $minutos
                                . ' minutos para completar tu compra.',
                            estado: EstadoNotificacion::PENDIENTE
                        );


                    // Distinguir las notificaciones de cada oportunidad.
                    $clave = 'cola_virtual:habilitado:turno:'
                        . $turnoHabilitado->id;

                    // Conservar el formato original del intento 1.
                    if ((int) $turnoHabilitado->intento === 2) {
                        $clave .= ':intento:2';
                    }

                    $guardada = $this->notificaciones->guardarUnica(
                        notificacion: $notificacion,
                        claveIdempotencia: $clave
                    );


                        // Programar el correo después de confirmar
                        // la transacción de base de datos.
                        if (
                            $guardada->getEstado()
                            === EstadoNotificacion::PENDIENTE
                        ) {
                            EnviarNotificacionCorreoJob::dispatch(
                                $guardada->getId()
                            )->afterCommit();
                        }
                    }

                }

                // 5. Avisar a los usuarios cuyo turno se aproxima.
                $umbral = max(
                    0,
                    (int) config('cola_virtual.aviso_proximo_personas', 2)
                );

                if ($umbral > 0) {

                    // Revisar los primeros usuarios que siguen esperando.
                    $proximos = DB::table('cola_virtual')
                        ->where('evento_id', $eventoId)
                        ->where('estado', 'esperando')
                        ->whereNull('deleted_at')
                        ->orderBy('posicion')
                        ->orderBy('id')
                        ->limit($umbral)
                        ->get(['id', 'usuario_id', 'posicion', 'intento']);

                    foreach ($proximos as $turnoProximo) {

                        // Contar solamente turnos que siguen activos
                        // y están delante de este usuario.
                        $personasDelante = DB::table('cola_virtual')
                            ->where('evento_id', $eventoId)
                            ->where('posicion', '<', $turnoProximo->posicion)
                            ->whereNull('deleted_at')
                            ->where(function ($consulta) use ($ahora) {
                                $consulta
                                    ->where('estado', 'esperando')
                                    ->orWhere(function ($subconsulta) use ($ahora) {
                                        $subconsulta
                                            ->where('estado', 'habilitado')
                                            ->where('habilitado_hasta', '>', $ahora);
                                    });
                            })
                            ->count();

                        if ($personasDelante > $umbral) {
                            continue;
                        }


                        // Identificar el aviso de proximidad del turno.
                        $clave = 'cola_virtual:proximo:turno:'
                            . $turnoProximo->id;

                        // Diferenciar el segundo intento del primero.
                        if ((int) $turnoProximo->intento === 2) {
                            $clave .= ':intento:2';
                        }


                        // Evitar registrar o programar nuevamente
                        // una notificación que ya existe.
                        if (
                            DB::table('notificaciones')
                                ->where('clave_idempotencia', $clave)
                                ->exists()
                        ) {
                            continue;
                        }

                        $notificacion = new Notificacion(
                            id: null,
                            usuarioId: (int) $turnoProximo->usuario_id,
                            canal: CanalNotificacion::EMAIL,
                            asunto: '¡Tu turno de compra se acerca!',
                            mensaje: 'Tu turno en la Cola Virtual está próximo. '
                                . 'Tienes ' . $personasDelante
                                . ' persona(s) delante. '
                                . 'Mantente atento para realizar tu reserva.',
                            estado: EstadoNotificacion::PENDIENTE
                        );

                        $guardada = $this->notificaciones->guardarUnica(
                            notificacion: $notificacion,
                            claveIdempotencia: $clave
                        );

                        // Enviar solamente después de confirmar
                        // los cambios de la transacción.
                        EnviarNotificacionCorreoJob::dispatch(
                            $guardada->getId()
                        )->afterCommit();
                    }
                }

                return [
                    'expirados' => $expirados,
                    'habilitados' => $habilitados,
                ];

            }, 3);

            $resultado['expirados'] += $parcial['expirados'];
            $resultado['habilitados'] += $parcial['habilitados'];
        }

        return $resultado;
    }
}
