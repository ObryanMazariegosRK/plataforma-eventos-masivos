<?php

namespace App\Application\UseCases\Reembolsos;

use App\Domain\Abstractions\Reembolsos\IReembolsoRepository;
use App\Domain\Entities\Reembolsos\Reembolso;
use App\Domain\Enums\Pagos\EstadoPago;
use DomainException;
use Illuminate\Support\Facades\DB;

use App\Domain\Enums\Recintos\EstadoEvento;

class ResolverSolicitudReembolsoUseCase
{
    public function __construct(
        private readonly IReembolsoRepository $reembolsos
    ) {}

    public function execute(
        int $reembolsoId,
        string $decision,
        int $administradorId
    ): Reembolso {

        if ($reembolsoId <= 0 || $administradorId <= 0) {
            throw new DomainException(
                'Los identificadores no son válidos.'
            );
        }

        if (!in_array($decision, ['aprobar', 'rechazar'], true)) {
            throw new DomainException(
                'La decisión debe ser aprobar o rechazar.'
            );
        }

        return DB::transaction(function () use (
            $reembolsoId,
            $decision,
            $administradorId
        ) {

            // 1. Bloquear el registro para impedir que dos
            // administradores lo resuelvan simultáneamente.
            $registro = DB::table('reembolsos')
                ->where('id', $reembolsoId)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            if ($registro === null) {
                throw new DomainException(
                    'La solicitud de reembolso no existe.'
                );
            }

            // 2. Recuperar la entidad de dominio.
            $reembolso = $this->reembolsos->buscarPorId(
                $reembolsoId
            );

            if ($reembolso === null) {
                throw new DomainException(
                    'No se pudo recuperar el reembolso.'
                );
            }

            // 3. Antes de aprobar, comprobar que el
            // pago continúa aprobado y que el monto
            // solicitado no supera el monto pagado.
            if ($decision === 'aprobar') {

                $pago = DB::table('pagos')
                    ->where('id', $reembolso->getPagoId())
                    ->whereNull('deleted_at')
                    ->first();

                if (
                    $pago === null ||
                    $pago->estado !== EstadoPago::APROBADO->value
                ) {
                    throw new DomainException(
                        'El pago no está disponible o aprobado.'
                    );
                }

                if (
                    round($reembolso->getMonto(), 2) >
                    round((float) $pago->monto, 2)
                ) {
                    throw new DomainException(
                        'El reembolso supera el monto del pago.'
                    );
                }

                $reembolso->aprobar();

                // 3.1 Aprobar es cancelar la compra: sus boletos se anulan
                // y sus lugares vuelven a la venta (si el evento sigue en pie).
                $compra = $this->reembolsos->contextoDePago(
                    $reembolso->getPagoId()
                );

                $this->reembolsos->anularBoletosYLiberarLugares(
                    reservaId: $compra['reserva_id'],
                    registradoPor: $administradorId,
                    liberar: $compra['evento_estado'] !== EstadoEvento::CANCELADO->value
                );

            } else {

                // 4. Comprobar si el organizador ya cancelo el evento.
                $eventoCancelado = DB::table('pagos as p')
                    ->join('reservas as r', 'r.id', '=', 'p.reserva_id')
                    ->join('eventos as e', 'e.id', '=', 'r.evento_id')
                    ->where('p.id', $reembolso->getPagoId())
                    ->where('e.estado', EstadoEvento::CANCELADO->value)
                    ->exists();

                // En una cancelacion del organizador, el cliente
                // conserva su derecho a la devolucion completa.
                if ($eventoCancelado) {
                    throw new DomainException(
                        'No se puede rechazar un reembolso de un evento cancelado.'
                    );
                }

                // Solo rechazar solicitudes de eventos no cancelados.
                $reembolso->rechazar();
            }


            // 5. Conservar quién resolvió la solicitud.
            return $this->reembolsos->guardar(
                $reembolso,
                $administradorId
            );

        }, 3);
    }
}
