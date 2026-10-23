<?php

namespace Tests\Unit\Reservas;

use App\Application\UseCases\Reservas\ListarMisComprasUseCase;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\Pagos\ReservaRepositoryEnMemoria;

class ListarMisComprasUseCaseTest extends TestCase
{
    private function compra(int $id, int $usuarioId, string $fecha, string $estado = 'confirmada', ?string $pagadoEn = null): array
    {
        return [
            'id' => $id, 'usuario_id' => $usuarioId, 'estado' => $estado, 'total' => 100.0,
            'evento' => ['id' => 1, 'nombre' => "Evento {$id}", 'fecha_evento' => $fecha, 'recinto' => 'Teatro'],
            'lugares' => [], 'pagado_en' => $pagadoEn, 'referencia' => null,
        ];
    }

    public function test_separa_proximas_y_pasadas_con_la_ultima_compra_primero(): void
    {
        $reservas = new ReservaRepositoryEnMemoria();
        $reservas->detalles = [
            1 => $this->compra(1, 7, '2026-12-20T20:00:00+00:00', pagadoEn: '2026-10-18T10:00:00+00:00'),
            2 => $this->compra(2, 7, '2026-11-01T20:00:00+00:00', pagadoEn: '2026-10-05T10:00:00+00:00'),
            3 => $this->compra(3, 7, '2026-09-01T20:00:00+00:00', pagadoEn: '2026-08-30T10:00:00+00:00'),
            4 => $this->compra(4, 7, '2026-10-01T20:00:00+00:00', pagadoEn: '2026-08-01T10:00:00+00:00'),
            5 => $this->compra(5, 8, '2026-11-15T20:00:00+00:00'),               // de otra persona
            6 => $this->compra(6, 7, '2026-11-20T20:00:00+00:00', 'activa'),     // sin pagar
        ];

        $resultado = (new ListarMisComprasUseCase($reservas))
            ->execute(7, new DateTimeImmutable('2026-10-20T12:00:00+00:00'));

        // en las dos pestañas, la última compra (por fecha de pago) primero
        $this->assertSame([1, 2], array_column($resultado['proximas'], 'id'));
        $this->assertSame([3, 4], array_column($resultado['pasadas'], 'id'));
        $this->assertArrayNotHasKey('usuario_id', $resultado['proximas'][0]);
    }
}
