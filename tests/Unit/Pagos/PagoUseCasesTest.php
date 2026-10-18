<?php

namespace Tests\Unit\Pagos;

use App\Application\DTOs\Pagos\ConfirmarPagoDTO;
use App\Application\DTOs\Pagos\IniciarPagoDTO;
use App\Application\UseCases\Pagos\ConfirmarPagoUseCase;
use App\Application\UseCases\Pagos\IniciarPagoUseCase;
use App\Domain\Entities\Inventario\ItemInventario;
use App\Domain\Entities\Pasarelas\Pasarela;
use App\Domain\Entities\Reservas\Reserva;
use App\Domain\Enums\Inventario\EstadoAsiento;
use App\Domain\Enums\Pagos\EstadoPago;
use App\Domain\Enums\Reservas\EstadoReserva;
use App\Domain\Exceptions\Pagos\PagoNoEncontradoException;
use App\Domain\Exceptions\Pagos\PagoRechazadoException;
use App\Domain\Exceptions\Pagos\PasarelaNoDisponibleException;
use App\Domain\Exceptions\Pagos\ReservaNoEncontradaException;
use App\Domain\Exceptions\Pagos\ReservaNoPagableException;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\Pagos\InventarioRepositoryEnMemoria;
use Tests\Fakes\Pagos\PagoRepositoryEnMemoria;
use Tests\Fakes\Pagos\PasarelaPagoFalsa;
use Tests\Fakes\Pagos\PasarelaRepositoryEnMemoria;
use Tests\Fakes\Pagos\RegistroVentaFalso;
use Tests\Fakes\Pagos\ReservaRepositoryEnMemoria;
use Tests\Fakes\Pagos\UnidadDeTrabajoEnMemoria;

class PagoUseCasesTest extends TestCase
{
    private const DUENO = 7;

    private PagoRepositoryEnMemoria $pagos;
    private ReservaRepositoryEnMemoria $reservas;
    private InventarioRepositoryEnMemoria $inventario;
    private PasarelaRepositoryEnMemoria $pasarelas;
    private PasarelaPagoFalsa $pasarela;
    private RegistroVentaFalso $venta;
    private IniciarPagoUseCase $iniciar;
    private ConfirmarPagoUseCase $confirmar;

    protected function setUp(): void
    {
        $this->pagos = new PagoRepositoryEnMemoria();
        $this->reservas = new ReservaRepositoryEnMemoria();
        $this->inventario = new InventarioRepositoryEnMemoria();
        $this->pasarelas = new PasarelaRepositoryEnMemoria();
        $this->pasarela = new PasarelaPagoFalsa();
        $this->venta = new RegistroVentaFalso();

        $this->pasarelas->agregar(new Pasarela(null, 'Simulada', 'simulada'));

        $this->iniciar = new IniciarPagoUseCase($this->reservas, $this->pagos, $this->pasarelas, $this->pasarela);
        $this->confirmar = new ConfirmarPagoUseCase(
            $this->pagos, $this->reservas, $this->inventario, $this->pasarelas, $this->pasarela,
            $this->venta, $this->venta,
            new UnidadDeTrabajoEnMemoria([$this->pagos, $this->reservas, $this->inventario]),
        );
    }

    // ---------- Iniciar ----------

    public function test_iniciar_crea_un_pago_pendiente_por_el_total_de_la_reserva(): void
    {
        $this->crearReserva(total: 300.0);

        $resultado = $this->iniciar->execute($this->dtoIniciar());

        $pago = $this->pagos->buscarPorId($resultado->pagoId);
        $this->assertSame(EstadoPago::PENDIENTE, $pago->getEstado());
        $this->assertSame(300.0, $pago->getMonto());
        $this->assertSame('ORD-1', $pago->getOrdenExterna());
        $this->assertSame('https://pasarela.test/aprobar/ORD-1', $resultado->urlAprobacion);
        $this->assertStringEndsWith('?pago='.$resultado->pagoId, $this->pasarela->ordenes[0]['urlRetorno']);
    }

    public function test_iniciar_cuenta_los_intentos(): void
    {
        $this->crearReserva();

        $this->iniciar->execute($this->dtoIniciar());
        $segundo = $this->iniciar->execute($this->dtoIniciar());

        $this->assertSame(2, $this->pagos->buscarPorId($segundo->pagoId)->getIntento());
    }

    public function test_iniciar_rechaza_la_reserva_de_otra_persona(): void
    {
        $this->crearReserva(usuarioId: 99);

        $this->expectException(ReservaNoEncontradaException::class);
        $this->iniciar->execute($this->dtoIniciar());
    }

    public function test_iniciar_rechaza_una_reserva_vencida(): void
    {
        $this->crearReserva(expiraEn: new DateTimeImmutable('-1 minute'));

        $this->expectException(ReservaNoPagableException::class);
        $this->iniciar->execute($this->dtoIniciar());
    }

    public function test_iniciar_rechaza_una_pasarela_inexistente(): void
    {
        $this->crearReserva();

        $this->expectException(PasarelaNoDisponibleException::class);
        $this->iniciar->execute($this->dtoIniciar(pasarela: 'bitcoin'));
    }

    // ---------- Confirmar ----------

