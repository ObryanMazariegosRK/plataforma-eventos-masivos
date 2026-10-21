<?php
namespace App\Application\UseCases\Reservas;

use App\Application\Abstractions\Reservas\IConsultarMiCupoUseCase;
use App\Domain\Abstractions\Reservas\IReservaRepository;
use DateTimeImmutable;

class ConsultarMiCupoUseCase implements IConsultarMiCupoUseCase
{
    public function __construct(private IReservaRepository $reservas) {}

    public function execute(int $usuarioId, int $eventoId, int $maximo, DateTimeImmutable $ahora): array
    {
        $usados = $this->reservas->lugaresDePersonaEnEvento($usuarioId, $eventoId, $ahora);

        return [
            'maximo'      => $maximo,
            'usados'      => $usados,
            'disponibles' => max(0, $maximo - $usados),
        ];
    }
}
