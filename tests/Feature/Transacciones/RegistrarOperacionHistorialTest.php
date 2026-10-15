<?php

namespace Tests\Feature\Transacciones;

use App\Application\UseCases\Transacciones\RegistrarOperacionHistorialUseCase;
use App\Domain\Enums\Transacciones\TipoOperacionHistorial;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RegistrarOperacionHistorialTest extends TestCase
{
    use RefreshDatabase;

    private RegistrarOperacionHistorialUseCase $registrador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registrador = app(
            RegistrarOperacionHistorialUseCase::class
        );
    }

    public function test_registra_operacion_correctamente(): void
    {
        $operacion = $this->registrador->execute(
            tipo: TipoOperacionHistorial::RESERVA_CREADA,
            claveIdempotencia: 'prueba-reserva-100',
            descripcion: 'Reserva creada correctamente',
            datos: ['origen' => 'sistema']
        );

        $this->assertNotNull($operacion->id);

        $this->assertDatabaseHas('historial_operaciones', [
            'id' => $operacion->id,
            'tipo' => 'reserva_creada',
            'clave_idempotencia' => 'prueba-reserva-100',
        ]);

        $this->assertDatabaseCount('historial_operaciones', 1);
    }

    public function test_reintento_identico_no_duplica_operacion(): void
    {
        $datos = [
            'tipo' => TipoOperacionHistorial::VENTA_CONFIRMADA,
            'claveIdempotencia' => 'prueba-venta-200',
            'descripcion' => 'Venta confirmada',
            'datos' => ['referencia' => 'TEST-200'],
        ];

        $primera = $this->registrador->execute(...$datos);
        $segunda = $this->registrador->execute(...$datos);

        $this->assertEquals(
            $primera->id,
            $segunda->id
        );

        $this->assertDatabaseCount('historial_operaciones', 1);
    }

    public function test_rechaza_clave_reutilizada_con_otros_datos(): void
    {
        $this->registrador->execute(
            tipo: TipoOperacionHistorial::RESERVA_CREADA,
            claveIdempotencia: 'prueba-operacion-300',
            descripcion: 'Reserva original'
        );

        try {
            $this->registrador->execute(
                tipo: TipoOperacionHistorial::RESERVA_CANCELADA,
                claveIdempotencia: 'prueba-operacion-300',
                descripcion: 'Operación diferente'
            );

            $this->fail(
                'El sistema aceptó una clave para operaciones diferentes.'
            );

        } catch (DomainException $e) {
            $this->assertSame(
                'La clave pertenece a otra operación.',
                $e->getMessage()
            );
        }

        $this->assertDatabaseCount('historial_operaciones', 1);
    }

    public function test_rechaza_clave_vacia(): void
    {
        $this->expectException(DomainException::class);

        $this->registrador->execute(
            tipo: TipoOperacionHistorial::RESERVA_CREADA,
            claveIdempotencia: '   '
        );
    }
}
