<?php

namespace App\Application\UseCases\Reembolsos;

use App\Application\Abstractions\Reembolsos\IProcesarReembolsoUseCase;
use App\Application\UseCases\Notificaciones\NotificarPersonalUseCase;
use App\Application\UseCases\Notificaciones\NotificarResolucionReembolsoUseCase;
use App\Application\UseCases\Transacciones\RegistrarOperacionHistorialUseCase;
use App\Application\UseCases\Transacciones\RegistrarTransaccionUseCase;
use App\Domain\Abstractions\Pasarelas\IPasarelaPagoResolver;
use App\Domain\Abstractions\Reembolsos\IReembolsoRepository;
use App\Domain\Abstractions\Shared\IUnidadDeTrabajo;
use App\Domain\Entities\Reembolsos\Reembolso;
use App\Domain\Enums\Reembolsos\EstadoReembolso;
use App\Domain\Enums\Transacciones\TipoOperacionHistorial;
use App\Domain\Enums\Transacciones\TipoTransaccion;
use App\Domain\Exceptions\Reembolsos\ReembolsoNoEncontradoException;
use App\Domain\Exceptions\Reembolsos\ReembolsoNoProcesadoException;
use DomainException;
use Throwable;

/**
 * Devuelve el dinero de un reembolso aprobado:
 *   1. la pasarela devuelve el monto del cobro original (con clave de idempotencia: un
 *      reintento nunca devuelve dos veces);
 *   2. en una transacción: reembolso PROCESADO + movimiento REEMBOLSO en Transacciones +
 *      historial REEMBOLSO_CONFIRMADO;
 *   3. se avisa al cliente por correo (en cola).
 * Si la pasarela falla, el reembolso sigue APROBADO y el panel ofrece reintentar.
 */
class ProcesarReembolsoUseCase implements IProcesarReembolsoUseCase
{
    public function __construct(
        private readonly IReembolsoRepository $reembolsos,
        private readonly IPasarelaPagoResolver $pasarelas,
        private readonly IUnidadDeTrabajo $unidad,
        private readonly RegistrarTransaccionUseCase $transacciones,
        private readonly RegistrarOperacionHistorialUseCase $historial,
        private readonly NotificarResolucionReembolsoUseCase $notificar,
        private readonly NotificarPersonalUseCase $personal,
    ) {}

    public function execute(int $reembolsoId, int $procesadoPor): Reembolso
    {
        $reembolso = $this->reembolsos->buscarPorId($reembolsoId)
            ?? throw new ReembolsoNoEncontradoException;

        if ($reembolso->getEstado() === EstadoReembolso::PROCESADO) {
            return $reembolso;
        }
        if ($reembolso->getEstado() !== EstadoReembolso::APROBADO) {
            throw new DomainException('Solo se devuelve el dinero de un reembolso aprobado.');
        }

        $pago = $this->reembolsos->contextoDePago($reembolso->getPagoId())
            ?? throw new DomainException('El pago del reembolso no está disponible.');

        if ($pago['referencia_cobro'] === null || $pago['pasarela'] === null) {
            throw new ReembolsoNoProcesadoException('El pago no tiene un cobro de la pasarela que se pueda devolver.');
        }

        $resultado = $this->pasarelas->para($pago['pasarela'])->reembolsar(
            $pago['referencia_cobro'],
            $reembolso->getMonto(),
            'reembolso-'.$reembolsoId,
        );

        if (! $resultado->aprobado) {
            rescue(fn () => $this->personal->devolucionPendiente($reembolsoId, (string) $resultado->motivo));
            throw new ReembolsoNoProcesadoException("La pasarela no devolvió el dinero: {$resultado->motivo}");
        }

        $procesado = $this->unidad->ejecutar(function () use ($reembolsoId, $procesadoPor, $pago, $resultado) {
            $actual = $this->reembolsos->buscarPorId($reembolsoId);
            if ($actual->getEstado() === EstadoReembolso::PROCESADO) {
                return $actual;   // otro proceso llegó primero (misma devolución por la idempotencia)
            }

            $actual->marcarProcesado($resultado->referencia);
            $actual = $this->reembolsos->guardar($actual, $procesadoPor);

            $this->transacciones->execute(
                tipo: TipoTransaccion::REEMBOLSO,
                monto: $actual->getMonto(),
                usuarioId: $pago['usuario_id'],
                reservaId: $pago['reserva_id'],
                pagoId: $pago['pago_id'],
                referencia: $resultado->referencia,
                descripcion: "Reembolso #{$reembolsoId} de la compra #{$pago['reserva_id']}",
                registradoPor: $procesadoPor,
            );

            $this->historial->execute(
                tipo: TipoOperacionHistorial::REEMBOLSO_CONFIRMADO,
                claveIdempotencia: 'reembolso_procesado:'.$reembolsoId,
                usuarioId: $pago['usuario_id'],
                eventoId: $pago['evento_id'],
                reservaId: $pago['reserva_id'],
                pagoId: $pago['pago_id'],
                registradoPor: $procesadoPor,
                descripcion: 'Devolución realizada en la pasarela.',
                datos: ['reembolso_id' => $reembolsoId, 'monto' => $actual->getMonto(), 'referencia' => $resultado->referencia],
            );

            return $actual;
        });

        // El dinero ya se devolvió: si el aviso falla, no se deshace nada
        try {
            $this->notificar->execute($reembolsoId);
        } catch (Throwable $e) {
            report($e);
        }

        return $procesado;
    }
}
