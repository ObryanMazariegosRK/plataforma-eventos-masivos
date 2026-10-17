<?php

namespace Tests\Feature\Reembolsos;

use App\Application\UseCases\Reembolsos\SolicitarReembolsoUseCase;
use App\Domain\Enums\Reembolsos\EstadoReembolso;
use App\Models\Auth\Usuario;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SolicitarReembolsoTest extends TestCase
{
    use RefreshDatabase;

    private function escenario(
        int $horas = 240,
        string $estadoPago = 'aprobado',
        string $estadoReserva = 'confirmada'
    ): array {
        $cliente = Usuario::create([
            'nombre' => 'Cliente',
            'apellido' => 'Prueba',
            'email' => 'cliente-reembolso@test.com',
            'password' => bcrypt('Secreto#123'),
            'rol' => 'cliente',
            'estado' => 'activo',
            'email_verified_at' => now(),
        ]);

        $otro = Usuario::create([
            'nombre' => 'Otro',
            'apellido' => 'Cliente',
            'email' => 'otro-reembolso@test.com',
            'password' => bcrypt('Secreto#123'),
            'rol' => 'cliente',
            'estado' => 'activo',
            'email_verified_at' => now(),
        ]);

        $recintoId = DB::table('recintos')->insertGetId([
            'nombre' => 'Recinto Prueba',
            'direccion' => 'Zona 1',
            'ciudad' => 'Guatemala',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $eventoId = DB::table('eventos')->insertGetId([
            'recinto_id' => $recintoId,
            'nombre' => 'Evento Reembolsos',
            'fecha_evento' => now()->addHours($horas),
            'estado' => 'publicado',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $reservaId = DB::table('reservas')->insertGetId([
            'usuario_id' => $cliente->id,
            'evento_id' => $eventoId,
            'estado' => $estadoReserva,
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

        return [
            'cliente' => $cliente,
            'otro' => $otro,
            'evento_id' => $eventoId,
            'reserva_id' => $reservaId,
            'pago_id' => $pagoId,
        ];
    }

    public function test_solicita_reembolso_completo(): void
    {
        $datos = $this->escenario(240);

        $reembolso = app(SolicitarReembolsoUseCase::class)
            ->execute(
                $datos['pago_id'],
                $datos['cliente']->id,
                'No podré asistir'
            );

        $this->assertSame(
            EstadoReembolso::SOLICITADO,
            $reembolso->getEstado()
        );

        $this->assertEquals(500, $reembolso->getMonto());

        $this->assertDatabaseHas('reembolsos', [
            'pago_id' => $datos['pago_id'],
            'monto' => 500,
            'estado' => 'solicitado',
            'motivo' => 'No podré asistir',
        ]);
    }

    public function test_aplica_penalizacion_del_20_por_ciento(): void
    {
        $datos = $this->escenario(120);

        $reembolso = app(SolicitarReembolsoUseCase::class)
            ->execute(
                $datos['pago_id'],
                $datos['cliente']->id
            );

        $this->assertEquals(400, $reembolso->getMonto());

        $this->assertDatabaseCount('reembolsos', 1);
    }

    public function test_aplica_penalizacion_del_50_por_ciento(): void
    {
        $datos = $this->escenario(48);

        $reembolso = app(SolicitarReembolsoUseCase::class)
            ->execute(
                $datos['pago_id'],
                $datos['cliente']->id
            );

        $this->assertEquals(250, $reembolso->getMonto());
    }

    public function test_rechaza_cancelacion_fuera_del_plazo(): void
    {
        $datos = $this->escenario(12);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'La cancelación está fuera de los plazos permitidos.'
        );

        app(SolicitarReembolsoUseCase::class)->execute(
            $datos['pago_id'],
            $datos['cliente']->id
        );
    }

    public function test_otro_usuario_no_puede_solicitar_reembolso(): void
    {
        $datos = $this->escenario();

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'No puedes solicitar el reembolso de otro usuario.'
        );

        app(SolicitarReembolsoUseCase::class)->execute(
            $datos['pago_id'],
            $datos['otro']->id
        );
    }

    public function test_rechaza_pago_pendiente(): void
    {
        $datos = $this->escenario(
            estadoPago: 'pendiente'
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'El pago no existe o no está aprobado.'
        );

        app(SolicitarReembolsoUseCase::class)->execute(
            $datos['pago_id'],
            $datos['cliente']->id
        );
    }

    public function test_rechaza_reserva_no_confirmada(): void
    {
        $datos = $this->escenario(
            estadoReserva: 'activa'
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'La reserva no está confirmada.'
        );

        app(SolicitarReembolsoUseCase::class)->execute(
            $datos['pago_id'],
            $datos['cliente']->id
        );
    }

    public function test_rechaza_solicitud_duplicada(): void
    {
        $datos = $this->escenario();

        $casoUso = app(SolicitarReembolsoUseCase::class);

        $casoUso->execute(
            $datos['pago_id'],
            $datos['cliente']->id
        );

        try {
            $casoUso->execute(
                $datos['pago_id'],
                $datos['cliente']->id
            );

            $this->fail(
                'El sistema permitió duplicar el reembolso.'
            );

        } catch (DomainException $e) {
            $this->assertSame(
                'Ya existe una solicitud de reembolso para este pago.',
                $e->getMessage()
            );
        }

        $this->assertDatabaseCount('reembolsos', 1);
    }

    public function test_rechaza_boleto_utilizado(): void
    {
        $datos = $this->escenario();

        $localidadId = DB::table('localidades')->insertGetId([
            'recinto_id' => DB::table('eventos')
                ->where('id', $datos['evento_id'])
                ->value('recinto_id'),
            'nombre' => 'General',
            'tipo' => 'general',
            'capacidad' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $inventarioId = DB::table('inventario')->insertGetId([
            'evento_id' => $datos['evento_id'],
            'localidad_id' => $localidadId,
            'reserva_id' => $datos['reserva_id'],
            'estado' => 'vendido',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('boletos')->insert([
            'reserva_id' => $datos['reserva_id'],
            'inventario_id' => $inventarioId,
            'usuario_id' => $datos['cliente']->id,
            'codigo_qr' => bin2hex(random_bytes(32)),
            'estado' => 'usado',
            'usado_en' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'La reserva contiene boletos usados o anulados.'
        );

        app(SolicitarReembolsoUseCase::class)->execute(
            $datos['pago_id'],
            $datos['cliente']->id
        );
    }
}
