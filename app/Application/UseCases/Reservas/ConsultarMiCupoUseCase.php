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
        // Los lugares apartados sin pagar cuentan para el máximo mientras no vencen: se devuelven
        // aparte para que la persona pueda continuar el pago o liberarlos
        $pendientes = $this->reservas->apartadasSinPagar($usuarioId, $eventoId, $ahora);
        $apartados = array_sum(array_column($pendientes, 'lugares'));
        $utc = new \DateTimeZone('UTC');

        return [
            'maximo'      => $maximo,
            'usados'      => $usados,
            'disponibles' => max(0, $maximo - $usados),
            'pagados'     => max(0, $usados - $apartados),
            'apartados'   => $apartados,
            'pendientes'  => array_map(fn (array $p) => [
                ...$p,
                'expira_en' => (new DateTimeImmutable($p['expira_en'], $utc))->format(DATE_ATOM),
            ], $pendientes),
        ];
    }
}
