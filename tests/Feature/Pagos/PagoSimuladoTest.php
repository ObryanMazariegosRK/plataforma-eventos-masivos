<?php

namespace Tests\Feature\Pagos;

use App\Domain\Enums\Auth\EstadoUsuario;
use App\Domain\Enums\Auth\RolUsuario;
use App\Models\Auth\Usuario as UsuarioModel;
use Database\Seeders\PasarelaSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PagoSimuladoTest extends TestCase
{
    use RefreshDatabase;

    private UsuarioModel $usuario;
    private string $token;
    private int $eventoId;
    private int $localidadId;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed(PasarelaSeeder::class);

        $this->usuario = $this->crearUsuario('ana@example.com');
        $this->token = $this->usuario->createToken('pruebas')->plainTextToken;

        $recintoId = DB::table('recintos')->insertGetId([
            'nombre' => 'Estadio', 'direccion' => 'Zona 1', 'ciudad' => 'Guatemala',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->eventoId = DB::table('eventos')->insertGetId([
            'recinto_id' => $recintoId, 'nombre' => 'Concierto', 'fecha_evento' => now()->addMonth(),
            'estado' => 'publicado', 'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->localidadId = DB::table('localidades')->insertGetId([
            'recinto_id' => $recintoId, 'nombre' => 'General', 'tipo' => 'general', 'capacidad' => 100,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_compra_completa_aprobada_emite_boletos(): void
    {
        $reservaId = $this->crearReservaConAsientos(asientos: 2);

        // 1) iniciar
        $inicio = $this->withToken($this->token)->postJson('/api/pagos', ['reserva_id' => $reservaId, 'pasarela' => 'simulada'])
            ->assertCreated()
            ->assertJsonStructure(['pago_id', 'url_aprobacion']);
        $pagoId = $inicio->json('pago_id');

        // 2) la persona aprueba en la página de la pasarela simulada
        $urlPasarela = $inicio->json('url_aprobacion');
        $this->get($urlPasarela)->assertOk()->assertSee('Aprobar pago');
        $this->post($urlPasarela, ['accion' => 'aprobar'])
            ->assertRedirectContains('/pagos/retorno?pago='.$pagoId);

        // 3) confirmar (cobrar)
        $this->withToken($this->token)->postJson("/api/pagos/{$pagoId}/confirmar")
            ->assertOk()
            ->assertJsonPath('estado', 'aprobado')
            ->assertJsonPath('reserva_id', $reservaId);

        $this->assertDatabaseHas('pagos', ['id' => $pagoId, 'estado' => 'aprobado']);
        $this->assertDatabaseHas('reservas', ['id' => $reservaId, 'estado' => 'confirmada']);
        $this->assertSame(2, DB::table('inventario')->where('reserva_id', $reservaId)->where('estado', 'vendido')->count());
        $this->assertDatabaseHas('transacciones', ['pago_id' => $pagoId, 'tipo' => 'venta']);
        // el job (en pruebas corre al momento) usó el código de Boletos de Freyder
        $this->assertSame(2, DB::table('boletos')->where('reserva_id', $reservaId)->count());

        // confirmar otra vez no cobra ni emite de nuevo
        $this->withToken($this->token)->postJson("/api/pagos/{$pagoId}/confirmar")->assertOk();
        $this->assertSame(2, DB::table('boletos')->where('reserva_id', $reservaId)->count());
        $this->assertSame(1, DB::table('transacciones')->where('pago_id', $pagoId)->count());
    }

    public function test_pago_rechazado_deja_la_reserva_activa_y_permite_reintentar(): void
    {
        $reservaId = $this->crearReservaConAsientos(asientos: 1);
        $inicio = $this->withToken($this->token)->postJson('/api/pagos', ['reserva_id' => $reservaId, 'pasarela' => 'simulada']);
        $pagoId = $inicio->json('pago_id');

        $this->post($inicio->json('url_aprobacion'), ['accion' => 'rechazar']);

        $this->withToken($this->token)->postJson("/api/pagos/{$pagoId}/confirmar")
            ->assertStatus(402)
            ->assertJsonPath('error', 'pago_rechazado');

        $this->assertDatabaseHas('pagos', ['id' => $pagoId, 'estado' => 'rechazado']);
        $this->assertDatabaseHas('reservas', ['id' => $reservaId, 'estado' => 'activa']);
        $this->assertDatabaseHas('inventario', ['reserva_id' => $reservaId, 'estado' => 'bloqueado']);
        $this->assertSame(0, DB::table('boletos')->count());

        // segundo intento
        $this->withToken($this->token)->postJson('/api/pagos', ['reserva_id' => $reservaId, 'pasarela' => 'simulada'])
            ->assertCreated();
        $this->assertSame(2, (int) DB::table('pagos')->where('reserva_id', $reservaId)->max('intento'));
    }

    public function test_cancelar_en_la_pasarela_regresa_a_la_pagina_de_cancelado(): void
    {
        $reservaId = $this->crearReservaConAsientos(asientos: 1);
        $inicio = $this->withToken($this->token)->postJson('/api/pagos', ['reserva_id' => $reservaId, 'pasarela' => 'simulada']);

        $this->post($inicio->json('url_aprobacion'), ['accion' => 'cancelar'])
            ->assertRedirectContains('/pagos/cancelado?pago='.$inicio->json('pago_id'));
    }

    public function test_no_se_puede_pagar_ni_confirmar_lo_de_otra_persona(): void
    {
        $reservaId = $this->crearReservaConAsientos(asientos: 1);
        $pagoId = $this->withToken($this->token)
            ->postJson('/api/pagos', ['reserva_id' => $reservaId, 'pasarela' => 'simulada'])->json('pago_id');

        $otro = $this->crearUsuario('otro@example.com')->createToken('t')->plainTextToken;
        $this->app['auth']->forgetGuards();

        $this->withToken($otro)->postJson('/api/pagos', ['reserva_id' => $reservaId, 'pasarela' => 'simulada'])
            ->assertNotFound()->assertJsonPath('error', 'reserva_no_encontrada');
        $this->withToken($otro)->postJson("/api/pagos/{$pagoId}/confirmar")
            ->assertNotFound()->assertJsonPath('error', 'pago_no_encontrado');
    }

    public function test_reserva_vencida_no_se_puede_pagar(): void
    {
        $reservaId = $this->crearReservaConAsientos(asientos: 1);
        DB::table('reservas')->where('id', $reservaId)->update(['expira_en' => now()->subMinute()]);

        $this->withToken($this->token)->postJson('/api/pagos', ['reserva_id' => $reservaId, 'pasarela' => 'simulada'])
            ->assertStatus(409)->assertJsonPath('error', 'reserva_no_pagable');
    }

    public function test_pagar_requiere_token(): void
    {
        $this->postJson('/api/pagos', ['reserva_id' => 1, 'pasarela' => 'simulada'])->assertUnauthorized();
    }

    public function test_lista_las_pasarelas_activas(): void
    {
        $this->getJson('/api/pasarelas')->assertOk()->assertJsonFragment(['codigo' => 'simulada']);
    }

    public function test_las_vistas_de_pago_cargan(): void
    {
        foreach (['/pagar', '/pagos/retorno', '/pagos/cancelado'] as $ruta) {
            $this->get($ruta)->assertOk();
        }
    }

    // ---- Ayudantes ----

    // La reserva se crea por la API real; los asientos se insertan a mano porque
    // CrearReserva todavía no los asigna (lo está haciendo el módulo de Reservas).
    private function crearReservaConAsientos(int $asientos): int
    {
        $reservaId = $this->withToken($this->token)
            ->postJson('/api/reservas', ['evento_id' => $this->eventoId, 'total' => 250])
            ->assertCreated()
            ->json('id');

        for ($i = 0; $i < $asientos; $i++) {
            DB::table('inventario')->insert([
                'evento_id' => $this->eventoId, 'localidad_id' => $this->localidadId, 'asiento_id' => null,
                'estado' => 'bloqueado', 'reserva_id' => $reservaId, 'bloqueado_hasta' => now()->addMinutes(5),
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $reservaId;
    }

    private function crearUsuario(string $email): UsuarioModel
    {
        return UsuarioModel::create([
            'nombre' => 'Ana', 'apellido' => 'López', 'email' => $email, 'password' => Hash::make('Secreto#123'),
            'rol' => RolUsuario::CLIENTE, 'estado' => EstadoUsuario::ACTIVO, 'email_verified_at' => now(),
        ]);
    }
}
