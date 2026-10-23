<?php

namespace Tests\Feature\Reembolsos;

use App\Application\UseCases\Reembolsos\ResolverSolicitudReembolsoUseCase;
use App\Domain\Enums\Auth\EstadoUsuario;
use App\Domain\Enums\Auth\RolUsuario;
use App\Domain\Enums\Reembolsos\EstadoReembolso;
use App\Models\Auth\Usuario;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ResolverSolicitudReembolsoTest extends TestCase
{
    use RefreshDatabase;

    private function escenario(
        string $estadoPago = 'aprobado',
        float $montoReembolso = 400
    ): array {
        $cliente = $this->crearUsuario(
            'cliente-resolver@test.com',
            RolUsuario::CLIENTE
        );

        $admin = $this->crearUsuario(
            'admin-resolver@test.com',
            RolUsuario::ADMIN
        );

        $recintoId = DB::table('recintos')->insertGetId([
            'nombre' => 'Recinto de Prueba',
            'direccion' => 'Zona 1',
            'ciudad' => 'Guatemala',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $eventoId = DB::table('eventos')->insertGetId([
            'recinto_id' => $recintoId,
            'nombre' => 'Evento de Reembolsos',
            'fecha_evento' => now()->addDays(10),
            'estado' => 'publicado',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $reservaId = DB::table('reservas')->insertGetId([
            'usuario_id' => $cliente->id,
            'evento_id' => $eventoId,
            'estado' => 'confirmada',
            'expira_en' => now()->addMinutes(5),
            'total' => 500,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $pagoId = DB::table('pagos')->insertGetId([
            'reserva_id' => $reservaId,
            'monto' => 500,
            'estado' => $estadoPago,
            'intento' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $reembolsoId = DB::table('reembolsos')->insertGetId([
            'pago_id' => $pagoId,
            'monto' => $montoReembolso,
            'estado' => 'solicitado',
            'motivo' => 'No podré asistir',
            'created_by' => $cliente->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'cliente' => $cliente,
            'admin' => $admin,
            'pago_id' => $pagoId,
            'reserva_id' => $reservaId,
            'reembolso_id' => $reembolsoId,
        ];
    }

    public function test_aprueba_solicitud_correctamente(): void
    {
        $datos = $this->escenario();

        $reembolso = app(ResolverSolicitudReembolsoUseCase::class)
            ->execute(
                $datos['reembolso_id'],
                'aprobar',
                $datos['admin']->id
            );

        $this->assertSame(
            EstadoReembolso::APROBADO,
            $reembolso->getEstado()
        );

        $this->assertDatabaseHas('reembolsos', [
            'id' => $datos['reembolso_id'],
            'estado' => 'aprobado',
            'monto' => 400,
            'updated_by' => $datos['admin']->id,
        ]);

        // Aprobar no significa devolver dinero.
        $this->assertDatabaseHas('pagos', [
            'id' => $datos['pago_id'],
            'estado' => 'aprobado',
        ]);

        $this->assertDatabaseHas('reservas', [
            'id' => $datos['reserva_id'],
            'estado' => 'confirmada',
        ]);
    }

    public function test_rechaza_solicitud_correctamente(): void
    {
        $datos = $this->escenario();

        $reembolso = app(ResolverSolicitudReembolsoUseCase::class)
            ->execute(
                $datos['reembolso_id'],
                'rechazar',
                $datos['admin']->id
            );

        $this->assertSame(
            EstadoReembolso::RECHAZADO,
            $reembolso->getEstado()
        );

        $this->assertDatabaseHas('reembolsos', [
            'id' => $datos['reembolso_id'],
            'estado' => 'rechazado',
            'updated_by' => $datos['admin']->id,
        ]);
    }

    public function test_no_permite_aprobar_dos_veces(): void
    {
        $datos = $this->escenario();

        $casoUso = app(ResolverSolicitudReembolsoUseCase::class);

        $casoUso->execute(
            $datos['reembolso_id'],
            'aprobar',
            $datos['admin']->id
        );

        $this->expectException(DomainException::class);

        $casoUso->execute(
            $datos['reembolso_id'],
            'aprobar',
            $datos['admin']->id
        );
    }

    public function test_no_permite_aprobar_solicitud_rechazada(): void
    {
        $datos = $this->escenario();

        $casoUso = app(ResolverSolicitudReembolsoUseCase::class);

        $casoUso->execute(
            $datos['reembolso_id'],
            'rechazar',
            $datos['admin']->id
        );

        $this->expectException(DomainException::class);

        $casoUso->execute(
            $datos['reembolso_id'],
            'aprobar',
            $datos['admin']->id
        );
    }

    public function test_rechaza_monto_superior_al_pago(): void
    {
        $datos = $this->escenario(
            montoReembolso: 600
        );

        try {
            app(ResolverSolicitudReembolsoUseCase::class)
                ->execute(
                    $datos['reembolso_id'],
                    'aprobar',
                    $datos['admin']->id
                );

            $this->fail('Se aprobó un reembolso excesivo.');

        } catch (DomainException $e) {
            $this->assertSame(
                'El reembolso supera el monto del pago.',
                $e->getMessage()
            );
        }

        $this->assertDatabaseHas('reembolsos', [
            'id' => $datos['reembolso_id'],
            'estado' => 'solicitado',
        ]);
    }

    public function test_no_aprueba_si_pago_esta_pendiente(): void
    {
        $datos = $this->escenario(
            estadoPago: 'pendiente'
        );

        $this->expectException(DomainException::class);

        app(ResolverSolicitudReembolsoUseCase::class)
            ->execute(
                $datos['reembolso_id'],
                'aprobar',
                $datos['admin']->id
            );
    }

    private function crearUsuario(
        string $email,
        RolUsuario $rol
    ): Usuario {
        return Usuario::create([
            'nombre' => 'Usuario',
            'apellido' => 'Prueba',
            'email' => $email,
            'password' => bcrypt('Secreto#123'),
            'rol' => $rol,
            'estado' => EstadoUsuario::ACTIVO,
            'email_verified_at' => now(),
        ]);
    }

    public function test_no_permite_rechazar_reembolso_de_evento_cancelado(): void
    {
        $datos = $this->escenario();

        // Obtener el evento relacionado con el pago.
        $reservaId = DB::table('pagos')
            ->where('id', $datos['pago_id'])
            ->value('reserva_id');

        $eventoId = DB::table('reservas')
            ->where('id', $reservaId)
            ->value('evento_id');

        // Simular una cancelacion del organizador.
        DB::table('eventos')
            ->where('id', $eventoId)
            ->update([
                'estado' => 'cancelado',
                'updated_at' => now(),
            ]);

        // El administrador intenta rechazar la devolucion.
        try {
            app(ResolverSolicitudReembolsoUseCase::class)
                ->execute(
                    $datos['reembolso_id'],
                    'rechazar',
                    $datos['admin']->id
                );

            $this->fail(
                'Se permitio rechazar el reembolso de un evento cancelado.'
            );

        } catch (DomainException $e) {
            $this->assertSame(
                'No se puede rechazar un reembolso de un evento cancelado.',
                $e->getMessage()
            );
        }

        // La solicitud debe conservar su estado.
        $this->assertDatabaseHas('reembolsos', [
            'id' => $datos['reembolso_id'],
            'estado' => 'solicitado',
        ]);
    }

}
