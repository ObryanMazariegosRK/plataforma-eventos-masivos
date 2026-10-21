<?php
namespace App\Application\UseCases\Pagos;

use App\Application\Abstractions\Pagos\IIniciarPagoUseCase;
use App\Application\DTOs\Pagos\IniciarPagoDTO;
use App\Application\DTOs\Pagos\PagoIniciadoDTO;
use App\Domain\Abstractions\Pagos\IPagoRepository;
use App\Domain\Abstractions\Pasarelas\IPasarelaPagoResolver;
use App\Domain\Abstractions\Pasarelas\IPasarelaRepository;
use App\Domain\Abstractions\Reservas\IReservaRepository;
use App\Domain\Entities\Pagos\Pago;
use App\Domain\Enums\Pagos\EstadoPago;
use App\Domain\Exceptions\Pagos\PasarelaNoDisponibleException;
use App\Domain\Exceptions\Pagos\ReservaNoEncontradaException;
use App\Domain\Exceptions\Pagos\ReservaNoPagableException;
use App\Domain\Exceptions\Pagos\ReservaYaPagadaException;
use DateTimeImmutable;

/**
 * Paso 1 del pago: registra un Pago PENDIENTE y pide a la pasarela una orden.
 * Aquí NO se cobra: solo se obtiene la URL donde la persona aprobará el pago.
 */
class IniciarPagoUseCase implements IIniciarPagoUseCase
{
    public function __construct(
        private IReservaRepository $reservas,
        private IPagoRepository $pagos,
        private IPasarelaRepository $pasarelas,
        private IPasarelaPagoResolver $resolver,
    ) {}

    public function execute(IniciarPagoDTO $dto): PagoIniciadoDTO
    {
        $reserva = $this->reservas->buscarPorId($dto->reservaId);

        // Reserva de otra persona → mismo error que inexistente (no revela que existe)
        if ($reserva === null || $reserva->getUsuarioId() !== $dto->usuarioId) {
            throw new ReservaNoEncontradaException();
        }
        if ($this->pagos->existeAprobadoParaReserva($reserva->getId())) {
            throw new ReservaYaPagadaException();
        }
        if (! $reserva->estaActiva() || $reserva->haVencido(new DateTimeImmutable())) {
            throw new ReservaNoPagableException();
        }

        $pasarela = $this->pasarelas->buscarActivaPorCodigo($dto->codigoPasarela)
            ?? throw new PasarelaNoDisponibleException();

        // Se guarda ANTES de hablar con la pasarela: si algo falla después, queda registro del intento.
        $pago = $this->pagos->guardar(new Pago(
            id: null,
            reservaId: $reserva->getId(),
            monto: $reserva->getTotal(),   // el monto sale de la reserva, nunca del cliente
            estado: EstadoPago::PENDIENTE,
            intento: $this->pagos->contarPorReserva($reserva->getId()) + 1,
            pasarelaId: $pasarela->getId(),
        ));

        $orden = $this->resolver->para($pasarela->getCodigo())->crearOrden(
            monto: $pago->getMonto(),
            moneda: $dto->moneda,
            referencia: 'pago-'.$pago->getId(),
            urlRetorno: $this->conPago($dto->urlRetorno, $pago->getId()),
            urlCancelacion: $this->conPago($dto->urlCancelacion, $pago->getId()),
            venceEn: $reserva->getExpiraEn(),   // la orden muere junto con la reserva
        );

        $pago->asignarOrdenExterna($orden->id);
        $this->pagos->guardar($pago);

        return new PagoIniciadoDTO($pago->getId(), $orden->urlAprobacion);
    }

    // La página de retorno necesita saber qué pago confirmar: /pagos/retorno?pago=15
    private function conPago(string $url, int $pagoId): string
    {
        return $url.(str_contains($url, '?') ? '&' : '?').'pago='.$pagoId;
    }
}