    public function test_confirmar_aprobado_confirma_la_reserva_vende_los_asientos_y_dispara_la_postventa(): void
    {
        $reserva = $this->crearReserva();
        $this->crearAsientos($reserva, 2);
        $pagoId = $this->iniciar->execute($this->dtoIniciar())->pagoId;

        $pago = $this->confirmar->execute(new ConfirmarPagoDTO(self::DUENO, $pagoId));

        $this->assertSame(EstadoPago::APROBADO, $pago->getEstado());
        $this->assertSame('CAP-ORD-1', $pago->getReferenciaExterna());
        $this->assertSame(EstadoReserva::CONFIRMADA, $this->reservas->buscarPorId($reserva->getId())->getEstado());
        foreach ($this->inventario->buscarPorReserva($reserva->getId()) as $item) {
            $this->assertSame(EstadoAsiento::VENDIDO, $item->getEstado());
        }
        $this->assertSame([$pagoId], $this->venta->ventasRegistradas);
        $this->assertSame([$pagoId], $this->venta->postVentasProgramadas);
    }

    public function test_confirmar_rechazado_deshace_todo_y_deja_el_pago_rechazado(): void
    {
        $reserva = $this->crearReserva();
        $this->crearAsientos($reserva, 2);
        $pagoId = $this->iniciar->execute($this->dtoIniciar())->pagoId;
        $this->pasarela->aprobar = false;

        try {
            $this->confirmar->execute(new ConfirmarPagoDTO(self::DUENO, $pagoId));
            $this->fail('Debió lanzar excepción');
        } catch (PagoRechazadoException $e) {
            $this->assertSame('Fondos insuficientes.', $e->getMessage());
        }

        $this->assertSame(EstadoPago::RECHAZADO, $this->pagos->buscarPorId($pagoId)->getEstado());
        // la reserva sigue ACTIVA (puede reintentar) y los asientos siguen BLOQUEADOS
        $this->assertSame(EstadoReserva::ACTIVA, $this->reservas->buscarPorId($reserva->getId())->getEstado());
        foreach ($this->inventario->buscarPorReserva($reserva->getId()) as $item) {
            $this->assertSame(EstadoAsiento::BLOQUEADO, $item->getEstado());
        }
        $this->assertSame([], $this->venta->ventasRegistradas);
    }

    public function test_si_la_reserva_vencio_no_se_cobra(): void
    {
        $reserva = $this->crearReserva();
        $pagoId = $this->iniciar->execute($this->dtoIniciar())->pagoId;

        // vence mientras la persona está en la pasarela
        $vencida = $this->reservas->buscarPorId($reserva->getId());
        $vencida->vencer();
        $this->reservas->guardar($vencida);

        $this->expectException(PagoRechazadoException::class);
        try {
            $this->confirmar->execute(new ConfirmarPagoDTO(self::DUENO, $pagoId));
        } finally {
            $this->assertSame([], $this->pasarela->capturas, 'no debe cobrar una reserva vencida');
        }
    }

    public function test_confirmar_dos_veces_no_cobra_dos_veces(): void
    {
        $this->crearReserva();
        $pagoId = $this->iniciar->execute($this->dtoIniciar())->pagoId;

        $this->confirmar->execute(new ConfirmarPagoDTO(self::DUENO, $pagoId));
        $segunda = $this->confirmar->execute(new ConfirmarPagoDTO(self::DUENO, $pagoId));

        $this->assertSame(EstadoPago::APROBADO, $segunda->getEstado());
        $this->assertCount(1, $this->pasarela->capturas);
    }

    public function test_no_se_puede_confirmar_el_pago_de_otra_persona(): void
    {
        $this->crearReserva();
        $pagoId = $this->iniciar->execute($this->dtoIniciar())->pagoId;

        $this->expectException(PagoNoEncontradoException::class);
        $this->confirmar->execute(new ConfirmarPagoDTO(99, $pagoId));
    }

    // ---------- Ayudantes ----------

    private function dtoIniciar(string $pasarela = 'simulada'): IniciarPagoDTO
    {
        return new IniciarPagoDTO(self::DUENO, 1, $pasarela, 'GTQ', 'https://app.test/pagos/retorno', 'https://app.test/pagos/cancelado');
    }

    private function crearReserva(int $usuarioId = self::DUENO, float $total = 250.0, ?DateTimeImmutable $expiraEn = null): Reserva
    {
        return $this->reservas->guardar(new Reserva(
            id: null,
            usuarioId: $usuarioId,
            eventoId: 3,
            estado: EstadoReserva::ACTIVA,
            expiraEn: $expiraEn ?? new DateTimeImmutable('+5 minutes'),
            total: $total,
        ));
    }

    private function crearAsientos(Reserva $reserva, int $cantidad): void
    {
        for ($i = 0; $i < $cantidad; $i++) {
            $this->inventario->guardar(new ItemInventario(
                id: null,
                eventoId: 3,
                localidadId: 1,
                asientoId: null,
                estado: EstadoAsiento::BLOQUEADO,
                reservaId: $reserva->getId(),
                bloqueadoHasta: new DateTimeImmutable('+5 minutes'),
            ));
        }
    }
}
