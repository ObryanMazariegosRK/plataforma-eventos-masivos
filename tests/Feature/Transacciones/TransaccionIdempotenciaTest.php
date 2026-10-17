<?php

namespace Tests\Feature\Transacciones;

use App\Application\UseCases\Transacciones\RegistrarTransaccionUseCase;
use App\Domain\Enums\Auth\EstadoUsuario;
use App\Domain\Enums\Auth\RolUsuario;
use App\Domain\Enums\Transacciones\TipoTransaccion;
use App\Models\Auth\Usuario;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransaccionIdempotenciaTest extends TestCase
{
    use RefreshDatabase;

    public function test_reintento_identico_no_duplica_venta(): void
    {
        $usuario = $this->crearUsuario();

        $registrador = app(RegistrarTransaccionUseCase::class);

        $datos = [
            'tipo' => TipoTransaccion::VENTA,
            'monto' => 150.00,
            'usuarioId' => $usuario->id,
            'referencia' => 'VENTA-100',
            'descripcion' => 'Venta confirmada',
            'registradoPor' => $usuario->id,
        ];

        $primera = $registrador->execute(...$datos);
        $segunda = $registrador->execute(...$datos);

        $this->assertSame(
            $primera->getId(),
            $segunda->getId()
        );

        $this->assertDatabaseCount('transacciones', 1);
    }

    public function test_rechaza_referencia_con_monto_diferente(): void
    {
        $usuario = $this->crearUsuario();

        $registrador = app(RegistrarTransaccionUseCase::class);

        $registrador->execute(
            tipo: TipoTransaccion::VENTA,
            monto: 150.00,
            usuarioId: $usuario->id,
            referencia: 'VENTA-200'
        );

        try {
            $registrador->execute(
                tipo: TipoTransaccion::VENTA,
                monto: 300.00,
                usuarioId: $usuario->id,
                referencia: 'VENTA-200'
            );

            $this->fail(
                'Se aceptó una referencia con diferente monto.'
            );

        } catch (DomainException $e) {
            $this->assertSame(
                'La referencia pertenece a otra transacción.',
                $e->getMessage()
            );
        }

        $this->assertDatabaseCount('transacciones', 1);
    }

    public function test_rechaza_transaccion_sin_referencia(): void
    {
        $usuario = $this->crearUsuario();

        $registrador = app(RegistrarTransaccionUseCase::class);

        try {
            $registrador->execute(
                tipo: TipoTransaccion::VENTA,
                monto: 100.00,
                usuarioId: $usuario->id
            );

            $this->fail(
                'Se aceptó una transacción sin referencia.'
            );

        } catch (DomainException $e) {
            $this->assertSame(
                'La referencia de la transacción es obligatoria y debe ser válida.',
                $e->getMessage()
            );
        }

        $this->assertDatabaseCount('transacciones', 0);
    }

    public function test_venta_y_reembolso_pueden_compartir_referencia(): void
    {
        $usuario = $this->crearUsuario();

        $registrador = app(RegistrarTransaccionUseCase::class);

        $venta = $registrador->execute(
            tipo: TipoTransaccion::VENTA,
            monto: 150.00,
            usuarioId: $usuario->id,
            referencia: 'OPERACION-300'
        );

        $reembolso = $registrador->execute(
            tipo: TipoTransaccion::REEMBOLSO,
            monto: 150.00,
            usuarioId: $usuario->id,
            referencia: 'OPERACION-300'
        );

        $this->assertNotEquals(
            $venta->getId(),
            $reembolso->getId()
        );

        $this->assertDatabaseCount('transacciones', 2);
    }

    private function crearUsuario(): Usuario
    {
        return Usuario::create([
            'nombre' => 'Cliente',
            'apellido' => 'Prueba',
            'email' => 'cliente-idempotencia@test.com',
            'password' => bcrypt('Secreto#123'),
            'rol' => RolUsuario::CLIENTE,
            'estado' => EstadoUsuario::ACTIVO,
            'email_verified_at' => now(),
        ]);
    }
}
