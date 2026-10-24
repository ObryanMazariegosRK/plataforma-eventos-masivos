<?php

namespace App\Http\Controllers\ColaVirtual;

use App\Application\Abstractions\ColaVirtual\IIngresarColaVirtualUseCase;
use App\Application\Abstractions\ColaVirtual\IProcesarColaVirtualUseCase;
use App\Domain\Abstractions\ColaVirtual\ITurnoRepository;
use Illuminate\Support\Facades\Cache;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ColaVirtualController extends Controller
{
    public function __construct(
        private readonly IProcesarColaVirtualUseCase $procesar,
    ) {}

    /**
     * Avance inmediato: la fila de este evento se procesa al entrar y al consultar el turno
     * (la sala de espera consulta cada pocos segundos), como mucho una vez por segundo por
     * evento. Así nadie espera al minuto del Scheduler si ya hay lugares libres.
     */
    private function avanzar(int $eventoId): void
    {
        if (! config('cola_virtual.procesamiento_automatico_habilitado', false)) {
            return;
        }
        if (Cache::add("cola_virtual:avance:{$eventoId}", true, 1)) {
            rescue(fn () => $this->procesar->execute($eventoId));
        }
    }

    public function ingresar(
        Request $request,
        IIngresarColaVirtualUseCase $ingresarCola,
        ITurnoRepository $turnos
    ): JsonResponse {

        // 1. Validar el identificador del evento.
        $datos = $request->validate([
            'evento_id' => ['required', 'integer', 'min:1'],
        ]);

        // 2. Obtener el usuario autenticado.
        // Nunca confiar en un usuario_id enviado por el cliente.
        $usuarioId = (int) $request->user()->id;

        try {
            // 3. Ejecutar nuestro caso de uso.
            $turno = $ingresarCola->execute(
                (int) $datos['evento_id'],
                $usuarioId
            );

            // Si hay lugares libres, pasa en este mismo momento
            $this->avanzar((int) $datos['evento_id']);
            $turno = $turnos->buscarPorEventoYUsuario((int) $datos['evento_id'], $usuarioId) ?? $turno;

            // 4. Devolver la información del turno.
            return response()->json([
                'id' => $turno->getId(),
                'evento_id' => $turno->getEventoId(),
                'posicion' => $turno->getPosicion(),
                'token' => $turno->getToken(),
                'estado' => $turno->getEstado()->value,
                'habilitado_hasta' => $turno->getHabilitadoHasta()
                    ?->format(DATE_ATOM),
            ], 200);

        } catch (DomainException $e) {

            // Distinguir un evento inexistente
            // de una regla de negocio incumplida.
            $codigoHttp = $e->getMessage() === 'El evento no existe.'
                ? 404
                : 422;

            return response()->json([
                'message' => $e->getMessage(),
            ], $codigoHttp);
        }
    }

    /**
     * Panel (admin y operador): cómo va la fila de un evento. "Ver asientos" lo muestra en vivo.
     */
    public function resumen(int $id, ITurnoRepository $turnos): JsonResponse
    {
        $this->avanzar($id);

        return response()->json(['data' => $turnos->resumen($id) + [
            'simultaneos' => (int) config('cola_virtual.max_turnos_simultaneos'),
            'ventana_minutos' => (int) config('cola_virtual.ventana_compra_minutos'),
        ]]);
    }

    /**
     * Consultar la posición y el estado del turno
     * del usuario autenticado.
     */
    public function miTurno(
        Request $request,
        \App\Application\Abstractions\ColaVirtual\IConsultarMiTurnoUseCase $consultar,
        int $eventoId
    ): JsonResponse {

        // Obtener el usuario desde la autenticación.
        $usuarioId = (int) $request->user()->id;

        // Primero la fila avanza (vence ventanas, habilita a los siguientes)
        $this->avanzar($eventoId);

        // Consultar su turno en el evento solicitado.
        $turno = $consultar->execute(
            $eventoId,
            $usuarioId
        );

        // El usuario no tiene turno en este evento.
        if ($turno === null) {
            return response()->json([
                'message' => 'No tienes un turno registrado para este evento.',
            ], 404);
        }

        // Devolver su posición y estado actual, con las reglas de la fila para la sala de espera
        return response()->json($turno + [
            'simultaneos' => (int) config('cola_virtual.max_turnos_simultaneos'),
            'ventana_minutos' => (int) config('cola_virtual.ventana_compra_minutos'),
        ], 200);
    }
}
