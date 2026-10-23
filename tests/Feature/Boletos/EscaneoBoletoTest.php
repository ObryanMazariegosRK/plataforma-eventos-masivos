<?php

namespace Tests\Feature\Boletos;

use App\Application\Abstractions\Boletos\IEmitirBoletosUseCase;
use App\Domain\Enums\Auth\EstadoUsuario;
use App\Domain\Enums\Auth\RolUsuario;
use App\Models\Auth\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// QR escaneado (/b#<código>): el personal ve si puede entrar y registra la entrada con un toque;
// el dueño va a sus boletos; cualquier otra persona, a la tienda sin saber nada del boleto.
class EscaneoBoletoTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(string $email, RolUsuario $rol = RolUsuario::CLIENTE): Usuario
    {
        return Usuario::create([
            'nombre' => 'Ana', 'apellido' => 'Pérez', 'email' => $email, 'password' => bcrypt('Secreto#123'),
            'rol' => $rol, 'estado' => EstadoUsuario::ACTIVO, 'email_verified_at' => now(),
        ]);
    }

    /** Boleto emitido de la butaca A7 para una función a las 20:00 (Guatemala) de $dia. */
    private function boleto(Usuario $duenio, $dia): object
    {
        $recinto = DB::table('recintos')->insertGetId(['nombre' => 'Teatro Soirée', 'direccion' => 'Z1', 'ciudad' => 'Guatemala', 'created_at' => now(), 'updated_at' => now()]);
        $platea = DB::table('localidades')->insertGetId(['recinto_id' => $recinto, 'nombre' => 'Platea', 'tipo' => 'numerada', 'capacidad' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $asiento = DB::table('asientos')->insertGetId(['localidad_id' => $platea, 'fila' => 'A', 'numero' => 7, 'created_at' => now(), 'updated_at' => now()]);
        $fecha = $dia->copy()->setTimezone('America/Guatemala')->setTime(20, 0)->utc();
        $evento = DB::table('eventos')->insertGetId(['recinto_id' => $recinto, 'nombre' => 'Gala de ópera', 'estado' => 'publicado', 'fecha_evento' => $fecha, 'created_at' => now(), 'updated_at' => now()]);
        $reserva = DB::table('reservas')->insertGetId(['usuario_id' => $duenio->id, 'evento_id' => $evento, 'estado' => 'confirmada', 'expira_en' => now(), 'total' => 100, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('inventario')->insert(['evento_id' => $evento, 'localidad_id' => $platea, 'asiento_id' => $asiento, 'estado' => 'vendido', 'reserva_id' => $reserva, 'created_at' => now(), 'updated_at' => now()]);
        $pago = DB::table('pagos')->insertGetId(['reserva_id' => $reserva, 'monto' => 100, 'estado' => 'aprobado', 'created_at' => now(), 'updated_at' => now()]);
        app(IEmitirBoletosUseCase::class)->execute($pago);

        return DB::table('boletos')->where('reserva_id', $reserva)->first();
    }

    public function test_cada_quien_ve_lo_suyo_al_escanear(): void
    {
        $duenio = $this->usuario('duenio@test.com');
        $boleto = $this->boleto($duenio, now()->addDays(3));
        $url = "http://localhost:8090/b#{$boleto->codigo_qr}";

        Sanctum::actingAs($duenio);
        $this->postJson('/api/boletos/consultar', ['codigo' => $url])->assertOk()
            ->assertExactJson(['data' => ['vista' => 'propietario', 'reserva_id' => $boleto->reserva_id, 'boleto_id' => $boleto->id]]);

        // Otro cliente: a la tienda, sin pistas de si el código existe
        Sanctum::actingAs($this->usuario('otro@test.com'));
        $this->postJson('/api/boletos/consultar', ['codigo' => $url])->assertOk()->assertExactJson(['data' => ['vista' => 'tienda']]);
        $this->postJson('/api/boletos/consultar', ['codigo' => str_repeat('a', 64)])->assertOk()->assertExactJson(['data' => ['vista' => 'tienda']]);
        $this->postJson('/api/boletos/validar', ['codigo_qr' => $boleto->codigo_qr, 'evento_id' => 1])->assertForbidden();

        // Sin sesión no hay consulta (la página /b manda a la tienda)
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/boletos/consultar', ['codigo' => $url], ['Authorization' => ''])->assertUnauthorized();
        $this->get('/b')->assertOk()->assertSee('entrada-boleto.js');
    }

    public function test_el_operador_registra_la_entrada_y_un_segundo_escaneo_lo_detecta(): void
    {
        $boleto = $this->boleto($this->usuario('duenio@test.com'), now());
        Sanctum::actingAs($this->usuario('portero@test.com', RolUsuario::ORGANIZADOR));

        // Escanear no gasta el boleto
        $datos = $this->postJson('/api/boletos/consultar', ['codigo' => "http://soiree.test/b#{$boleto->codigo_qr}"])->assertOk()->json('data');
        $this->assertSame(['personal', true, true, null], [$datos['vista'], $datos['puede_entrar'], $datos['es_hoy'], $datos['motivo']]);
        $this->assertSame(['Platea', 'A', 7], [$datos['lugar']['localidad'], $datos['lugar']['fila'], $datos['lugar']['numero']]);
        $this->assertSame('Ana Pérez', $datos['boleto']['titular']);
        $this->assertSame('emitido', DB::table('boletos')->value('estado'));

        $this->postJson('/api/boletos/validar', ['codigo_qr' => $datos['codigo'], 'evento_id' => $datos['evento']['id']])
            ->assertOk()->assertJsonPath('boleto.estado', 'usado');

        $otra = $this->postJson('/api/boletos/consultar', ['codigo' => $boleto->codigo_qr])->json('data');
        $this->assertFalse($otra['puede_entrar']);
        $this->assertSame('usado', $otra['boleto']['estado']);
        $this->assertNotNull($otra['boleto']['usado_en']);
        $this->postJson('/api/boletos/validar', ['codigo_qr' => $boleto->codigo_qr, 'evento_id' => $datos['evento']['id']])->assertStatus(409);

        $this->assertFalse($this->postJson('/api/boletos/consultar', ['codigo' => 'no-es-un-boleto'])->json('data.encontrado'));
    }

    public function test_avisa_si_la_funcion_no_es_hoy_y_rechaza_anulados_o_pasados(): void
    {
        Sanctum::actingAs($this->usuario('admin@test.com', RolUsuario::ADMIN));

        $futuro = $this->boleto($this->usuario('a@test.com'), now()->addDays(2));
        $datos = $this->postJson('/api/boletos/consultar', ['codigo' => $futuro->codigo_qr])->json('data');
        $this->assertSame([true, false], [$datos['puede_entrar'], $datos['es_hoy']]);

        DB::table('boletos')->where('id', $futuro->id)->update(['estado' => 'anulado']);
        $datos = $this->postJson('/api/boletos/consultar', ['codigo' => $futuro->codigo_qr])->json('data');
        $this->assertFalse($datos['puede_entrar']);
        $this->assertStringContainsString('anulado', $datos['motivo']);

        $pasado = $this->boleto($this->usuario('b@test.com'), now()->subDays(2));
        $this->assertSame('Esta función ya pasó.', $this->postJson('/api/boletos/consultar', ['codigo' => $pasado->codigo_qr])->json('data.motivo'));
    }
}
