<?php

namespace App\Application\UseCases\Reembolsos;

use App\Domain\Abstractions\Reembolsos\IReembolsoRepository;
use App\Domain\Entities\Reembolsos\Reembolso;
use App\Domain\Enums\Boletos\EstadoBoleto;
use App\Domain\Enums\Pagos\EstadoPago;
use App\Domain\Enums\Recintos\EstadoEvento;
use App\Domain\Enums\Reembolsos\EstadoReembolso;
use DomainException;
use Illuminate\Support\Facades\DB;

use App\Application\UseCases\Transacciones\RegistrarOperacionHistorialUseCase;
use App\Domain\Enums\Transacciones\TipoOperacionHistorial;

class CancelarEventoConReembolsosUseCase
{
    
    public function __construct(
        private readonly IReembolsoRepository $reembolsos,
        private readonly RegistrarOperacionHistorialUseCase $historial
    ) {}


    public function execute(
        int $eventoId,
        int $administradorId
    ): array {

        if ($eventoId <= 0 || $administradorId <= 0) {
            throw new DomainException(
                'Los identificadores no son válidos.'
            );
        }

        return DB::transaction(function () use (
            $eventoId,
            $administradorId
        ) {

            // 1. Bloquear el evento durante la cancelación.
            $evento = DB::table('eventos')
                ->where('id', $eventoId)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            if ($evento === null) {
                throw new DomainException(
                    'El evento no existe.'
                );
            }

            if ($evento->estado === EstadoEvento::CANCELADO->value) {
                throw new DomainException(
                    'El evento ya está cancelado.'
                );
            }

            if (!in_array($evento->estado, [
                EstadoEvento::PUBLICADO->value,
                EstadoEvento::AGOTADO->value,
            ], true)) {
                throw new DomainException(
                    'El estado del evento no permite su cancelación.'
                );
            }

            // 2. Obtener las reservas del evento.
            $reservasIds = DB::table('reservas')
                ->where('evento_id', $eventoId)
                ->whereNull('deleted_at')
                ->pluck('id');

            // 3. Obtener únicamente pagos aprobados.
            $pagos = DB::table('pagos')
                ->whereIn('reserva_id', $reservasIds)
                ->where('estado', EstadoPago::APROBADO->value)
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $reembolsosCreados = 0;
            $montoPreparado = 0;

            // 4. Preparar devolución completa por cada pago.
            foreach ($pagos as $pago) {

                $montoPagadoCentavos = (int) round(
                    (float) $pago->monto * 100
                );

                // Considerar solicitudes previas, aprobadas
                // y procesadas para impedir sobre-reembolsos.
                $montoPrevio = DB::table('reembolsos')
                    ->where('pago_id', $pago->id)
                    ->whereIn('estado', [
                        EstadoReembolso::SOLICITADO->value,
                        EstadoReembolso::APROBADO->value,
                        EstadoReembolso::PROCESADO->value,
                    ])
                    ->sum('monto');

                $montoPrevioCentavos = (int) round(
                    (float) $montoPrevio * 100
                );

                $restanteCentavos = $montoPagadoCentavos
                    - $montoPrevioCentavos;

                if ($restanteCentavos < 0) {
                    throw new DomainException(
                        'Existen reembolsos que superan el monto de un pago.'
                    );
                }

                // Si ya tiene cubierta la devolución completa,
                // no generar otro reembolso.
                if ($restanteCentavos === 0) {
                    continue;
                }

                $montoRestante = $restanteCentavos / 100;

                $reembolso = new Reembolso(
                    id: null,
                    pagoId: (int) $pago->id,
                    monto: $montoRestante,
                    estado: EstadoReembolso::SOLICITADO,
                    motivo: 'Cancelación del evento por el organizador'
                );

                $this->reembolsos->guardar(
                    $reembolso,
                    $administradorId
                );

                $reembolsosCreados++;
                $montoPreparado += $montoRestante;
            }

            // 5. Anular boletos emitidos.
            // Los boletos usados conservan su historial.
            $boletosAnulados = DB::table('boletos')
                ->whereIn('reserva_id', $reservasIds)
                ->where('estado', EstadoBoleto::EMITIDO->value)
                ->whereNull('deleted_at')
                ->update([
                    'estado' => EstadoBoleto::ANULADO->value,
                    'updated_by' => $administradorId,
                    'updated_at' => now(),
                ]);

            // 6. Marcar el evento como cancelado.
            DB::table('eventos')
                ->where('id', $eventoId)
                ->update([
                    'estado' => EstadoEvento::CANCELADO->value,
                    'updated_by' => $administradorId,
                    'updated_at' => now(),
                ]);

            // 7. Registrar la cancelacion masiva en el historial.
            $this->historial->execute(
                tipo: TipoOperacionHistorial::EVENTO_CANCELADO,

                // Una clave unica por evento cancelado.
                claveIdempotencia: 'evento_cancelado:' . $eventoId,

                eventoId: $eventoId,

                registradoPor: $administradorId,

                descripcion: 'Cancelacion del evento por el organizador.',

                datos: [
                    'pagos_aprobados' => $pagos->count(),
                    'reembolsos_creados' => $reembolsosCreados,
                    'monto_preparado' => round($montoPreparado, 2),
                    'boletos_anulados' => $boletosAnulados,
                ]
            );

            return [
                'evento_id' => $eventoId,
                'estado' => EstadoEvento::CANCELADO->value,
                'pagos_aprobados' => $pagos->count(),
                'reembolsos_creados' => $reembolsosCreados,
                'monto_preparado' => round($montoPreparado, 2),
                'boletos_anulados' => $boletosAnulados,
            ];

        }, 3);
    }
}
