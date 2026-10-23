<?php

namespace Tests\Feature\Notificaciones;

use App\Application\UseCases\Notificaciones\NotificarPersonalUseCase;
use App\Domain\Abstractions\Notificaciones\IEnviadorPush;
use App\Domain\Abstractions\Notificaciones\INotificacionRepository;
use App\Domain\Enums\Auth\EstadoUsuario;
use App\Domain\Enums\Auth\RolUsuario;
use App\Jobs\Notificaciones\EnviarNotificacionCorreoJob;
use App\Models\Auth\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\Fakes\Notificaciones\EnviadorPushFalso;
use Tests\TestCase;

// Avisos: cada notificación se guarda una vez y sale por correo (clientes) y/o push; se ve en la
// campana de la tienda y en la sección Notificaciones del panel, con leído/no leído en el servidor.
class AvisosTest extends TestCase
{
    use RefreshDatabase;

    private EnviadorPushFalso $push;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->push = new EnviadorPushFalso;
        $this->app->instance(IEnviadorPush::class, $this->push);
    }

    private function usuario(string $email, RolUsuario $rol = RolUsuario::CLIENTE, EstadoUsuario $estado = EstadoUsuario::ACTIVO): Usuario
    {
        return Usuario::create([
            'nombre' => ucfirst(strtok($email, '@')), 'apellido' => 'Pérez', 'email' => $email, 'password' => bcrypt('Secreto#123'),
            'rol' => $rol, 'estado' => $estado, 'email_verified_at' => now(),
        ]);
    }

    private function aviso(Usuario $usuario, string $asunto, string $canal = 'email', array $extra = []): int
    {
        return DB::table('notificaciones')->insertGetId($extra + [
            'usuario_id' => $usuario->id, 'canal' => $canal, 'tipo' => 'compra_confirmada', 'asunto' => $asunto,
            'mensaje' => "Tus boletos están listos.\n\nPreséntalos en la entrada.", 'url' => '/mis-compras/1/boletos',
            'estado' => 'pendiente', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_el_envio_manda_el_push_una_sola_vez_con_el_primer_parrafo(): void
    {
        $cliente = $this->usuario('ana@test.com');
        $id = $this->aviso($cliente, 'Confirmación de compra');

        (new EnviarNotificacionCorreoJob($id))->handle(app(INotificacionRepository::class));
        (new EnviarNotificacionCorreoJob($id))->handle(app(INotificacionRepository::class));

        $this->assertSame([['usuario_id' => $cliente->id, 'titulo' => 'Confirmación de compra', 'cuerpo' => 'Tus boletos están listos.', 'url' => '/mis-compras/1/boletos']], $this->push->enviados);
        Mail::assertSentCount(1);
        $this->assertNotNull(DB::table('notificaciones')->value('push_en'));
    }

    public function test_si_el_push_falla_el_correo_sale_igual_y_los_avisos_del_panel_no_van_por_correo(): void
    {
        $this->push->fallar = true;
        $cliente = $this->usuario('ana@test.com');
        $id = $this->aviso($cliente, 'Confirmación de compra');
        (new EnviarNotificacionCorreoJob($id))->handle(app(INotificacionRepository::class));
        $this->assertSame('enviada', DB::table('notificaciones')->where('id', $id)->value('estado'));
        Mail::assertSentCount(1);

        $this->push->fallar = false;
        $admin = $this->usuario('luis@test.com', RolUsuario::ADMIN);
        $panel = $this->aviso($admin, 'Nueva solicitud de reembolso', 'push');
        (new EnviarNotificacionCorreoJob($panel))->handle(app(INotificacionRepository::class));
        $this->assertSame('enviada', DB::table('notificaciones')->where('id', $panel)->value('estado'));
        Mail::assertSentCount(1);   // ninguno más
        $this->assertCount(1, $this->push->enviados);
    }

    public function test_la_bandeja_muestra_solo_lo_propio_y_marca_leidos(): void
    {
        $ana = $this->usuario('ana@test.com');
        $otro = $this->usuario('otro@test.com');
        $primero = $this->aviso($ana, 'Primero', extra: ['created_at' => now()->subHour()]);
        $segundo = $this->aviso($ana, 'Segundo');
        $ajeno = $this->aviso($otro, 'Ajeno');

        Sanctum::actingAs($ana);
        $datos = $this->getJson('/api/notificaciones')->assertOk()->json('data');
        $this->assertSame(['Segundo', 'Primero'], array_column($datos['avisos'], 'titulo'));
        $this->assertSame([2, 2], [$datos['no_leidas'], $datos['meta']['total']]);
        $this->assertSame(['tipo' => 'compra_confirmada', 'url' => '/mis-compras/1/boletos', 'leida' => false], array_intersect_key($datos['avisos'][0], array_flip(['tipo', 'url', 'leida'])));

        $this->postJson("/api/notificaciones/{$segundo}/leer")->assertOk()->assertJsonPath('data.no_leidas', 1);
        // El aviso de otra persona no se toca
        $this->postJson("/api/notificaciones/{$ajeno}/leer")->assertOk();
        $this->assertNull(DB::table('notificaciones')->where('id', $ajeno)->value('leida_en'));

        $this->postJson('/api/notificaciones/leer-todas')->assertOk()->assertJsonPath('data.no_leidas', 0);
        $this->assertNotNull(DB::table('notificaciones')->where('id', $primero)->value('leida_en'));
        $this->assertTrue($this->getJson('/api/notificaciones?por_pagina=1')->json('data.meta.hay_mas'));

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/notificaciones', ['Authorization' => ''])->assertUnauthorized();
    }

    public function test_activar_el_push_solo_acepta_servicios_de_push_de_navegadores(): void
    {
        Sanctum::actingAs($ana = $this->usuario('ana@test.com'));
        $llaves = ['p256dh' => 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpQtUbVlUls0VJXg7A8u-Ts1XbjhazAkj7I99e8QcYP7DkM', 'auth' => 'tBHItJI5svbpez7KI4CCXg'];

        $this->postJson('/api/push/suscribir', ['endpoint' => 'https://evil.example.com/push/1', 'keys' => $llaves])
            ->assertStatus(422)->assertJsonValidationErrors('endpoint');
        $this->postJson('/api/push/suscribir', ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123', 'keys' => $llaves])->assertCreated();
        $this->assertSame(1, $ana->pushSubscriptions()->count());

        $this->postJson('/api/push/desuscribir', ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc123'])->assertOk();
        $this->assertSame(0, $ana->pushSubscriptions()->count());
    }

    public function test_el_personal_recibe_avisos_de_reembolsos_y_de_eventos_que_se_llenan(): void
    {
        // Cola sincrónica: cada aviso sale (push) en cuanto se crea
        $admin = $this->usuario('admin@test.com', RolUsuario::ADMIN);
        $operador = $this->usuario('operador@test.com', RolUsuario::ORGANIZADOR);
        $this->usuario('bloqueado@test.com', RolUsuario::ADMIN, EstadoUsuario::BLOQUEADO);
        $cliente = $this->usuario('ana@test.com');

        $recinto = DB::table('recintos')->insertGetId(['nombre' => 'Teatro', 'direccion' => 'Z1', 'ciudad' => 'Guatemala', 'created_at' => now(), 'updated_at' => now()]);
        $general = DB::table('localidades')->insertGetId(['recinto_id' => $recinto, 'nombre' => 'General', 'tipo' => 'general', 'capacidad' => 10, 'created_at' => now(), 'updated_at' => now()]);
        $evento = DB::table('eventos')->insertGetId(['recinto_id' => $recinto, 'nombre' => 'Gala', 'estado' => 'publicado', 'fecha_evento' => now()->addDays(20), 'created_at' => now(), 'updated_at' => now()]);
        $reserva = DB::table('reservas')->insertGetId(['usuario_id' => $cliente->id, 'evento_id' => $evento, 'estado' => 'confirmada', 'expira_en' => now(), 'total' => 900, 'created_at' => now(), 'updated_at' => now()]);
        for ($i = 0; $i < 10; $i++) {
            DB::table('inventario')->insert(['evento_id' => $evento, 'localidad_id' => $general, 'estado' => $i < 9 ? 'vendido' : 'disponible', 'reserva_id' => $i < 9 ? $reserva : null, 'created_at' => now(), 'updated_at' => now()]);
        }
        $pago = DB::table('pagos')->insertGetId(['reserva_id' => $reserva, 'monto' => 900, 'estado' => 'aprobado', 'created_at' => now(), 'updated_at' => now()]);
        $personal = app(NotificarPersonalUseCase::class);

        // 9 de 10: casi lleno, a admin y operador (no al bloqueado ni al cliente); una sola vez
        $this->assertSame(2, $personal->ocupacionTrasVenta($pago, 0.9));
        $this->assertSame(0, $personal->ocupacionTrasVenta($pago, 0.9));
        DB::table('inventario')->where('estado', 'disponible')->update(['estado' => 'vendido', 'reserva_id' => $reserva]);
        $this->assertSame(2, $personal->ocupacionTrasVenta($pago, 0.9));
        $this->assertSame(
            ['evento_agotado', 'evento_casi_lleno'],
            DB::table('notificaciones')->where('usuario_id', $operador->id)->orderBy('tipo')->pluck('tipo')->all(),
        );
        $this->assertSame('Se vendieron 9 de 10 lugares: queda 1.', DB::table('notificaciones')->where('tipo', 'evento_casi_lleno')->value('mensaje'));

        // Solicitud de reembolso: solo administradores, con enlace a la sección
        $reembolso = DB::table('reembolsos')->insertGetId(['pago_id' => $pago, 'monto' => 720, 'estado' => 'solicitado', 'motivo' => 'Viaje', 'created_at' => now(), 'updated_at' => now()]);
        $this->assertSame(1, $personal->reembolsoSolicitado($reembolso));
        $aviso = DB::table('notificaciones')->where('tipo', 'reembolso_solicitado')->first();
        $this->assertSame([$admin->id, 'push', '/admin?seccion=reembolsos'], [(int) $aviso->usuario_id, $aviso->canal, $aviso->url]);
        $this->assertSame("Ana Pérez pidió el reembolso de Q720.00 (80 %) de su compra para «Gala».\n\nMotivo: Viaje", $aviso->mensaje);
        $this->assertCount(5, $this->push->enviados);
        Mail::assertNothingSent();   // los avisos del personal no van por correo
    }

    public function test_cancelar_un_evento_avisa_a_cada_comprador(): void
    {
        Queue::fake([EnviarNotificacionCorreoJob::class]);
        $cliente = $this->usuario('ana@test.com');
        $recinto = DB::table('recintos')->insertGetId(['nombre' => 'Teatro', 'direccion' => 'Z1', 'ciudad' => 'Guatemala', 'created_at' => now(), 'updated_at' => now()]);
        $evento = DB::table('eventos')->insertGetId(['recinto_id' => $recinto, 'nombre' => 'Gala', 'estado' => 'publicado', 'fecha_evento' => now()->addDays(20), 'created_at' => now(), 'updated_at' => now()]);
        $reserva = DB::table('reservas')->insertGetId(['usuario_id' => $cliente->id, 'evento_id' => $evento, 'estado' => 'confirmada', 'expira_en' => now(), 'total' => 100, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('pagos')->insert(['reserva_id' => $reserva, 'monto' => 100, 'estado' => 'aprobado', 'created_at' => now(), 'updated_at' => now()]);

        Sanctum::actingAs($this->usuario('admin@test.com', RolUsuario::ADMIN));
        $this->postJson("/api/eventos/{$evento}/cancelar")->assertOk();

        $aviso = DB::table('notificaciones')->where('tipo', 'evento_cancelado')->first();
        $this->assertSame([$cliente->id, 'Evento cancelado: Gala', '/mis-compras'], [(int) $aviso->usuario_id, $aviso->asunto, $aviso->url]);
        $this->assertStringContainsString('Te devolvemos el 100 %', $aviso->mensaje);
    }

    public function test_las_paginas_llevan_la_llave_publica_para_el_push(): void
    {
        config(['webpush.vapid.public_key' => 'LLAVE-PUBLICA-DE-PRUEBA']);

        $this->get('/mis-compras')->assertOk()->assertSee('<meta name="vapid-public-key" content="LLAVE-PUBLICA-DE-PRUEBA">', false)->assertSee('campana.js');
        $this->get('/admin')->assertOk()->assertSee('LLAVE-PUBLICA-DE-PRUEBA')->assertSee('admin-notificaciones.js');
        $this->assertFileExists(public_path('sw.js'));   // lo sirve nginx
    }
}
