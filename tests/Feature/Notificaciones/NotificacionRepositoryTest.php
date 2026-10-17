<?php

namespace Tests\Feature\Notificaciones;

use App\Data\Notificaciones\NotificacionRepository;
use App\Domain\Entities\Notificaciones\Notificacion;
use App\Domain\Enums\Auth\EstadoUsuario;
use App\Domain\Enums\Auth\RolUsuario;
use App\Domain\Enums\Notificaciones\CanalNotificacion;
use App\Domain\Enums\Notificaciones\EstadoNotificacion;
use App\Models\Auth\Usuario;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NotificacionRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private function crearUsuario(): Usuario
    {
        return Usuario::create([
            'nombre' => 'Cliente',
            'apellido' => 'Notificaciones',
            'email' => 'cliente-notificaciones@test.com',
            'password' => bcrypt('Secreto#123'),
            'rol' => RolUsuario::CLIENTE,
            'estado' => EstadoUsuario::ACTIVO,
            'email_verified_at' => now(),
        ]);
    }

    private function nuevaNotificacion(
        int $usuarioId
    ): Notificacion {
        return new Notificacion(
            id: null,
            usuarioId: $usuarioId,
            canal: CanalNotificacion::EMAIL,
            asunto: 'Confirmación de compra',
            mensaje: 'Tu compra fue confirmada correctamente.',
            estado: EstadoNotificacion::PENDIENTE
        );
    }

    public function test_guarda_notificacion_pendiente(): void
    {
        $usuario = $this->crearUsuario();

        $repositorio = app(NotificacionRepository::class);

        $notificacion = $repositorio->guardar(
            $this->nuevaNotificacion($usuario->id),
            $usuario->id
        );

        $this->assertNotNull($notificacion->getId());

        $this->assertSame(
            EstadoNotificacion::PENDIENTE,
            $notificacion->getEstado()
        );

        $this->assertSame(
            CanalNotificacion::EMAIL,
            $notificacion->getCanal()
        );

        $this->assertDatabaseHas('notificaciones', [
            'id' => $notificacion->getId(),
            'usuario_id' => $usuario->id,
            'canal' => 'email',
            'estado' => 'pendiente',
            'created_by' => $usuario->id,
        ]);
    }

    public function test_actualiza_notificacion_a_enviada(): void
    {
        $usuario = $this->crearUsuario();

        $repositorio = app(NotificacionRepository::class);

        $notificacion = $repositorio->guardar(
            $this->nuevaNotificacion($usuario->id)
        );

        $momento = new DateTimeImmutable(
            '2026-10-16 10:30:00'
        );

        $notificacion->marcarEnviada($momento);

        $actualizada = $repositorio->guardar(
            $notificacion
        );

        $this->assertSame(
            EstadoNotificacion::ENVIADA,
            $actualizada->getEstado()
        );

        $this->assertSame(
            '2026-10-16 10:30:00',
            $actualizada->getEnviadaEn()->format(
                'Y-m-d H:i:s'
            )
        );

        $this->assertDatabaseHas('notificaciones', [
            'id' => $notificacion->getId(),
            'estado' => 'enviada',
            'enviada_en' => '2026-10-16 10:30:00',
        ]);

        $this->assertDatabaseCount('notificaciones', 1);
    }

    public function test_registra_notificacion_fallida(): void
    {
        $usuario = $this->crearUsuario();

        $repositorio = app(NotificacionRepository::class);

        $notificacion = $repositorio->guardar(
            $this->nuevaNotificacion($usuario->id)
        );

        $notificacion->marcarFallida();

        $actualizada = $repositorio->guardar(
            $notificacion
        );

        $this->assertSame(
            EstadoNotificacion::FALLIDA,
            $actualizada->getEstado()
        );

        $this->assertDatabaseHas('notificaciones', [
            'id' => $notificacion->getId(),
            'estado' => 'fallida',
        ]);
    }

    public function test_no_recupera_notificacion_eliminada(): void
    {
        $usuario = $this->crearUsuario();

        $repositorio = app(NotificacionRepository::class);

        $notificacion = $repositorio->guardar(
            $this->nuevaNotificacion($usuario->id)
        );

        // Simulamos una eliminacion logica.
        DB::table('notificaciones')
            ->where('id', $notificacion->getId())
            ->update([
                'deleted_at' => now(),
            ]);

        $resultado = $repositorio->buscarPorId(
            $notificacion->getId()
        );

        $this->assertNull($resultado);
    }
    
    public function test_no_duplica_notificacion_con_misma_clave(): void
    {
        $usuario = $this->crearUsuario();

        $repositorio = app(NotificacionRepository::class);

        $primera = $repositorio->guardarUnica(
            $this->nuevaNotificacion($usuario->id),
            'compra_confirmada:pago:15'
        );

        $segunda = $repositorio->guardarUnica(
            $this->nuevaNotificacion($usuario->id),
            'compra_confirmada:pago:15'
        );

        $this->assertSame(
            $primera->getId(),
            $segunda->getId()
        );

        $this->assertDatabaseCount('notificaciones', 1);
    }

    public function test_reintento_recupera_notificacion_ya_enviada(): void
    {
        $usuario = $this->crearUsuario();

        $repositorio = app(NotificacionRepository::class);

        $primera = $repositorio->guardarUnica(
            $this->nuevaNotificacion($usuario->id),
            'compra_confirmada:pago:20'
        );

        $primera->marcarEnviada(
            new DateTimeImmutable('2026-10-16 10:30:00')
        );

        $repositorio->guardar($primera);

        // Simular que se vuelve a procesar el mismo pago.
        $reintento = $repositorio->guardarUnica(
            $this->nuevaNotificacion($usuario->id),
            'compra_confirmada:pago:20'
        );

        $this->assertSame(
            $primera->getId(),
            $reintento->getId()
        );

        $this->assertSame(
            EstadoNotificacion::ENVIADA,
            $reintento->getEstado()
        );

        $this->assertDatabaseCount('notificaciones', 1);
    }

    public function test_rechaza_reutilizar_clave_con_otro_contenido(): void
    {
        $usuario = $this->crearUsuario();

        $repositorio = app(NotificacionRepository::class);

        $repositorio->guardarUnica(
            $this->nuevaNotificacion($usuario->id),
            'compra_confirmada:pago:25'
        );

        $diferente = new Notificacion(
            id: null,
            usuarioId: $usuario->id,
            canal: CanalNotificacion::EMAIL,
            asunto: 'Otro asunto',
            mensaje: 'Un mensaje diferente.',
            estado: EstadoNotificacion::PENDIENTE
        );

        try {
            $repositorio->guardarUnica(
                $diferente,
                'compra_confirmada:pago:25'
            );

            $this->fail(
                'Se permitio reutilizar la clave con otros datos.'
            );

        } catch (\DomainException $e) {
            $this->assertSame(
                'La clave pertenece a otra notificación.',
                $e->getMessage()
            );
        }

        $this->assertDatabaseCount('notificaciones', 1);
    }

    public function test_rechaza_clave_de_idempotencia_vacia(): void
    {
        $usuario = $this->crearUsuario();

        $repositorio = app(NotificacionRepository::class);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage(
            'La clave de idempotencia no es válida.'
        );

        $repositorio->guardarUnica(
            $this->nuevaNotificacion($usuario->id),
            '  '
        );
    }

}
