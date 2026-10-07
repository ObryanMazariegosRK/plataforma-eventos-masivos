<?php

namespace Tests\Unit\Reservas;

use App\Application\DTOs\Reservas\CrearReservaDTO;
use App\Application\UseCases\Reservas\CrearReservaUseCase;
use App\Domain\Abstractions\Reservas\IReservaRepository;
use App\Domain\Entities\Reservas\Reserva;
use App\Domain\Enums\Reservas\EstadoReserva;
use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\TestCase;

class CrearReservaUseCaseTest extends TestCase
{
    public function test_crea_una_reserva_activa_que_vence_al_terminar_el_ttl(): void
    {
        $repo = $this->repositorioEnMemoria();
        $useCase = new CrearReservaUseCase($repo);

        $antes = new DateTimeImmutable();
        $reserva = $useCase->execute(new CrearReservaDTO(usuarioId: 1, eventoId: 2, total: 150.50, ttlSegundos: 300));
        $despues = new DateTimeImmutable();

        $this->assertSame(1, $reserva->getId());
        $this->assertSame(EstadoReserva::ACTIVA, $reserva->getEstado());
        $this->assertSame(1, $reserva->getUsuarioId());
        $this->assertSame(2, $reserva->getEventoId());
        $this->assertSame(150.50, $reserva->getTotal());
        $this->assertGreaterThanOrEqual($antes->modify('+300 seconds'), $reserva->getExpiraEn());
        $this->assertLessThanOrEqual($despues->modify('+300 seconds'), $reserva->getExpiraEn());
        $this->assertNotNull($repo->buscarPorId(1));
    }

    public function test_rechaza_un_total_negativo(): void
    {
        $repo = $this->repositorioEnMemoria();
        $useCase = new CrearReservaUseCase($repo);

        $this->expectException(DomainException::class);

        $useCase->execute(new CrearReservaDTO(usuarioId: 1, eventoId: 2, total: -1, ttlSegundos: 300));
    }

    private function repositorioEnMemoria(): IReservaRepository
    {
        return new class implements IReservaRepository
        {
            /** @var array<int, Reserva> */
            private array $reservas = [];

            public function guardar(Reserva $reserva): Reserva
            {
                $id = $reserva->getId() ?? count($this->reservas) + 1;

                return $this->reservas[$id] = new Reserva(
                    id: $id,
                    usuarioId: $reserva->getUsuarioId(),
                    eventoId: $reserva->getEventoId(),
                    estado: $reserva->getEstado(),
                    expiraEn: $reserva->getExpiraEn(),
                    total: $reserva->getTotal(),
                );
            }

            public function buscarPorId(int $id): ?Reserva
            {
                return $this->reservas[$id] ?? null;
            }
        };
    }
}
