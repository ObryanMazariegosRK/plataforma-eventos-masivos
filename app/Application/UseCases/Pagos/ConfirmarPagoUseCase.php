<?php
namespace App\Application\UseCases\Pagos;

use App\Application\Abstractions\Pagos\IConfirmarPagoUseCase;
use App\Application\DTOs\Pagos\ConfirmarPagoDTO;
use App\Domain\Abstractions\Inventario\IInventarioRepository;
use App\Domain\Abstractions\Pagos\IPagoRepository;
use App\Domain\Abstractions\Pagos\IProcesadorPostVenta;
use App\Domain\Abstractions\Pagos\IRegistroVenta;
use App\Domain\Abstractions\Pasarelas\IPasarelaPagoResolver;
use App\Domain\Abstractions\Pasarelas\IPasarelaRepository;
use App\Domain\Abstractions\Reservas\IReservaRepository;
use App\Domain\Abstractions\Shared\IUnidadDeTrabajo;
use App\Domain\Entities\Pagos\Pago;
use App\Domain\Exceptions\Pagos\PagoNoEncontradoException;
use App\Domain\Exceptions\Pagos\PagoRechazadoException;
use App\Domain\Exceptions\Pagos\PasarelaNoDisponibleException;
use DateTimeImmutable;

/**
 * Paso 3 del pago: la persona ya aprobó (o rechazó) en la pasarela; ahora se cobra.
 *
 * Todo ocurre en UNA transacción con la reserva BLOQUEADA, en este orden:
 *   1. se revisa que la reserva siga activa (si venció, no se cobra nada)
 *   2. se aplican los cambios: pago aprobado, reserva confirmada, asientos vendidos, venta registrada
 *   3. AL FINAL se cobra en la pasarela
 * Si el cobro se rechaza, se deshace el paso 2. Así nunca hay un cobro sin asientos,
 * ni asientos vendidos sin cobro.
 */
class ConfirmarPagoUseCase implements IConfirmarPagoUseCase
{
    public function __construct(
        private IPagoRepository $pagos,
        private IReservaRepository $reservas,
        private IInventarioRepository $inventario,
        private IPasarelaRepository $pasarelas,
        private IPasarelaPagoResolver $resolver,
        private IRegistroVenta $registroVenta,
        private IProcesadorPostVenta $postVenta,
        private IUnidadDeTrabajo $unidadDeTrabajo,
    ) {}

    public function execute(ConfirmarPagoDTO $dto): Pago
    {
        $pago = $this->pagos->buscarPorId($dto->pagoId);
        $reserva = $pago ? $this->reservas->buscarPorId($pago->getReservaId()) : null;

        // Pago de otra persona → mismo error que inexistente
        if ($pago === null || $reserva === null || $reserva->getUsuarioId() !== $dto->usuarioId) {
            throw new PagoNoEncontradoException();
        }

        // Idempotente: confirmar dos veces (doble clic, recargar la página) devuelve el mismo pago
        if ($pago->fueAprobado()) {
            return $pago;
        }
        if (! $pago->estaPendiente()) {
            throw new PagoRechazadoException('Este intento de pago ya fue rechazado. Inicia un nuevo pago.');
        }

        try {
            return $this->unidadDeTrabajo->ejecutar(fn () => $this->aprobarYCobrar($dto->pagoId));
        } catch (PagoRechazadoException $rechazo) {
            // La transacción se deshizo; en otra aparte se deja constancia del rechazo.
            $this->unidadDeTrabajo->ejecutar(fn () => $this->marcarRechazado($dto->pagoId));

            throw $rechazo;
        }
    }

    private function aprobarYCobrar(int $pagoId): Pago
    {
        // Se vuelve a leer TODO con bloqueo: entre la primera lectura y ahora pudo cambiar
        // (otra pestaña confirmando el mismo pago, o el proceso que vence reservas).
        $pago = $this->pagos->buscarPorIdParaActualizar($pagoId);
        if ($pago->fueAprobado()) {
            return $pago;
        }

        $reserva = $this->reservas->buscarPorIdParaActualizar($pago->getReservaId());
        if (! $reserva->estaActiva() || $reserva->haVencido(new DateTimeImmutable())) {
            throw new PagoRechazadoException('Tu reserva venció antes de completar el pago. No se realizó ningún cobro.');
        }

        $codigoPasarela = $this->pasarelas->buscarPorId($pago->getPasarelaId())?->getCodigo()
            ?? throw new PasarelaNoDisponibleException();

        // ---- cambios (se deshacen si el cobro falla) ----
        $reserva->confirmar();
        $this->reservas->guardar($reserva);

        // TODO: cuando Reservas asigne asientos, exigir que la reserva tenga al menos uno.
        foreach ($this->inventario->buscarPorReserva($reserva->getId(), bloquear: true) as $item) {
            $item->vender();
            $this->inventario->guardar($item);
        }

        // ---- el cobro, lo último ----
        $captura = $this->resolver->para($codigoPasarela)->capturar($pago->getOrdenExterna());
        if (! $captura->aprobado) {
            throw new PagoRechazadoException($captura->motivo);
        }

        $pago->aprobar($captura->referencia);
        $pago = $this->pagos->guardar($pago);

        $this->registroVenta->registrar($pago, $reserva, $codigoPasarela);
        $this->postVenta->programar($pago->getId());   // boletos + correo, después del commit

        return $pago;
    }

    private function marcarRechazado(int $pagoId): void
    {
        $pago = $this->pagos->buscarPorIdParaActualizar($pagoId);
        if ($pago->estaPendiente()) {
            $pago->rechazar();
            $this->pagos->guardar($pago);
        }
    }
}
