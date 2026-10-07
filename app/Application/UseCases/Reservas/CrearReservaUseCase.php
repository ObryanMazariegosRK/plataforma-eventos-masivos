<?php
namespace App\Application\UseCases\Reservas;

use App\Application\Abstractions\Reservas\ICrearReservaUseCase;
use App\Application\DTOs\Reservas\CrearReservaDTO;
use App\Domain\Abstractions\Reservas\IReservaRepository;
use App\Domain\Entities\Reservas\Reserva;
use App\Domain\Enums\Reservas\EstadoReserva;
use DateTimeImmutable;

class CrearReservaUseCase implements ICrearReservaUseCase
{
    public function __construct(
        private IReservaRepository $reservas,
    ) {}

    public function execute(CrearReservaDTO $dto): Reserva
    {
        // Toda reserva nace ACTIVA y vence al terminar su TTL.
        $reserva = new Reserva(
            id: null,
            usuarioId: $dto->usuarioId,
            eventoId: $dto->eventoId,
            estado: EstadoReserva::ACTIVA,
            expiraEn: (new DateTimeImmutable())->modify("+{$dto->ttlSegundos} seconds"),
            total: $dto->total,
        );

        // TODO (paso 2): bloquear asientos de inventario (SELECT ... FOR UPDATE)
        // dentro de una transacción y calcular el total desde 'precios'.
        return $this->reservas->guardar($reserva);
    }
}
