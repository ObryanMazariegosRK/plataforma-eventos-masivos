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
            'recinto_id' => $recintoId, 'nombre' => 'Platea', 'tipo' => 'numerada', 'capacidad' => 100,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        // precio oficial de la localidad: Reservas calcula el total con esta tabla
        DB::table('precios')->insert([
            'evento_id' => $this->eventoId, 'localidad_id' => $this->localidadId, 'precio' => 125,
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
        $this->get($urlPasarela)->assertOk()->assertSee('Llenado automático')->assertDontSee('fondos insuficientes');
        $this->post($urlPasarela, $this->tarjeta('aprobada'))
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

        $this->post($inicio->json('url_aprobacion'), $this->tarjeta('sin_fondos'));

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

    public function test_datos_de_tarjeta_incorrectos_no_aprueban_ni_rechazan_la_orden(): void
    {
        $reservaId = $this->crearReservaConAsientos(asientos: 1);
        $inicio = $this->withToken($this->token)->postJson('/api/pagos', ['reserva_id' => $reservaId, 'pasarela' => 'simulada']);
        $url = $inicio->json('url_aprobacion');

        // CVV equivocado: vuelve a la pasarela con el aviso, sin decidir la orden
        $this->post($url, ['cvv' => '999'] + $this->tarjeta('aprobada'))
            ->assertRedirect($url)
            ->assertSessionHasErrors('tarjeta');
        $this->get($url)->assertOk()->assertSee('Los datos de la tarjeta no son correctos');

        // la orden sigue pendiente: confirmar todavía no cobra
        $this->withToken($this->token)->postJson('/api/pagos/'.$inicio->json('pago_id').'/confirmar')->assertStatus(402);

        // los botones viejos ya no existen
        $this->post($url, ['accion' => 'aprobar'])->assertSessionHasErrors('accion');
    }

    public function test_volver_a_la_pasarela_despues_de_pagar_no_cobra_dos_veces(): void
    {
        $reservaId = $this->crearReservaConAsientos(asientos: 1);
        $inicio = $this->withToken($this->token)->postJson('/api/pagos', ['reserva_id' => $reservaId, 'pasarela' => 'simulada']);
        $url = $inicio->json('url_aprobacion');
        $this->post($url, $this->tarjeta('aprobada'));
        $this->withToken($this->token)->postJson('/api/pagos/'.$inicio->json('pago_id').'/confirmar')->assertOk();

        // "Atrás": ya no aparece el formulario, sino el aviso con el enlace al resultado
        $this->get($url)->assertOk()
            ->assertSee('Este pago ya fue procesado')
            ->assertSee('/pagos/retorno?pago='.$inicio->json('pago_id'), false)
            ->assertDontSee('Llenado automático');

        // reenviar el formulario tampoco procesa nada: vuelve al aviso
        $this->post($url, $this->tarjeta('aprobada'))->assertRedirect($url);
        $this->post($url, ['accion' => 'cancelar'])->assertRedirect($url);
        $this->assertSame(1, DB::table('transacciones')->count());
    }

    public function test_una_orden_rechazada_ofrece_intentar_de_nuevo(): void
    {
        $reservaId = $this->crearReservaConAsientos(asientos: 1);
        $url = $this->withToken($this->token)
            ->postJson('/api/pagos', ['reserva_id' => $reservaId, 'pasarela' => 'simulada'])->json('url_aprobacion');
        $this->post($url, $this->tarjeta('sin_fondos'));

        $this->get($url)->assertOk()->assertSee('El pago fue rechazado')->assertSee('intentar de nuevo');
    }

    public function test_la_orden_vence_junto_con_la_reserva(): void
    {
        $reservaId = $this->crearReservaConAsientos(asientos: 1);
        $inicio = $this->withToken($this->token)->postJson('/api/pagos', ['reserva_id' => $reservaId, 'pasarela' => 'simulada']);
        $url = $inicio->json('url_aprobacion');

        // un segundo antes de que venza la reserva todavía se puede pagar...
        $this->travel(config('reservas.ttl_segundos') - 1)->seconds();
        $this->get($url)->assertOk()->assertSee('Llenado automático');

        // ...y al vencer la reserva, la pasarela ya no acepta la tarjeta (aunque falten
        // minutos para el tope de pagos.simulada.minutos_orden)
        $this->travel(2)->seconds();
        $this->get($url)->assertOk()
            ->assertSee('Esta orden de pago venció')
            ->assertSee('Volver a la cartelera')
            ->assertDontSee('Llenado automático');
        $this->post($url, $this->tarjeta('aprobada'))->assertRedirect($url);

        // nada quedó aprobado y no se cobra
        $this->withToken($this->token)->postJson('/api/pagos/'.$inicio->json('pago_id').'/confirmar')
            ->assertStatus(402);
        $this->assertSame(0, DB::table('transacciones')->count());
    }

    public function test_una_orden_que_no_existe_muestra_un_aviso(): void
    {
        $this->get('/pasarela-simulada/SIM-NOEXISTE')->assertNotFound()->assertSee('No encontramos esta orden de pago');
        $this->post('/pasarela-simulada/SIM-NOEXISTE', $this->tarjeta('aprobada'))
            ->assertRedirect('/pasarela-simulada/SIM-NOEXISTE');
    }

    public function test_la_pagina_de_pago_aprobado_obtiene_el_evento_y_los_lugares(): void
    {
        $reservaId = $this->crearReservaConAsientos(asientos: 2);

        $this->withToken($this->token)->getJson("/api/reservas/{$reservaId}")
            ->assertOk()
            ->assertJsonPath('evento.nombre', 'Concierto')
            ->assertJsonPath('evento.recinto', 'Estadio')
            ->assertJsonCount(2, 'lugares')
            ->assertJsonPath('lugares.0.localidad', 'Platea')
            ->assertJsonPath('lugares.0.fila', 'A')
            ->assertJsonMissingPath('usuario_id');

        // la reserva de otra persona no se puede consultar (ni se revela que existe)
        $otro = $this->crearUsuario('otro-detalle@example.com')->createToken('t')->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withToken($otro)->getJson("/api/reservas/{$reservaId}")
            ->assertNotFound()->assertJsonPath('error', 'reserva_no_encontrada');
    }

    public function test_mis_compras_lista_solo_las_reservas_pagadas_del_usuario(): void
    {
        // una compra pagada y una reserva sin pagar
        $pagada = $this->crearReservaConAsientos(asientos: 2);
        $inicio = $this->withToken($this->token)->postJson('/api/pagos', ['reserva_id' => $pagada, 'pasarela' => 'simulada']);
        $this->post($inicio->json('url_aprobacion'), $this->tarjeta('aprobada'));
        $this->withToken($this->token)->postJson('/api/pagos/'.$inicio->json('pago_id').'/confirmar')->assertOk();
        $this->crearReservaConAsientos(asientos: 1);

        $this->withToken($this->token)->getJson('/api/mis-compras')
            ->assertOk()
            ->assertJsonCount(1, 'proximas')
            ->assertJsonCount(0, 'pasadas')
            ->assertJsonPath('proximas.0.id', $pagada)
            ->assertJsonPath('proximas.0.evento.nombre', 'Concierto')
            ->assertJsonCount(2, 'proximas.0.lugares')
            ->assertJsonPath('proximas.0.referencia', fn ($ref) => str_starts_with($ref, 'SIMCAP-'))
            ->assertJsonPath('proximas.0.pagado_en', fn ($fecha) => $fecha !== null);

        // otra persona no ve esa compra
        $otro = $this->crearUsuario('otro-compras@example.com')->createToken('t')->plainTextToken;
        $this->app['auth']->forgetGuards();
        $this->withToken($otro)->getJson('/api/mis-compras')
            ->assertOk()->assertExactJson(['proximas' => [], 'pasadas' => []]);

        $this->get('/mis-compras')->assertOk()->assertSee('Mis <em>compras</em>', false);
    }

    public function test_despues_de_pagar_cada_lugar_tiene_su_boleto_con_qr(): void
    {
        $reservaId = $this->crearReservaConAsientos(asientos: 2);
        $inicio = $this->withToken($this->token)->postJson('/api/pagos', ['reserva_id' => $reservaId, 'pasarela' => 'simulada']);
        $this->post($inicio->json('url_aprobacion'), $this->tarjeta('aprobada'));
        $this->withToken($this->token)->postJson('/api/pagos/'.$inicio->json('pago_id').'/confirmar')->assertOk();

        // la compra trae el boleto de cada lugar (sin exponer el código secreto)
        $lugares = $this->withToken($this->token)->getJson("/api/reservas/{$reservaId}")
            ->assertOk()
            ->assertJsonPath('lugares.0.boleto.estado', 'emitido')
            ->assertJsonPath('lugares.1.boleto.estado', 'emitido')
            ->json('lugares');
        $this->assertStringNotContainsString(
            DB::table('boletos')->value('codigo_qr'),
            $this->withToken($this->token)->getJson("/api/reservas/{$reservaId}")->getContent()
        );

        // el QR de ese boleto (módulo Boletos) es una imagen SVG
        $this->withToken($this->token)->get('/api/boletos/'.$lugares[0]['boleto']['id'].'/qr')
            ->assertOk()
            ->assertHeader('Content-Type', 'image/svg+xml; charset=UTF-8');

        $this->get("/mis-compras/{$reservaId}/boletos")->assertOk()->assertSee('Tus boletos');
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

    // Igual que el frontend: hay asientos DISPONIBLES, el cliente elige cuáles
    // y la API de Reservas los bloquea y calcula el total (125 por asiento).
    private function crearReservaConAsientos(int $asientos): int
    {
        static $numero = 0;
        $inventarioIds = [];

        for ($i = 0; $i < $asientos; $i++) {
            $asientoId = DB::table('asientos')->insertGetId([
                'localidad_id' => $this->localidadId, 'fila' => 'A', 'numero' => ++$numero,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $inventarioIds[] = DB::table('inventario')->insertGetId([
                'evento_id' => $this->eventoId, 'localidad_id' => $this->localidadId, 'asiento_id' => $asientoId,
                'estado' => 'disponible', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $respuesta = $this->withToken($this->token)
            ->postJson('/api/reservas', ['evento_id' => $this->eventoId, 'inventario_ids' => $inventarioIds])
            ->assertCreated();

        // assertEquals (no assertSame): en JSON, 250.0 llega como 250 y 250.50 como 250.5;
        // importa el valor, no si PHP lo lee como entero o decimal.
        $this->assertEquals(125 * $asientos, $respuesta->json('total'));

        return $respuesta->json('id');
    }

    private function crearUsuario(string $email): UsuarioModel
    {
        return UsuarioModel::create([
            'nombre' => 'Ana', 'apellido' => 'López', 'email' => $email, 'password' => Hash::make('Secreto#123'),
            'rol' => RolUsuario::CLIENTE, 'estado' => EstadoUsuario::ACTIVO, 'email_verified_at' => now(),
        ]);
    }

    // Datos que se escriben en la pasarela simulada (config pagos.simulada.tarjetas)
    private function tarjeta(string $tipo): array
    {
        $tarjeta = config("pagos.simulada.tarjetas.{$tipo}");

        return [
            'accion'  => 'pagar',
            'titular' => 'Ana López',
            'numero'  => trim(chunk_split($tarjeta['numero'], 4, ' ')),   // con espacios, como en el formulario
            'vence'   => $tarjeta['vence'],
            'cvv'     => $tarjeta['cvv'],
        ];
    }
}
