<?php

namespace App\Http\Controllers\ColaVirtual;

use App\Application\Abstractions\ColaVirtual\IIngresarColaVirtualUseCase;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ColaVirtualController extends Controller
{
    public function ingresar(
        Request $request,
        IIngresarColaVirtualUseCase $ingresarCola
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

        // Devolver su posición y estado actual.
        return response()->json($turno, 200);
    }
}
