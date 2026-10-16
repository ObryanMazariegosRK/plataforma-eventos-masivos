<?php

namespace App\Application\UseCases\Reembolsos;

use App\Domain\Abstractions\Reembolsos\IReembolsoRepository;
use App\Domain\Entities\Reembolsos\Reembolso;
use App\Domain\Enums\Boletos\EstadoBoleto;
use App\Domain\Enums\Pagos\EstadoPago;
use App\Domain\Enums\Reservas\EstadoReserva;
use App\Domain\Enums\Reembolsos\EstadoReembolso;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

class SolicitarReembolsoUseCase
{
    public function __construct(
        private readonly IReembolsoRepository $reembolsos,
        private readonly CalcularReembolsoUseCase $calculadora
    ) {}

    public function execute(
        int $pagoId,
        int $usuarioId,
        ?string $motivo = null
    ): Reembolso {

        if ($pagoId <= 0 || $usuarioId <= 0) {
            throw new DomainException(
                'Los identificadores proporcionados no son válidos.'
            );
        }

        $motivo = $motivo === null ? null : trim($motivo);

        if ($motivo !== null && strlen($motivo) > 255) {
            throw new DomainException(
                'El motivo no puede superar los 255 caracteres.'
            );
        }

        return DB::transaction(function () use (
            $pagoId,
            $usuarioId,
            $motivo
        ) {

            // 1. Bloquear el pago para evitar solicitudes
            // simultáneas de reembolso sobre el mismo pago.
            $pago = DB::table('pagos')
                ->where('id', $pagoId)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            if (
                $pago === null ||
                $pago->estado !== EstadoPago::APROBADO->value
            ) {
                throw new DomainException(
                    'El pago no existe o no está aprobado.'
                );
            }

            // 2. Buscar la reserva asociada al pago.
            $reserva = DB::table('reservas')
                ->where('id', $pago->reserva_id)
                ->whereNull('deleted_at')
                ->first();

            if ($reserva === null) {
                throw new DomainException(
                    'La reserva asociada no está disponible.'
                );
            }

            // 3. Comprobar que sea el propietario.
            if ((int) $reserva->usuario_id !== $usuarioId) {
                throw new DomainException(
                    'No puedes solicitar el reembolso de otro usuario.'
                );
            }

            // 4. Solo permitir solicitudes sobre
            // reservas confirmadas.
            if (
                $reserva->estado !== EstadoReserva::CONFIRMADA->value
            ) {
                throw new DomainException(
                    'La reserva no está confirmada.'
                );
            }

            // 5. Evitar múltiples solicitudes para el mismo pago.
            // Incluimos registros eliminados lógicamente
            // para evitar reutilizar una operación anterior.
            $existeReembolso = DB::table('reembolsos')
                ->where('pago_id', $pagoId)
                ->exists();

            if ($existeReembolso) {
                throw new DomainException(
                    'Ya existe una solicitud de reembolso para este pago.'
                );
            }

            // 6. No permitir cancelaciones voluntarias
            // si algún boleto ya se utilizó o fue anulado.
            $boletoNoDisponible = DB::table('boletos')
                ->where('reserva_id', $reserva->id)
                ->whereIn('estado', [
                    EstadoBoleto::USADO->value,
                    EstadoBoleto::ANULADO->value,
                ])
                ->exists();

            if ($boletoNoDisponible) {
                throw new DomainException(
                    'La reserva contiene boletos usados o anulados.'
                );
            }

            // 7. Obtener la fecha del evento.
            $evento = DB::table('eventos')
                ->where('id', $reserva->evento_id)
                ->whereNull('deleted_at')
                ->first();

            if ($evento === null) {
                throw new DomainException(
                    'El evento no está disponible.'
                );
            }

            // 8. Calcular el reembolso mediante
            // la política estricta que configuramos.
            $calculo = $this->calculadora->execute(
                montoPagado: (float) $pago->monto,
                fechaEvento: new DateTimeImmutable(
                    $evento->fecha_evento
                )
            );

            $montoReembolso = $calculo['monto_reembolso'];

            if ($montoReembolso <= 0) {
                throw new DomainException(
                    'La política no permite un reembolso positivo.'
                );
            }

            // 9. Crear la solicitud.
            // No se devuelve dinero en este paso.
            $reembolso = new Reembolso(
                id: null,
                pagoId: $pagoId,
                monto: $montoReembolso,
                estado: EstadoReembolso::SOLICITADO,
                motivo: $motivo
            );

            // 10. Guardar y devolver la solicitud.
            return $this->reembolsos->guardar(
                $reembolso,
                $usuarioId
            );

        }, 3);
    }
}
