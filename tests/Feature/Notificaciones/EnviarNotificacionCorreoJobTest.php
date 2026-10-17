<?php

namespace Tests\Feature\Notificaciones;

use App\Domain\Abstractions\Notificaciones\INotificacionRepository;
use App\Domain\Entities\Notificaciones\Notificacion;
use App\Domain\Enums\Auth\EstadoUsuario;
use App\Domain\Enums\Auth\RolUsuario;
use App\Domain\Enums\Notificaciones\CanalNotificacion;
use App\Domain\Enums\Notificaciones\EstadoNotificacion;
use App\Infrastructure\Notificaciones\Mail\NotificacionCorreo;
use App\Jobs\Notificaciones\EnviarNotificacionCorreoJob;
use App\Models\Auth\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class EnviarNotificacionCorreoJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Bloqueos aislados durante las pruebas.
        config()->set('cache.default', 'array');

        // Evitar envios SMTP reales.
        Mail::fake();
    }

    private function crearNotificacion(): Notificacion
    {
        $usuario = Usuario::create([
            'nombre' => 'Cliente',
            'apellido' => 'Prueba',
            'email' => 'correo-prueba@example.com',
            'password' => bcrypt('Secreto#123'),
            'rol' => RolUsuario::CLIENTE,
            'estado' => EstadoUsuario::ACTIVO,
            'email_verified_at' => now(),
        ]);

        $notificacion = new Notificacion(
            id: null,
            usuarioId: $usuario->id,
            canal: CanalNotificacion::EMAIL,
            asunto: 'Confirmación de compra',
            mensaje: 'Tus boletos están disponibles.',
            estado: EstadoNotificacion::PENDIENTE
        );

        return app(INotificacionRepository::class)
            ->guardar($notificacion);
    }

    public function test_envia_correo_y_actualiza_estado(): void
    {
        $notificacion = $this->crearNotificacion();

        $job = new EnviarNotificacionCorreoJob(
            $notificacion->getId()
        );

        $job->handle(
            app(INotificacionRepository::class)
        );

        // Verificar el destinatario del correo.
        Mail::assertSent(
            NotificacionCorreo::class,
            function ($correo) {
                return $correo->hasTo(
                    'correo-prueba@example.com'
                );
            }
        );

        Mail::assertSentCount(1);

        // Comprobar que SMTP fue simulado correctamente.
        $this->assertDatabaseHas('notificaciones', [
            'id' => $notificacion->getId(),
            'estado' => 'enviada',
        ]);

        $actualizada = app(
            INotificacionRepository::class
        )->buscarPorId($notificacion->getId());

        $this->assertSame(
            EstadoNotificacion::ENVIADA,
            $actualizada->getEstado()
        );

        $this->assertNotNull(
            $actualizada->getEnviadaEn()
        );
    }

    public function test_no_reenvia_notificacion_ya_enviada(): void
    {
        $notificacion = $this->crearNotificacion();

        $job = new EnviarNotificacionCorreoJob(
            $notificacion->getId()
        );

        $repositorio = app(
            INotificacionRepository::class
        );

        // Primera ejecucion.
        $job->handle($repositorio);

        // Segunda ejecucion del mismo Job.
        $job->handle($repositorio);

        // Debe existir solamente un correo enviado.
        Mail::assertSentCount(1);

        $this->assertDatabaseHas('notificaciones', [
            'id' => $notificacion->getId(),
            'estado' => 'enviada',
        ]);

        $this->assertDatabaseCount('notificaciones', 1);
    }

    public function test_marca_fallida_si_correo_es_invalido(): void
    {
        $notificacion = $this->crearNotificacion();

        // Simular un destinatario con correo invalido.
        DB::table('usuarios')
            ->where('id', $notificacion->getUsuarioId())
            ->update([
                'email' => 'correo-invalido',
            ]);

        $job = new EnviarNotificacionCorreoJob(
            $notificacion->getId()
        );

        try {
            $job->handle(
                app(INotificacionRepository::class)
            );

            $this->fail(
                'Debio rechazarse el destinatario invalido.'
            );

        } catch (RuntimeException $e) {
            $this->assertSame(
                'El destinatario no tiene un correo valido.',
                $e->getMessage()
            );
        }

        $this->assertDatabaseHas('notificaciones', [
            'id' => $notificacion->getId(),
            'estado' => 'fallida',
        ]);

        // No debe enviarse ningun correo.
        Mail::assertNothingSent();
    }
}
