<?php
namespace App\Application\UseCases\Reservas;

use App\Application\Abstractions\Reservas\IListarMisComprasUseCase;
use App\Domain\Abstractions\Reservas\IReservaRepository;
use DateTimeImmutable;

class ListarMisComprasUseCase implements IListarMisComprasUseCase
{
    public function __construct(private IReservaRepository $reservas) {}

    public function execute(int $usuarioId, DateTimeImmutable $ahora): array
    {
        $proximas = [];
        $pasadas = [];

        foreach ($this->reservas->comprasDeUsuario($usuarioId) as $compra) {
            unset($compra['usuario_id']);
            $fecha = new DateTimeImmutable($compra['evento']['fecha_evento']);
            $fecha >= $ahora ? $proximas[] = $compra : $pasadas[] = $compra;
        }

        // Próximas: la más cercana primero. Pasadas: la más reciente primero.
        $porFecha = fn (array $a, array $b) => strcmp($a['evento']['fecha_evento'], $b['evento']['fecha_evento']);
        usort($proximas, $porFecha);
        usort($pasadas, fn ($a, $b) => $porFecha($b, $a));

        return ['proximas' => $proximas, 'pasadas' => $pasadas];
    }
}
