<?php

namespace Tests\Feature\Admin;

use App\Domain\Enums\Auth\EstadoUsuario;
use App\Domain\Enums\Auth\RolUsuario;
use App\Jobs\Notificaciones\EnviarNotificacionCorreoJob;
use App\Models\Auth\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// Fecha de las funciones: se escribe en hora de Guatemala, futura y hasta 3 meses adelante; un evento
// publicado solo se puede aplazar y, si cambia, se avisa por correo a quienes compraron.
class ReprogramarEventoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-23 18:00:00');   // 12:00 en Guatemala
        Queue::fake([EnviarNotificacionCorreoJob::class]);
    }

    private function usuario(string $email, RolUsuario $rol = RolUsuario::CLIENTE): Usuario
    {
        return Usuario::create([
            'nombre' => 'Ana', 'apellido' => 'Pérez', 'email' => $email, 'password' => bcrypt('Secreto#123'),
            'rol' => $rol, 'estado' => EstadoUsuario::ACTIVO, 'email_verified_at' => now(),
        ]);
    }

    private function recinto(): array
    {
        $recinto = DB::table('recintos')->insertGetId(['nombre' => 'Teatro', 'direccion' => 'Z1', 'ciudad' => 'Guatemala', 'estado' => 'activo', 'created_at' => now(), 'updated_at' => now()]);
        $general = DB::table('localidades')->insertGetId(['recinto_id' => $recinto, 'nombre' => 'General', 'tipo' => 'general', 'capacidad' => 10, 'created_at' => now(), 'updated_at' => now()]);

        return [$recinto, $general];
    }

    private function datosEvento(int $recinto, int $localidad, string $fecha): array
    {
        return [
            'nombre' => 'Gala de ópera', 'recinto_id' => $recinto, 'fecha_evento' => $fecha,
            'imagen_url' => '/storage/eventos/a.webp', 'imagen_url_2' => '/storage/eventos/b.webp', 'imagen_url_3' => '/storage/eventos/c.webp',
            'precios' => [['localidad_id' => $localidad, 'precio' => 100]],
            'costos' => [
                ['categoria' => 'recinto', 'concepto' => 'Alquiler del recinto', 'monto' => 500],
                ['categoria' => 'artistas', 'concepto' => 'Artistas', 'monto' => 800],
            ],
        ];
    }

    public function test_al_crear_la_fecha_es_futura_hasta_tres_meses_y_en_hora_de_guatemala(): void
    {
        [$recinto, $general] = $this->recinto();
        Sanctum::actingAs($this->usuario('operador@test.com', RolUsuario::ORGANIZADOR));

        $this->postJson('/api/admin/eventos', $this->datosEvento($recinto, $general, '2026-10-22T20:00'))
            ->assertStatus(422)->assertJsonPath('errors.fecha_evento.0', 'La función debe ser en una fecha y hora futuras.');
        $this->postJson('/api/admin/eventos', $this->datosEvento($recinto, $general, '2027-01-24T20:00'))
            ->assertStatus(422)->assertJsonPath('errors.fecha_evento.0', 'Puedes programar funciones hasta con 3 meses de anticipación (hasta el 23 de enero de 2027).');

        $this->postJson('/api/admin/eventos', $this->datosEvento($recinto, $general, '2027-01-23T20:00'))->assertCreated();
        // 20:00 en Guatemala = 02:00 UTC del día siguiente
        $this->assertSame('2027-01-24 02:00:00', (string) DB::table('eventos')->value('fecha_evento'));
    }

    public function test_un_evento_publicado_solo_se_aplaza_y_se_avisa_a_cada_comprador(): void
    {
        [$recinto, $general] = $this->recinto();
        $evento = DB::table('eventos')->insertGetId([
            'recinto_id' => $recinto, 'nombre' => 'Gala de ópera', 'estado' => 'publicado',
            'fecha_evento' => '2026-11-06 02:00:00',   // jueves 5 de noviembre, 8:00 p. m. en Guatemala
            'imagen_url' => '/storage/eventos/a.webp', 'imagen_url_2' => '/storage/eventos/b.webp', 'imagen_url_3' => '/storage/eventos/c.webp',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        // Ana compró dos veces; Luis una; Marta solo apartó (no pagó)
        $compras = [['ana@test.com', 2, 'confirmada'], ['luis@test.com', 1, 'confirmada'], ['marta@test.com', 1, 'activa']];
        foreach ($compras as [$email, $veces, $estado]) {
            $cliente = $this->usuario($email);
            for ($i = 0; $i < $veces; $i++) {
                $reserva = DB::table('reservas')->insertGetId(['usuario_id' => $cliente->id, 'evento_id' => $evento, 'estado' => $estado, 'expira_en' => now()->addMinutes(5), 'total' => 100, 'created_at' => now(), 'updated_at' => now()]);
                DB::table('pagos')->insert(['reserva_id' => $reserva, 'monto' => 100, 'estado' => $estado === 'confirmada' ? 'aprobado' : 'pendiente', 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        Sanctum::actingAs($this->usuario('admin@test.com', RolUsuario::ADMIN));
        $contenido = fn (array $extra = []) => [
            'nombre' => 'Gala de ópera', 'imagen_url' => '/storage/eventos/a.webp',
            'imagen_url_2' => '/storage/eventos/b.webp', 'imagen_url_3' => '/storage/eventos/c.webp',
        ] + $extra;

        // Adelantarla no: quien compró para el 5 de noviembre no puede encontrarse con otra fecha antes
        $this->patchJson("/api/admin/eventos/{$evento}/contenido", $contenido(['fecha_evento' => '2026-11-03T20:00']))
            ->assertStatus(422)
            ->assertJsonPath('errors.fecha_evento.0', 'Solo puedes aplazar la función: elige el jueves 5 de noviembre, 8:00 p. m. o una fecha posterior.');

        // La misma fecha (como la muestra el formulario) no es un cambio: nadie recibe correo
        $this->patchJson("/api/admin/eventos/{$evento}/contenido", $contenido(['fecha_evento' => '2026-11-05T20:00']))
            ->assertOk()->assertJsonPath('fecha_cambiada', false);
        $this->assertSame(0, DB::table('notificaciones')->count());
        $this->assertNull(DB::table('eventos')->value('reprogramado_en'));

        $this->patchJson("/api/admin/eventos/{$evento}/contenido", $contenido(['fecha_evento' => '2026-11-12T21:30']))
            ->assertOk()
            ->assertJsonPath('fecha_cambiada', true)
            ->assertJsonPath('compradores_avisados', 2);

        $this->assertSame('2026-11-13 03:30:00', (string) DB::table('eventos')->value('fecha_evento'));
        $this->assertNotNull(DB::table('eventos')->value('reprogramado_en'));   // habilita el reembolso del 100 %
        $mensaje = DB::table('notificaciones')->value('mensaje');
        $this->assertStringContainsString('Antes: jueves 5 de noviembre de 2026, 8:00 p. m.', $mensaje);
        $this->assertStringContainsString('Ahora: jueves 12 de noviembre de 2026, 9:30 p. m.', $mensaje);
        $this->assertSame('Cambio de fecha: Gala de ópera', DB::table('notificaciones')->value('asunto'));
        Queue::assertPushed(EnviarNotificacionCorreoJob::class, 2);

        // Sin fecha en la petición, solo se edita el contenido
        $this->patchJson("/api/admin/eventos/{$evento}/contenido", $contenido(['nombre' => 'Gala de ópera 2026']))
            ->assertOk()->assertJsonPath('fecha_cambiada', false);
        $this->assertSame(2, DB::table('notificaciones')->count());
    }

    public function test_el_operador_gestiona_los_eventos_publicados_igual_que_el_admin(): void
    {
        [$recinto] = $this->recinto();
        $evento = DB::table('eventos')->insertGetId([
            'recinto_id' => $recinto, 'nombre' => 'Gala de ópera', 'estado' => 'publicado',
            'fecha_evento' => '2026-11-06 02:00:00',
            'imagen_url' => '/storage/eventos/a.webp', 'imagen_url_2' => '/storage/eventos/b.webp', 'imagen_url_3' => '/storage/eventos/c.webp',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $contenido = [
            'nombre' => 'Gala de ópera 2026', 'imagen_url' => '/storage/eventos/a.webp',
            'imagen_url_2' => '/storage/eventos/b.webp', 'imagen_url_3' => '/storage/eventos/c.webp',
        ];

        // Un cliente no puede tocar nada
        Sanctum::actingAs($this->usuario('cliente@test.com'));
        $this->patchJson("/api/admin/eventos/{$evento}/contenido", $contenido)->assertForbidden();
        $this->postJson("/api/eventos/{$evento}/cancelar")->assertForbidden();

        // El operador modifica (y aplaza) y cancela, como el admin
        Sanctum::actingAs($this->usuario('operador@test.com', RolUsuario::ORGANIZADOR));
        $this->patchJson("/api/admin/eventos/{$evento}/contenido", $contenido + ['fecha_evento' => '2026-11-12T21:30'])
            ->assertOk()->assertJsonPath('fecha_cambiada', true);
        $this->assertSame('Gala de ópera 2026', DB::table('eventos')->value('nombre'));

        $this->postJson("/api/eventos/{$evento}/cancelar")->assertOk();
        $this->assertSame('cancelado', DB::table('eventos')->value('estado'));
    }
}
