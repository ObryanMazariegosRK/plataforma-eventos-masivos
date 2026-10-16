<?php

namespace Tests\Feature\Notificaciones;

use App\Application\UseCases\Notificaciones\NotificarCompraConfirmadaUseCase;
use App\Domain\Abstractions\Notificaciones\INotificacionRepository;
use App\Domain\Enums\Auth\EstadoUsuario;
use App\Domain\Enums\Auth\RolUsuario;
use App\Domain\Enums\Notificaciones\EstadoNotificacion;
use App\Jobs\Notificaciones\EnviarNotificacionCorreoJob;
use App\Models\Auth\Usuario;
use DateTimeImmutable;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotificarCompraConfirmadaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Impedir que las pruebas envien trabajos reales a Redis.
        Queue::fake();
    }

    private function crearEscenario(
        string $estadoPago = 'aprobado',
        string $estadoReserva = 'confirmada',
        bool $crearBoleto = true
    ): array {

        $cliente = Usuario::create([
            'nombre' => 'Cliente',
            'apellido' => 'Prueba',
            'email' => 'cliente-confirmacion@test.com',
            'password' => bcrypt('Secreto#123'),
            'rol' => RolUsuario::CLIENTE,
            'estado' => EstadoUsuario::ACTIVO,
            'email_verified_at' => now(),
        ]);

        $recintoId = DB::table('recintos')->insertGetId([
            'nombre' => 'Recinto Notificaciones',
            'direccion' => 'Zona 1',
            'ciudad' => 'Guatemala',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $eventoId = DB::table('eventos')->insertGetId([
            'recinto_id' => $recintoId,
            'nombre' => 'Concierto de Prueba',
            'fecha_evento' => now()->addDays(10),
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

        if ($crearBoleto) {

            $localidadId = DB::table('localidades')
                ->insertGetId([
                    'recinto_id' => $recintoId,
                    'nombre' => 'General',
                    'tipo' => 'general',
                    'capacidad' => 100,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            $inventarioId = DB::table('inventario')
                ->insertGetId([
                    'evento_id' => $eventoId,
                    'localidad_id' => $localidadId,
                    'reserva_id' => $reservaId,
                    'estado' => 'vendido',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

            DB::table('boletos')->insert([
                'reserva_id' => $reservaId,
                'inventario_id' => $inventarioId,
                'usuario_id' => $cliente->id,
                'codigo_qr' => bin2hex(random_bytes(32)),
                'estado' => 'emitido',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return [
            'cliente' => $cliente,
            'pago_id' => $pagoId,
            'reserva_id' => $reservaId,
        ];
    }

    public function test_genera_notificacion_de_compra_confirmada(): void
    {
        $datos = $this->crearEscenario();

        $notificacion = app(
            NotificarCompraConfirmadaUseCase::class
        )->execute($datos['pago_id']);

        $this->assertSame(
            EstadoNotificacion::PENDIENTE,
            $notificacion->getEstado()
        );

        $this->assertSame(
            $datos['cliente']->id,
            $notificacion->getUsuarioId()
        );

        $this->assertDatabaseHas('notificaciones', [
            'usuario_id' => $datos['cliente']->id,
            'canal' => 'email',
            'estado' => 'pendiente',
            'clave_idempotencia' =>
                'compra_confirmada:pago:' . $datos['pago_id'],
        ]);

        $this->assertDatabaseCount('notificaciones', 1);
    }

    public function test_programa_job_de_envio_de_correo(): void
    {
        $datos = $this->crearEscenario();

        $notificacion = app(
            NotificarCompraConfirmadaUseCase::class
        )->execute($datos['pago_id']);

        Queue::assertPushed(
            EnviarNotificacionCorreoJob::class,
            function ($job) use ($notificacion) {
                return $job->notificacionId
                    === $notificacion->getId()
                    && $job->queue === 'default';
            }
        );

        
        Queue::assertPushed(EnviarNotificacionCorreoJob::class, 1);

    }

    public function test_no_duplica_confirmacion_de_compra(): void
    {
        $datos = $this->crearEscenario();

        $casoUso = app(
            NotificarCompraConfirmadaUseCase::class
        );

        $primera = $casoUso->execute($datos['pago_id']);
        $segunda = $casoUso->execute($datos['pago_id']);

        $this->assertSame(
            $primera->getId(),
            $segunda->getId()
        );

        $this->assertDatabaseCount('notificaciones', 1);
    }

    public function test_no_programa_otro_envio_si_ya_fue_enviada(): void
    {
        $datos = $this->crearEscenario();

        $casoUso = app(
            NotificarCompraConfirmadaUseCase::class
        );

        $primera = $casoUso->execute($datos['pago_id']);

        $repositorio = app(
            INotificacionRepository::class
        );

        // Simular que el correo ya fue enviado.
        $primera->marcarEnviada(
            new DateTimeImmutable()
        );

        $repositorio->guardar($primera);

        // Volver a procesar la misma compra.
        $segunda = $casoUso->execute($datos['pago_id']);

        $this->assertSame(
            $primera->getId(),
            $segunda->getId()
        );

        $this->assertSame(
            EstadoNotificacion::ENVIADA,
            $segunda->getEstado()
        );

        // Solo existe el trabajo de la primera ejecucion.
        Queue::assertPushed(EnviarNotificacionCorreoJob::class, 1);


        $this->assertDatabaseCount('notificaciones', 1);
    }

    public function test_rechaza_pago_pendiente(): void
    {
        $datos = $this->crearEscenario(
            estadoPago: 'pendiente'
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'El pago no existe o no está aprobado.'
        );

        app(NotificarCompraConfirmadaUseCase::class)
            ->execute($datos['pago_id']);
    }

    public function test_rechaza_reserva_no_confirmada(): void
    {
        $datos = $this->crearEscenario(
            estadoReserva: 'activa'
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'La reserva no está confirmada.'
        );

        app(NotificarCompraConfirmadaUseCase::class)
            ->execute($datos['pago_id']);
    }

    public function test_rechaza_compra_sin_boletos(): void
    {
        $datos = $this->crearEscenario(
            crearBoleto: false
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'Todavía no existen boletos emitidos para esta compra.'
        );

        app(NotificarCompraConfirmadaUseCase::class)
            ->execute($datos['pago_id']);
    }
}
