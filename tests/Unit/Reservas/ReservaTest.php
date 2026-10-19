<?php

namespace Tests\Unit\Reservas;

use App\Domain\Entities\Reservas\Reserva;
use App\Domain\Enums\Reservas\EstadoReserva;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\TestCase;

class ReservaTest extends TestCase
{

    public function test_crea_una_reserva_activa_que_vence_al_terminar_el_ttl(): void
    {
        $ahora = new DateTimeImmutable();
        $expiraEn = $ahora->modify('+300 seconds');

        $reserva = new Reserva(
            id: null,
            usuarioId: 1,
            eventoId: 2,
            estado: EstadoReserva::ACTIVA,
            expiraEn: $expiraEn,
            total: 150.50
        );

        $this->assertNull($reserva->getId());
        $this->assertSame(EstadoReserva::ACTIVA, $reserva->getEstado());
        $this->assertSame(1, $reserva->getUsuarioId());
        $this->assertSame(2, $reserva->getEventoId());
        $this->assertSame(150.50, $reserva->getTotal());
        $this->assertSame($expiraEn, $reserva->getExpiraEn());

        // Antes de los cinco minutos, la reserva sigue vigente.
        $this->assertFalse($reserva->haVencido($ahora));

        // Exactamente al cumplirse el TTL, ya ha vencido.
        $this->assertTrue($reserva->haVencido($expiraEn));
    }

    public function test_rechaza_un_total_negativo(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'El total de la reserva no puede ser negativo.'
        );

        new Reserva(
            id: null,
            usuarioId: 1,
            eventoId: 2,
            estado: EstadoReserva::ACTIVA,
            expiraEn: (new DateTimeImmutable())->modify('+300 seconds'),
            total: -1
        );
    }

}
