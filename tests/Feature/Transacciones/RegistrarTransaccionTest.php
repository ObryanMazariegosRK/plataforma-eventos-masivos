<?php

namespace Tests\Feature\Transacciones;

use App\Application\UseCases\Transacciones\RegistrarTransaccionUseCase;
use App\Domain\Abstractions\Transacciones\ITransaccionRepository;
use App\Domain\Entities\Transacciones\Transaccion;
use App\Domain\Enums\Auth\EstadoUsuario;
use App\Domain\Enums\Auth\RolUsuario;
use App\Domain\Enums\Transacciones\TipoTransaccion;
use App\Models\Auth\Usuario;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrarTransaccionTest extends TestCase
{
    use RefreshDatabase;

    public function test_registra_una_venta_correctamente(): void
    {
        $usuario = $this->crearUsuario();

        $casoUso = app(RegistrarTransaccionUseCase::class);

        $transaccion = $casoUso->execute(
            tipo: TipoTransaccion::VENTA,
            monto: 150.00,
            usuarioId: $usuario->id,
            referencia: 'VENTA-PRUEBA-001',
            descripcion: 'Venta de boleto de prueba',
            registradoPor: $usuario->id
        );

        $this->assertNotNull($transaccion->getId());

        $this->assertDatabaseHas('transacciones', [
            'id' => $transaccion->getId(),
            'tipo' => 'venta',
            'monto' => 150.00,
            'usuario_id' => $usuario->id,
            'referencia' => 'VENTA-PRUEBA-001',
            'created_by' => $usuario->id,
        ]);

        $this->assertDatabaseCount('transacciones', 1);
    }

    public function test_registra_un_reembolso_correctamente(): void
    {
        $usuario = $this->crearUsuario();

        $casoUso = app(RegistrarTransaccionUseCase::class);

        $transaccion = $casoUso->execute(
            tipo: TipoTransaccion::REEMBOLSO,
            monto: 50.00,
            usuarioId: $usuario->id,
            referencia: 'REEMBOLSO-PRUEBA-001',
            descripcion: 'Reembolso de prueba',
            registradoPor: $usuario->id
        );

        $this->assertNotNull($transaccion->getId());

        $this->assertDatabaseHas('transacciones', [
            'tipo' => 'reembolso',
            'monto' => 50.00,
            'usuario_id' => $usuario->id,
            'referencia' => 'REEMBOLSO-PRUEBA-001',
        ]);

        $this->assertDatabaseCount('transacciones', 1);
    }

    public function test_rechaza_transacciones_con_monto_cero(): void
    {
        $usuario = $this->crearUsuario();

        $casoUso = app(RegistrarTransaccionUseCase::class);

        try {
            $casoUso->execute(
                tipo: TipoTransaccion::VENTA,
                monto: 0,
                usuarioId: $usuario->id
            );

            $this->fail(
                'El sistema permitió registrar una transacción sin monto.'
            );

        } catch (DomainException $e) {
            $this->assertSame(
                'El monto de la transacción debe ser mayor a cero.',
                $e->getMessage()
            );
        }

        $this->assertDatabaseCount('transacciones', 0);
    }

    public function test_no_permite_guardar_una_transaccion_existente(): void
    {
        $usuario = $this->crearUsuario();

        $repositorio = app(ITransaccionRepository::class);

        $transaccion = new Transaccion(
            id: 999,
            tipo: TipoTransaccion::VENTA,
            monto: 100.00,
            usuarioId: $usuario->id
        );

        $this->expectException(DomainException::class);

        try {
            $repositorio->registrar($transaccion);
        } finally {
            $this->assertDatabaseCount('transacciones', 0);
        }
    }

    private function crearUsuario(): Usuario
    {
        return Usuario::create([
            'nombre' => 'Cliente',
            'apellido' => 'Prueba',
            'email' => 'cliente-transaccion@test.com',
            'password' => bcrypt('Secreto#123'),
            'rol' => RolUsuario::CLIENTE,
            'estado' => EstadoUsuario::ACTIVO,
            'email_verified_at' => now(),
        ]);
    }
}
