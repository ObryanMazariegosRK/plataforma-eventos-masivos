<?php

namespace Tests\Unit\Inventario;

use App\Application\UseCases\Inventario\GenerarInventarioEventoUseCase;
use App\Domain\Exceptions\Inventario\LocalidadesSinPrecioException;
use App\Domain\Exceptions\Inventario\RecintoSinLugaresException;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\Pagos\InventarioRepositoryEnMemoria;

class GenerarInventarioEventoUseCaseTest extends TestCase
{
    public function test_genera_los_lugares_del_recinto(): void
    {
        $repo = new InventarioRepositoryEnMemoria();
        $repo->lugaresDelRecinto = 80;

        $this->assertSame(80, (new GenerarInventarioEventoUseCase($repo))->execute(7));
        $this->assertSame([7], $repo->generados);
    }

    public function test_es_idempotente(): void
    {
        $repo = new InventarioRepositoryEnMemoria();
        $repo->lugaresDelRecinto = 80;
        $useCase = new GenerarInventarioEventoUseCase($repo);

        $useCase->execute(7);

        $this->assertSame(0, $useCase->execute(7));
        $this->assertSame([7], $repo->generados, 'no debe generar dos veces');
    }

    public function test_exige_precio_en_todas_las_localidades(): void
    {
        $repo = new InventarioRepositoryEnMemoria();
        $repo->sinPrecio = ['Balcón', 'Platea'];

        try {
            (new GenerarInventarioEventoUseCase($repo))->execute(7);
            $this->fail('Debió lanzar excepción');
        } catch (LocalidadesSinPrecioException $e) {
            $this->assertStringContainsString('Balcón, Platea', $e->getMessage());
            $this->assertSame([], $repo->generados);
        }
    }

    public function test_rechaza_un_recinto_sin_lugares(): void
    {
        $repo = new InventarioRepositoryEnMemoria();
        $repo->lugaresDelRecinto = 0;

        $this->expectException(RecintoSinLugaresException::class);
        (new GenerarInventarioEventoUseCase($repo))->execute(7);
    }
}
