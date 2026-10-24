<?php

namespace Tests\Feature\ColaVirtual;

use App\Domain\Enums\Auth\EstadoUsuario;
use App\Domain\Enums\Auth\RolUsuario;
use App\Models\Auth\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// Fila virtual de punta a punta: avance inmediato (sin esperar al Scheduler), 10 compradores a la
// vez, reservar consume el turno y se puede volver a la fila, activación desde el panel y la
// huella del mapa de asientos (actualización casi al instante).
class FilaVirtualTiendaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config([
            'cola_virtual.max_turnos_simultaneos' => 10,
            'cola_virtual.ventana_compra_minutos' => 5,
            'cola_virtual.procesamiento_automatico_habilitado' => true,
        ]);
    }

    private function usuario(string $email, RolUsuario $rol = RolUsuario::CLIENTE): Usuario
    {
        return Usuario::create([
            'nombre' => 'Ana', 'apellido' => 'López', 'email' => $email, 'password' => Hash::make('Secreto#123'),
            'rol' => $rol, 'estado' => EstadoUsuario::ACTIVO, 'email_verified_at' => now(),
        ]);
    }

    /** Evento publicado con una zona numerada de $lugares asientos con precio. */
    private function evento(int $lugares = 30, bool $fila = true): array
    {
        $recinto = DB::table('recintos')->insertGetId(['nombre' => 'Teatro', 'direccion' => 'Z1', 'ciudad' => 'Guatemala', 'created_at' => now(), 'updated_at' => now()]);
        $evento = DB::table('eventos')->insertGetId([
            'recinto_id' => $recinto, 'nombre' => 'Gala', 'estado' => 'publicado', 'fecha_evento' => now()->addDays(20),
            'cola_virtual_activa' => $fila, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $localidad = DB::table('localidades')->insertGetId(['recinto_id' => $recinto, 'nombre' => 'Platea', 'tipo' => 'numerada', 'capacidad' => $lugares, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('precios')->insert(['evento_id' => $evento, 'localidad_id' => $localidad, 'precio' => 100, 'created_at' => now(), 'updated_at' => now()]);

        $inventario = [];
        for ($n = 1; $n <= $lugares; $n++) {
            $asiento = DB::table('asientos')->insertGetId(['localidad_id' => $localidad, 'fila' => 'A', 'numero' => $n, 'created_at' => now(), 'updated_at' => now()]);
            $inventario[] = DB::table('inventario')->insertGetId([
                'evento_id' => $evento, 'localidad_id' => $localidad, 'asiento_id' => $asiento,
                'estado' => 'disponible', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return [$evento, $inventario];
    }

    // La fila avanza como mucho una vez por segundo por evento: entre pasos se adelanta el reloj
    private function siguienteSegundo(): void
    {
        $this->travel(2)->seconds();
    }

    public function test_con_lugares_libres_se_pasa_de_inmediato_y_solo_diez_a_la_vez(): void
    {
        [$evento] = $this->evento();

        $clientes = [];
        for ($i = 1; $i <= 12; $i++) {
            $clientes[$i] = $this->usuario("cliente{$i}@test.com");
            Sanctum::actingAs($clientes[$i]);
            $estado = $this->postJson('/api/cola-virtual/ingresar', ['evento_id' => $evento])->assertOk()->json('estado');
            // Los primeros 10 entran en el acto (sin esperar al minuto del Scheduler); el 11 y el 12 esperan
            $this->assertSame($i <= 10 ? 'habilitado' : 'esperando', $estado, "cliente {$i}");
            $this->siguienteSegundo();
        }

        Sanctum::actingAs($clientes[12]);
        $this->getJson("/api/cola-virtual/mi-turno/{$evento}")->assertOk()
            ->assertJsonPath('estado', 'esperando')
            ->assertJsonPath('personas_delante', 11)
            ->assertJsonPath('simultaneos', 10)
            ->assertJsonPath('ventana_minutos', 5)
            ->assertJsonPath('intento', 1);

        // Uno de los habilitados reserva: su lugar en la fila pasa al siguiente en la próxima consulta
        DB::table('cola_virtual')->where('usuario_id', $clientes[1]->id)->update(['estado' => 'atendido']);
        $this->siguienteSegundo();
        Sanctum::actingAs($clientes[11]);
        $this->getJson("/api/cola-virtual/mi-turno/{$evento}")->assertOk()->assertJsonPath('estado', 'habilitado');
    }

    public function test_reservar_con_el_turno_lo_usa_y_luego_se_puede_volver_a_la_fila(): void
    {
        [$evento, $inventario] = $this->evento();
        $ana = $this->usuario('ana@test.com');
        Sanctum::actingAs($ana);

        // Sin turno no hay reserva
        $this->postJson('/api/reservas', ['evento_id' => $evento, 'inventario_ids' => [$inventario[0]]])
            ->assertUnprocessable()->assertJsonValidationErrors('cola_virtual');

        $this->postJson('/api/cola-virtual/ingresar', ['evento_id' => $evento])->assertJsonPath('estado', 'habilitado');
        $this->postJson('/api/reservas', ['evento_id' => $evento, 'inventario_ids' => [$inventario[0]]])->assertCreated();
        $this->assertSame('atendido', DB::table('cola_virtual')->where('usuario_id', $ana->id)->value('estado'));

        // Ya usó su turno: para otra reserva vuelve a la fila (al final, con sus dos oportunidades)
        $this->postJson('/api/reservas', ['evento_id' => $evento, 'inventario_ids' => [$inventario[1]]])
            ->assertUnprocessable()->assertJsonValidationErrors('cola_virtual');
        $this->siguienteSegundo();
        $this->postJson('/api/cola-virtual/ingresar', ['evento_id' => $evento])->assertOk()
            ->assertJsonPath('estado', 'habilitado')->assertJsonPath('posicion', 2);
        $this->assertSame(1, (int) DB::table('cola_virtual')->where('usuario_id', $ana->id)->value('intento'));
        $this->assertDatabaseHas('cola_virtual_intentos', ['estado' => 'atendido', 'posicion' => 1]);
    }

    public function test_el_panel_activa_la_fila_y_ve_como_va(): void
    {
        [$evento] = $this->evento(lugares: 5, fila: false);
        DB::table('eventos')->where('id', $evento)->update([
            'imagen_url' => '/storage/eventos/a.webp', 'imagen_url_2' => '/storage/eventos/b.webp', 'imagen_url_3' => '/storage/eventos/c.webp',
        ]);
        $this->getJson("/api/eventos/{$evento}/disponibilidad")->assertJsonPath('evento.cola_virtual', false);

        // El operador la activa en plena venta desde "Modificar evento"
        Sanctum::actingAs($this->usuario('operador@test.com', RolUsuario::ORGANIZADOR));
        $this->patchJson("/api/admin/eventos/{$evento}/contenido", [
            'nombre' => 'Gala', 'cola_virtual_activa' => true,
            'imagen_url' => '/storage/eventos/a.webp', 'imagen_url_2' => '/storage/eventos/b.webp', 'imagen_url_3' => '/storage/eventos/c.webp',
        ])->assertOk()->assertJsonPath('data.cola_virtual_activa', true);
        $this->getJson("/api/eventos/{$evento}/disponibilidad")->assertJsonPath('evento.cola_virtual', true);
        $this->getJson('/api/admin/eventos/catalogo')->assertJsonPath('data.0.cola_virtual', true);

        // Dos clientes en la fila: uno comprando y, como solo hay 5 lugares... ambos caben
        foreach (['a@test.com', 'b@test.com'] as $email) {
            Sanctum::actingAs($this->usuario($email));
            $this->postJson('/api/cola-virtual/ingresar', ['evento_id' => $evento])->assertOk();
            $this->siguienteSegundo();
        }

        Sanctum::actingAs($this->usuario('admin@test.com', RolUsuario::ADMIN));
        $this->getJson("/api/admin/eventos/{$evento}/cola")->assertOk()->assertJson(['data' => [
            'activa' => true, 'esperando' => 0, 'comprando' => 2, 'atendidos' => 0, 'expirados' => 0, 'simultaneos' => 10,
        ]]);

        // Un cliente no ve el resumen
        Sanctum::actingAs($this->usuario('c@test.com'));
        $this->getJson("/api/admin/eventos/{$evento}/cola")->assertForbidden();
    }

    public function test_la_huella_del_mapa_cambia_en_cuanto_alguien_aparta_un_lugar(): void
    {
        [$evento, $inventario] = $this->evento(lugares: 3, fila: false);

        $antes = $this->getJson("/api/eventos/{$evento}/disponibilidad/version")->assertOk()->json('version');
        $this->assertSame($antes, $this->getJson("/api/eventos/{$evento}/disponibilidad/version")->json('version'));

        DB::table('inventario')->where('id', $inventario[0])->update(['estado' => 'bloqueado']);
        $this->siguienteSegundo();   // la huella vive 1 s en caché
        $this->assertNotSame($antes, $this->getJson("/api/eventos/{$evento}/disponibilidad/version")->json('version'));

        // Un evento que no está a la venta no tiene huella
        DB::table('eventos')->where('id', $evento)->update(['estado' => 'borrador']);
        $this->siguienteSegundo();
        $this->getJson("/api/eventos/{$evento}/disponibilidad/version")->assertNotFound();
    }

    public function test_la_sala_de_espera_carga(): void
    {
        [$evento] = $this->evento(lugares: 1);

        $this->get("/eventos/{$evento}/fila")->assertOk()->assertSee('fila-virtual.js')->assertSee('Fila virtual');
    }
}
