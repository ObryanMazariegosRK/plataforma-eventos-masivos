<?php

namespace Tests\Feature\Inventario;

use App\Application\UseCases\Admin\Eventos\PublicarEventoUseCase;
use App\Domain\Enums\Auth\EstadoUsuario;
use App\Domain\Enums\Auth\RolUsuario;
use App\Domain\Exceptions\Inventario\LocalidadesSinPrecioException;
use App\Models\Auth\Usuario as UsuarioModel;
use Database\Seeders\RecintoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InventarioTest extends TestCase
{
    use RefreshDatabase;

    private UsuarioModel $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RecintoSeeder::class);
        $this->admin = $this->crearUsuario('admin@example.com', RolUsuario::ADMIN);
    }

    // ---------- Recintos fijos ----------

    public function test_el_seeder_crea_recintos_de_maximo_100_lugares_y_no_duplica(): void
    {
        $this->seed(RecintoSeeder::class);   // segunda vez

        $this->assertSame(2, DB::table('recintos')->count());
        foreach (DB::table('recintos')->pluck('id') as $recintoId) {
            $capacidad = DB::table('localidades')->where('recinto_id', $recintoId)->sum('capacidad');
            $this->assertLessThanOrEqual(100, $capacidad);
        }
        // en las numeradas, la capacidad coincide con los asientos físicos
        foreach (DB::table('localidades')->where('tipo', 'numerada')->get() as $localidad) {
            $this->assertSame((int) $localidad->capacidad, DB::table('asientos')->where('localidad_id', $localidad->id)->count());
        }
    }

    // ---------- Generación al publicar ----------

    public function test_publicar_genera_un_lugar_por_asiento_y_por_cupo(): void
    {
        $eventoId = $this->crearEvento('Auditorio Central', precios: ['VIP' => 300, 'General' => 120]);

        app(PublicarEventoUseCase::class)->ejecutar($eventoId, $this->admin->id);

        $this->assertDatabaseHas('eventos', ['id' => $eventoId, 'estado' => 'publicado']);
        $vip = $this->localidad('Auditorio Central', 'VIP');
        $general = $this->localidad('Auditorio Central', 'General');
        $this->assertSame(20, DB::table('inventario')->where('evento_id', $eventoId)->where('localidad_id', $vip)->whereNotNull('asiento_id')->count());
        $this->assertSame(60, DB::table('inventario')->where('evento_id', $eventoId)->where('localidad_id', $general)->whereNull('asiento_id')->count());
        $this->assertSame(80, DB::table('inventario')->where('evento_id', $eventoId)->where('estado', 'disponible')->count());
    }

    public function test_no_publica_si_falta_un_precio(): void
    {
        $eventoId = $this->crearEvento('Teatro Soirée', precios: ['Platea' => 200]);   // falta Balcón

        try {
            app(PublicarEventoUseCase::class)->ejecutar($eventoId, $this->admin->id);
            $this->fail('Debió lanzar excepción');
        } catch (LocalidadesSinPrecioException $e) {
            $this->assertStringContainsString('Balcón', $e->getMessage());
        }

        $this->assertDatabaseHas('eventos', ['id' => $eventoId, 'estado' => 'borrador']);
        $this->assertSame(0, DB::table('inventario')->where('evento_id', $eventoId)->count());
    }

    public function test_el_comando_genera_inventario_de_un_evento_ya_publicado(): void
    {
        $eventoId = $this->crearEvento('Teatro Soirée', precios: ['Platea' => 200, 'Balcón' => 150], estado: 'publicado');

        $this->artisan('inventario:generar', ['evento' => $eventoId])->assertSuccessful();
        $this->artisan('inventario:generar', ['evento' => $eventoId])->expectsOutputToContain('ya tenía inventario');

        $this->assertSame(80, DB::table('inventario')->where('evento_id', $eventoId)->count());
    }

    // ---------- Disponibilidad ----------

    public function test_disponibilidad_por_localidad_con_asientos_y_resumen(): void
    {
        $eventoId = $this->publicar('Teatro Soirée', ['Platea' => 200, 'Balcón' => 150]);

        $respuesta = $this->getJson("/api/eventos/{$eventoId}/disponibilidad")->assertOk();

        $respuesta->assertJsonPath('resumen.total', 80)
            ->assertJsonPath('resumen.disponibles', 80)
            ->assertJsonPath('evento.recinto', 'Teatro Soirée');
        $platea = collect($respuesta->json('localidades'))->firstWhere('nombre', 'Platea');
        $this->assertEquals(200, $platea['precio']);
        $this->assertCount(60, $platea['asientos']);
        $this->assertSame(['inventario_id', 'fila', 'numero', 'estado'], array_keys($platea['asientos'][0]));
    }

    public function test_disponibilidad_de_un_evento_en_borrador_responde_404(): void
    {
        $eventoId = $this->crearEvento('Teatro Soirée', precios: ['Platea' => 200, 'Balcón' => 150]);

        $this->getJson("/api/eventos/{$eventoId}/disponibilidad")
            ->assertNotFound()->assertJsonPath('error', 'evento_no_disponible');
    }

    // ---------- Reservas por cantidad (localidades generales) ----------

    public function test_reserva_cupos_generales_por_cantidad(): void
    {
        $eventoId = $this->publicar('Auditorio Central', ['VIP' => 300, 'General' => 120]);
        $general = $this->localidad('Auditorio Central', 'General');
        $vipAsiento = DB::table('inventario')->where('evento_id', $eventoId)->whereNotNull('asiento_id')->value('id');
        $cliente = $this->crearUsuario('ana@example.com');

        $respuesta = $this->withToken($cliente->createToken('t')->plainTextToken)->postJson('/api/reservas', [
            'evento_id'      => $eventoId,
            'inventario_ids' => [$vipAsiento],
            'cupos'          => [['localidad_id' => $general, 'cantidad' => 2]],
        ])->assertCreated();

        $this->assertEquals(300 + 2 * 120, $respuesta->json('total'));
        $this->assertSame(3, DB::table('inventario')->where('reserva_id', $respuesta->json('id'))->where('estado', 'bloqueado')->count());
    }

    public function test_no_reserva_mas_cupos_de_los_que_quedan(): void
    {
        $eventoId = $this->publicar('Auditorio Central', ['VIP' => 300, 'General' => 120]);
        $general = $this->localidad('Auditorio Central', 'General');
        DB::table('inventario')->where('localidad_id', $general)->where('evento_id', $eventoId)->limit(59)
            ->update(['estado' => 'vendido']);   // queda 1
        $cliente = $this->crearUsuario('ana@example.com');

        $this->withToken($cliente->createToken('t')->plainTextToken)->postJson('/api/reservas', [
            'evento_id' => $eventoId,
            'cupos'     => [['localidad_id' => $general, 'cantidad' => 2]],
        ])->assertUnprocessable()->assertJsonValidationErrors('cupos');

        $this->assertSame(0, DB::table('reservas')->count());
    }

    public function test_maximo_de_boletos_por_persona_en_una_sola_reserva(): void
    {
        config(['reservas.max_boletos_por_persona' => 4]);
        $eventoId = $this->publicar('Auditorio Central', ['VIP' => 300, 'General' => 120]);
        $cliente = $this->crearUsuario('ana@example.com');

        $this->withToken($cliente->createToken('t')->plainTextToken)->postJson('/api/reservas', [
            'evento_id' => $eventoId,
            'cupos'     => [['localidad_id' => $this->localidad('Auditorio Central', 'General'), 'cantidad' => 5]],
        ])->assertUnprocessable()->assertJsonPath('error', 'limite_por_persona');
    }

    public function test_el_maximo_por_persona_suma_las_reservas_del_mismo_evento(): void
    {
        $eventoId = $this->publicar('Auditorio Central', ['VIP' => 300, 'General' => 120]);
        $general = $this->localidad('Auditorio Central', 'General');
        $token = $this->crearUsuario('ana@example.com')->createToken('t')->plainTextToken;
        $reservar = fn (int $cantidad) => $this->withToken($token)->postJson('/api/reservas', [
            'evento_id' => $eventoId, 'cupos' => [['localidad_id' => $general, 'cantidad' => $cantidad]],
        ]);

        $primera = $reservar(3)->assertCreated()->json('id');

        // 3 apartados + 3 nuevos = 6 > 5: se avisa cuántos le quedan
        $reservar(3)->assertUnprocessable()
            ->assertJsonPath('error', 'limite_por_persona')
            ->assertJsonPath('message', 'Puedes tener hasta 5 boletos por persona para esta función. Ya tienes 3, así que puedes elegir 2 más.');
        $reservar(2)->assertCreated();

        $cupo = $this->withToken($token)->getJson("/api/eventos/{$eventoId}/mi-cupo")->assertOk();
        $cupo->assertJsonPath('maximo', 5)->assertJsonPath('usados', 5)->assertJsonPath('disponibles', 0)
            // los 5 están apartados sin pagar: la página ofrece continuar el pago o liberarlos
            ->assertJsonPath('pagados', 0)->assertJsonPath('apartados', 5);
        $this->assertSame([3, 2], array_column($cupo->json('pendientes'), 'lugares'));
        $this->assertSame($primera, $cupo->json('pendientes.0.reserva_id'));
        $reservar(1)->assertUnprocessable()
            ->assertJsonPath('message', 'Ya tienes 5 boletos para esta función, el máximo por persona.');

        // una reserva que vence sin pagar devuelve sus lugares al cupo de la persona
        DB::table('reservas')->where('id', $primera)->update(['expira_en' => now()->subMinute()]);
        $this->withToken($token)->getJson("/api/eventos/{$eventoId}/mi-cupo")->assertJsonPath('disponibles', 3);
        $reservar(3)->assertCreated();
    }

    public function test_liberar_una_reserva_pendiente_devuelve_el_cupo(): void
    {
        $eventoId = $this->publicar('Auditorio Central', ['VIP' => 300, 'General' => 120]);
        $general = $this->localidad('Auditorio Central', 'General');
        $token = $this->crearUsuario('ana@example.com')->createToken('t')->plainTextToken;

        $reserva = $this->withToken($token)->postJson('/api/reservas', [
            'evento_id' => $eventoId, 'cupos' => [['localidad_id' => $general, 'cantidad' => 5]],
        ])->assertCreated()->json('id');
        $this->withToken($token)->getJson("/api/eventos/{$eventoId}/mi-cupo")->assertJsonPath('disponibles', 0);

        // Se fue a la pasarela, no pagó y regresó: libera los lugares desde el plano
        $this->withToken($token)->deleteJson("/api/reservas/{$reserva}")->assertOk();

        $this->withToken($token)->getJson("/api/eventos/{$eventoId}/mi-cupo")
            ->assertJsonPath('disponibles', 5)->assertJsonPath('pendientes', []);
        $this->assertSame(0, DB::table('inventario')->where('reserva_id', $reserva)->count());
    }

    public function test_no_se_pueden_apartar_lugares_de_un_evento_que_ya_ocurrio(): void
    {
        $eventoId = $this->publicar('Auditorio Central', ['VIP' => 300, 'General' => 120]);
        $general = $this->localidad('Auditorio Central', 'General');
        $token = $this->crearUsuario('ana@example.com')->createToken('t')->plainTextToken;
        // Sigue "publicado", pero ya pasó su fecha
        DB::table('eventos')->where('id', $eventoId)->update(['fecha_evento' => now()->subHour()]);

        $this->withToken($token)->postJson('/api/reservas', [
            'evento_id' => $eventoId, 'cupos' => [['localidad_id' => $general, 'cantidad' => 1]],
        ])->assertUnprocessable()->assertJsonPath('errors.evento_id.0', 'Este evento ya ocurrió: ya no se venden boletos.');
        $this->assertSame(0, DB::table('reservas')->count());
    }

    public function test_el_maximo_es_por_persona_y_por_evento(): void
    {
        $eventoId = $this->publicar('Auditorio Central', ['VIP' => 300, 'General' => 120]);
        $general = $this->localidad('Auditorio Central', 'General');
        $ana = $this->crearUsuario('ana@example.com')->createToken('t')->plainTextToken;
        $luis = $this->crearUsuario('luis@example.com')->createToken('t')->plainTextToken;

        $this->withToken($ana)->postJson('/api/reservas', [
            'evento_id' => $eventoId, 'cupos' => [['localidad_id' => $general, 'cantidad' => 5]],
        ])->assertCreated();
        $this->app['auth']->forgetGuards();

        // otra persona tiene su propio cupo
        $this->withToken($luis)->getJson("/api/eventos/{$eventoId}/mi-cupo")->assertJsonPath('disponibles', 5);
        $this->withToken($luis)->postJson('/api/reservas', [
            'evento_id' => $eventoId, 'cupos' => [['localidad_id' => $general, 'cantidad' => 5]],
        ])->assertCreated();
    }

    // ---------- Vistas ----------

    public function test_experiencia_del_evento_junta_el_recorrido_y_el_plano(): void
    {
        $eventoId = $this->publicar('Teatro Soirée', ['Platea' => 200, 'Balcón' => 150]);

        $this->get("/eventos/{$eventoId}/experiencia")
            ->assertOk()
            ->assertSee('Gala de prueba')          // nombre del evento en la entrada
            ->assertSee('Teatro Soirée')           // recinto en el detalle
            ->assertSee('data-recorrido', false)   // el recorrido por scroll
            ->assertSee('id="seleccionAsientos"', false);   // y el plano al final
    }

    public function test_experiencia_de_un_evento_que_no_esta_a_la_venta_responde_404(): void
    {
        $eventoId = $this->crearEvento('Teatro Soirée', precios: ['Platea' => 200, 'Balcón' => 150]);   // borrador

        $this->get("/eventos/{$eventoId}/experiencia")->assertNotFound();
        $this->get('/eventos/999999/experiencia')->assertNotFound();
    }

    public function test_las_vistas_del_mapa_cargan(): void
    {
        $this->get('/eventos/1/asientos')->assertOk()->assertSee('Elige tus asientos');
        $this->get('/admin/eventos/1/asientos')->assertOk()->assertSee('Ocupación en vivo');
    }

    // ---------- Ayudantes ----------

    private function publicar(string $recinto, array $precios): int
    {
        $eventoId = $this->crearEvento($recinto, $precios);
        app(PublicarEventoUseCase::class)->ejecutar($eventoId, $this->admin->id);

        return $eventoId;
    }

    private function crearEvento(string $recinto, array $precios, string $estado = 'borrador'): int
    {
        $recintoId = DB::table('recintos')->where('nombre', $recinto)->value('id');
        $eventoId = DB::table('eventos')->insertGetId([
            'recinto_id' => $recintoId, 'nombre' => 'Gala de prueba', 'fecha_evento' => now()->addMonth(),
            'estado' => $estado, 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ($precios as $localidad => $precio) {
            DB::table('precios')->insert([
                'evento_id' => $eventoId, 'localidad_id' => $this->localidad($recinto, $localidad),
                'precio' => $precio, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $eventoId;
    }

    private function localidad(string $recinto, string $nombre): int
    {
        return DB::table('localidades')
            ->join('recintos', 'recintos.id', '=', 'localidades.recinto_id')
            ->where('recintos.nombre', $recinto)->where('localidades.nombre', $nombre)
            ->value('localidades.id');
    }

    private function crearUsuario(string $email, RolUsuario $rol = RolUsuario::CLIENTE): UsuarioModel
    {
        return UsuarioModel::create([
            'nombre' => 'Ana', 'apellido' => 'López', 'email' => $email, 'password' => Hash::make('Secreto#123'),
            'rol' => $rol, 'estado' => EstadoUsuario::ACTIVO, 'email_verified_at' => now(),
        ]);
    }
}
