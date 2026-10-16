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
use App\Application\UseCases\Notificaciones\ProgramarRecordatoriosEventosUseCase;
use App\Application\UseCases\Notificaciones\NotificarCambioEventoUseCase;
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

    public function test_programa_recordatorio_para_evento_proximo(): void
    {
        $datos = $this->crearEscenario();

        $eventoId = DB::table('reservas')
            ->where('id', $datos['reserva_id'])
            ->value('evento_id');

        // El evento se realizara dentro de 24 horas y 30 minutos.
        DB::table('eventos')
            ->where('id', $eventoId)
            ->update([
                'fecha_evento' => now()->addHours(24)->addMinutes(30),
            ]);

        $cantidad = app(
            ProgramarRecordatoriosEventosUseCase::class
        )->execute();

        $this->assertSame(1, $cantidad);

        $this->assertDatabaseHas('notificaciones', [
            'usuario_id' => $datos['cliente']->id,
            'clave_idempotencia' =>
                'recordatorio_24h:evento:'
                . $eventoId
                . ':usuario:' . $datos['cliente']->id,
            'estado' => 'pendiente',
        ]);

        Queue::assertPushed(
            EnviarNotificacionCorreoJob::class,
            1
        );
    }

    public function test_no_programa_recordatorios_para_eventos_lejanos(): void
    {
        // El escenario original crea un evento a 10 dias.
        $this->crearEscenario();

        $cantidad = app(
            ProgramarRecordatoriosEventosUseCase::class
        )->execute();

        $this->assertSame(0, $cantidad);

        $this->assertDatabaseCount('notificaciones', 0);

        Queue::assertNothingPushed();
    }

    public function test_notifica_cancelacion_de_evento(): void
    {
        $datos = $this->crearEscenario();

        $eventoId = DB::table('reservas')
            ->where('id', $datos['reserva_id'])
            ->value('evento_id');

        // Simular la cancelacion realizada por el sistema.
        DB::table('eventos')
            ->where('id', $eventoId)
            ->update(['estado' => 'cancelado']);

        $casoUso = app(NotificarCambioEventoUseCase::class);

        $cantidad = $casoUso->notificarCancelacion($eventoId);

        $this->assertSame(1, $cantidad);

        $this->assertDatabaseHas('notificaciones', [
            'usuario_id' => $datos['cliente']->id,
            'asunto' => 'Evento cancelado: Concierto de Prueba',
            'estado' => 'pendiente',
            'clave_idempotencia' =>
                'evento_cancelado:' . $eventoId
                . ':usuario:' . $datos['cliente']->id,
        ]);

        Queue::assertPushed(
            EnviarNotificacionCorreoJob::class,
            1
        );

        // Repetir el aviso no debe crear otro registro.
        $casoUso->notificarCancelacion($eventoId);

        $this->assertDatabaseCount('notificaciones', 1);
    }

    public function test_notifica_cambio_de_fecha_de_evento(): void
    {
        $datos = $this->crearEscenario();

        $eventoId = DB::table('reservas')
            ->where('id', $datos['reserva_id'])
            ->value('evento_id');

        $fechaAnterior = DB::table('eventos')
            ->where('id', $eventoId)
            ->value('fecha_evento');

        $nuevaFecha = now()->addDays(15)->format('Y-m-d H:i:s');

        // Simular que el organizador cambia la fecha.
        DB::table('eventos')
            ->where('id', $eventoId)
            ->update(['fecha_evento' => $nuevaFecha]);

        $cantidad = app(
            NotificarCambioEventoUseCase::class
        )->notificarCambioFecha(
            $eventoId,
            $fechaAnterior
        );

        $this->assertSame(1, $cantidad);

        $this->assertDatabaseHas('notificaciones', [
            'usuario_id' => $datos['cliente']->id,
            'asunto' => 'Cambio de fecha: Concierto de Prueba',
            'estado' => 'pendiente',
        ]);

        Queue::assertPushed(
            EnviarNotificacionCorreoJob::class,
            1
        );
    }


}
