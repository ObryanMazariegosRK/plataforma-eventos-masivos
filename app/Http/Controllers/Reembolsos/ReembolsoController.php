<?php

namespace App\Http\Controllers\Reembolsos;

use App\Application\UseCases\Reembolsos\SolicitarReembolsoUseCase;
use App\Application\UseCases\Reembolsos\ResolverSolicitudReembolsoUseCase;
use App\Domain\Enums\Auth\RolUsuario;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

use App\Application\UseCases\Reembolsos\CancelarEventoConReembolsosUseCase;
use App\Application\Abstractions\Reembolsos\IProcesarReembolsoUseCase;
use App\Application\UseCases\Notificaciones\NotificarResolucionReembolsoUseCase;
use App\Domain\Abstractions\Reembolsos\IReembolsoRepository;
use App\Domain\Enums\Reembolsos\EstadoReembolso;
use App\Domain\Exceptions\Reembolsos\ReembolsoNoProcesadoException;

class ReembolsoController extends Controller
{
    // RF-18: Solicitud de reembolso del cliente.
    public function store(
        Request $request,
        SolicitarReembolsoUseCase $solicitarReembolso
    ): JsonResponse {

        if ($request->user()?->rol !== RolUsuario::CLIENTE) {
            return response()->json([
                'mensaje' => 'No tienes permisos para solicitar reembolsos.'
            ], 403);
        }

        $datos = $request->validate([
            'pago_id' => ['required', 'integer', 'min:1'],
            'motivo' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $reembolso = $solicitarReembolso->execute(
                pagoId: (int) $datos['pago_id'],
                usuarioId: (int) $request->user()->id,
                motivo: $datos['motivo'] ?? null
            );

            return response()->json([
                'mensaje' => 'Solicitud de reembolso registrada correctamente.',
                'reembolso' => [
                    'id' => $reembolso->getId(),
                    'pago_id' => $reembolso->getPagoId(),
                    'monto' => $reembolso->getMonto(),
                    'estado' => $reembolso->getEstado()->value,
                    'motivo' => $reembolso->getMotivo(),
                ]
            ], 201);

        } catch (DomainException $e) {
            return response()->json([
                'mensaje' => 'No se puede registrar la solicitud de reembolso. Verifica las condiciones de cancelación.'
            ], 409);
        }
    }

    // Gestión administrativa: aprobar o rechazar.
    public function resolver(
        Request $request,
        int $id,
        ResolverSolicitudReembolsoUseCase $resolverReembolso,
        IProcesarReembolsoUseCase $procesarReembolso,
        NotificarResolucionReembolsoUseCase $notificar
    ): JsonResponse {

        // 1. Verificar permisos.
        if ($request->user()?->rol !== RolUsuario::ADMIN) {
            return response()->json([
                'mensaje' => 'Solo un administrador puede resolver reembolsos.'
            ], 403);
        }

        // 2. Validar la decisión.
        $datos = $request->validate([
            'decision' => [
                'required',
                'in:aprobar,rechazar'
            ],
        ]);

        try {
            // 3. Ejecutar el caso de uso.
            $reembolso = $resolverReembolso->execute(
                reembolsoId: $id,
                decision: $datos['decision'],
                administradorId: (int) $request->user()->id
            );

            // 4. Aprobado: devolver el dinero en la pasarela.
            // Si la pasarela falla, el reembolso queda aprobado
            // y el panel permite reintentar la devolución.
            $aviso = null;

            if ($reembolso->getEstado() === EstadoReembolso::APROBADO) {
                try {
                    $reembolso = $procesarReembolso->execute(
                        $id,
                        (int) $request->user()->id
                    );
                } catch (ReembolsoNoProcesadoException $e) {
                    $aviso = $e->getMessage();
                }
            } else {
                rescue(fn () => $notificar->execute($id));
            }

            // 5. Devolver el resultado.
            return response()->json([
                'mensaje' => $aviso === null
                    ? 'Solicitud de reembolso resuelta correctamente.'
                    : 'El reembolso quedó aprobado, pero la devolución está pendiente: ' . $aviso,
                'devolucion_pendiente' => $aviso !== null,
                'reembolso' => [
                    'id' => $reembolso->getId(),
                    'pago_id' => $reembolso->getPagoId(),
                    'monto' => $reembolso->getMonto(),
                    'estado' => $reembolso->getEstado()->value,
                ]
            ]);

        } catch (DomainException $e) {
            return response()->json([
                'mensaje' => $e->getMessage()
            ], 409);
        }
    }

    // RF-20: Cancelacion masiva de un evento.
    public function cancelarEvento(
        Request $request,
        int $id,
        CancelarEventoConReembolsosUseCase $cancelarEvento,
        ResolverSolicitudReembolsoUseCase $resolverReembolso,
        IProcesarReembolsoUseCase $procesarReembolso,
        IReembolsoRepository $reembolsos
    ): JsonResponse {

        // 1. Solo los administradores pueden cancelar eventos.
        if ($request->user()?->rol !== RolUsuario::ADMIN) {
            return response()->json([
                'mensaje' => 'Solo un administrador puede cancelar eventos.'
            ], 403);
        }

        try {

            // 2. Ejecutar la cancelacion masiva.
            $resultado = $cancelarEvento->execute(
                eventoId: $id,
                administradorId: (int) $request->user()->id
            );

            // 3. Con el evento cancelado, el cliente tiene derecho al 100 %:
            // se aprueban y devuelven todas las solicitudes sin revisión.
            $administradorId = (int) $request->user()->id;
            $devueltos = 0;

            foreach ($reembolsos->idsDeEvento($id, EstadoReembolso::SOLICITADO) as $reembolsoId) {
                $resolverReembolso->execute($reembolsoId, 'aprobar', $administradorId);
            }

            foreach ($reembolsos->idsDeEvento($id, EstadoReembolso::APROBADO) as $reembolsoId) {
                try {
                    $procesarReembolso->execute($reembolsoId, $administradorId);
                    $devueltos++;
                } catch (ReembolsoNoProcesadoException $e) {
                    report($e);
                }
            }

            $resultado['reembolsos_devueltos'] = $devueltos;
            $pendientes = count($reembolsos->idsDeEvento($id, EstadoReembolso::APROBADO));

            // 4. Devolver el resumen de la operacion.
            return response()->json([
                'mensaje' => $pendientes > 0
                    ? "Evento cancelado. {$devueltos} reembolsos devueltos; {$pendientes} quedaron aprobados con la devolución pendiente (reintenta desde Reembolsos)."
                    : 'Evento cancelado correctamente. Se devolvió el 100 % de cada compra.',
                'resultado' => $resultado
            ], 200);

        } catch (DomainException $e) {

            // 4. Rechazar cancelaciones no permitidas.
            return response()->json([
                'mensaje' => $e->getMessage()
            ], 409);
        }
    }

}
