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

        // En las dos pestañas, la última compra primero (por cuándo se pagó; a igual fecha, la más nueva)
        $porCompra = fn (array $a, array $b) => [$b['pagado_en'] ?? '', $b['id']] <=> [$a['pagado_en'] ?? '', $a['id']];
        usort($proximas, $porCompra);
        usort($pasadas, $porCompra);

        return ['proximas' => $proximas, 'pasadas' => $pasadas];
    }
}
