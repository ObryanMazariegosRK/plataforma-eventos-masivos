<?php

namespace Tests\Feature\Bitacora;

use App\Domain\Enums\Auth\EstadoUsuario;
use App\Domain\Enums\Auth\RolUsuario;
use App\Models\Auth\Usuario;
use Database\Seeders\PasarelaSeeder;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BitacoraTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // phpunit.xml la apaga para el resto de tests; aquí se usa una base SQLite en memoria aparte
        config(['bitacora.habilitada' => true]);
        $this->artisan('migrate', ['--database' => 'bitacora', '--path' => 'database/migrations/bitacora'])->assertSuccessful();
    }

    private function bitacora(): Builder
    {
        return DB::connection('bitacora')->table('bitacora');
    }

    private function usuario(RolUsuario $rol, string $nombre = 'Ana', ?string $email = null): Usuario
    {
        return Usuario::create([
            'nombre' => $nombre, 'apellido' => 'López', 'email' => $email ?? $rol->value.'@test.com',
            'password' => bcrypt('Secreto#123'), 'rol' => $rol, 'estado' => EstadoUsuario::ACTIVO,
            'email_verified_at' => now(),
        ]);
    }

    private function eventoBorrador(string $nombre = 'Gala de ópera'): array
    {
        $recinto = DB::table('recintos')->insertGetId(['nombre' => 'Teatro', 'direccion' => 'Z1', 'ciudad' => 'Guatemala', 'created_at' => now(), 'updated_at' => now()]);
        $evento = DB::table('eventos')->insertGetId([
            'recinto_id' => $recinto, 'nombre' => $nombre, 'estado' => 'borrador',
            'fecha_evento' => now()->addDays(10), 'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$evento, $recinto];
    }

    public function test_registra_inicios_de_sesion_fallidos_y_exitosos_sin_guardar_la_contrasena(): void
    {
        $ana = $this->usuario(RolUsuario::CLIENTE);

        $this->postJson('/api/auth/login', ['email' => $ana->email, 'password' => 'Equivocada#1'])->assertStatus(401);
        $this->postJson('/api/auth/login', ['email' => $ana->email, 'password' => 'Secreto#123'])->assertOk();

        $filas = $this->bitacora()->orderBy('id')->get();
        $this->assertSame(['sesion.inicio_fallido', 'sesion.inicio'], $filas->pluck('accion')->all());
        $this->assertSame(['fallido', 'exito'], $filas->pluck('resultado')->all());
        $this->assertStringContainsString('contraseña incorrecta', $filas[0]->descripcion);
        $this->assertNull($filas[0]->usuario_id);                   // falló: no hay sesión, solo el correo intentado
        $this->assertSame($ana->email, $filas[0]->usuario_correo);
        $this->assertSame($ana->id, (int) $filas[1]->usuario_id);   // entró: se sabe quién es por el token
        $this->assertSame('Ana López', $filas[1]->usuario_nombre);
        $this->assertSame('cliente', $filas[1]->usuario_rol);

        $todo = json_encode($filas);
        $this->assertStringNotContainsString('Secreto#123', $todo);
        $this->assertStringNotContainsString('Equivocada#1', $todo);
    }

    public function test_registra_la_compra_completa_y_el_pago_rechazado(): void
    {
        Mail::fake();
        $this->seed(PasarelaSeeder::class);
        $token = $this->usuario(RolUsuario::CLIENTE)->createToken('pruebas')->plainTextToken;

        $recinto = DB::table('recintos')->insertGetId(['nombre' => 'Estadio', 'direccion' => 'Z1', 'ciudad' => 'Guatemala', 'created_at' => now(), 'updated_at' => now()]);
        $evento = DB::table('eventos')->insertGetId(['recinto_id' => $recinto, 'nombre' => 'Concierto', 'fecha_evento' => now()->addMonth(), 'estado' => 'publicado', 'created_at' => now(), 'updated_at' => now()]);
        $localidad = DB::table('localidades')->insertGetId(['recinto_id' => $recinto, 'nombre' => 'Platea', 'tipo' => 'numerada', 'capacidad' => 10, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('precios')->insert(['evento_id' => $evento, 'localidad_id' => $localidad, 'precio' => 125, 'created_at' => now(), 'updated_at' => now()]);

        $comprar = function (string $tarjeta) use ($token, $evento, $localidad) {
            $asiento = DB::table('asientos')->insertGetId(['localidad_id' => $localidad, 'fila' => 'A', 'numero' => random_int(1, 999), 'created_at' => now(), 'updated_at' => now()]);
            $lugar = DB::table('inventario')->insertGetId(['evento_id' => $evento, 'localidad_id' => $localidad, 'asiento_id' => $asiento, 'estado' => 'disponible', 'created_at' => now(), 'updated_at' => now()]);
            $reserva = $this->withToken($token)->postJson('/api/reservas', ['evento_id' => $evento, 'inventario_ids' => [$lugar]])->assertCreated()->json('id');
            $inicio = $this->withToken($token)->postJson('/api/pagos', ['reserva_id' => $reserva, 'pasarela' => 'simulada'])->assertCreated();
            $datos = config("pagos.simulada.tarjetas.{$tarjeta}");
            $this->post($inicio->json('url_aprobacion'), ['accion' => 'pagar', 'titular' => 'Ana', 'numero' => $datos['numero'], 'vence' => $datos['vence'], 'cvv' => $datos['cvv']]);
            $this->withToken($token)->postJson("/api/pagos/{$inicio->json('pago_id')}/confirmar");

            return [$reserva, $inicio->json('pago_id')];
        };

        [$reserva, $pago] = $comprar('aprobada');
        [, $rechazado] = $comprar('sin_fondos');

        $descripciones = $this->bitacora()->where('categoria', 'compra')->orderBy('id')->pluck('descripcion', 'id')->values()->all();
        $this->assertContains("Apartó 1 lugar para «Concierto» (reserva #{$reserva})", $descripciones);
        $this->assertContains("Inició el pago #{$pago} de la reserva #{$reserva}", $descripciones);
        $this->assertContains("Compró: pago #{$pago} de Q125.00 para «Concierto» aprobado", $descripciones);
        $fallido = $this->bitacora()->where('accion', 'compra.pago_confirmado')->where('resultado', 'fallido')->first();
        $this->assertStringStartsWith("Pago #{$rechazado} de Q125.00 para «Concierto» rechazado", $fallido->descripcion);
    }

    public function test_registra_que_operador_edito_un_evento_y_como_estaba_antes(): void
    {
        [$evento, $recinto] = $this->eventoBorrador();
        Sanctum::actingAs($operador = $this->usuario(RolUsuario::ORGANIZADOR, 'Obryan'));

        $this->putJson("/api/admin/eventos/{$evento}", [
            'nombre' => 'Gala de ópera 2027', 'recinto_id' => $recinto, 'fecha_evento' => now()->addDays(20)->format('Y-m-d H:i:s'),
            'imagen_url' => '/storage/eventos/a.webp', 'imagen_url_2' => '/storage/eventos/b.webp', 'imagen_url_3' => 'https://example.com/c.jpg',
        ])->assertOk();

        $fila = $this->bitacora()->where('accion', 'evento.editado')->first();
        $this->assertNotNull($fila);
        $this->assertSame('evento', $fila->categoria);
        $this->assertSame('Editó el evento «Gala de ópera»', $fila->descripcion);
        $this->assertSame([$operador->id, 'Obryan López', 'organizador'], [(int) $fila->usuario_id, $fila->usuario_nombre, $fila->usuario_rol]);
        $this->assertSame((string) $evento, $fila->entidad_id);
        $this->assertSame('Gala de ópera', json_decode($fila->datos_antes, true)['nombre']);
        $this->assertSame('Gala de ópera 2027', json_decode($fila->datos_despues, true)['nombre']);
    }

    public function test_describe_el_cambio_de_rol_de_un_usuario(): void
    {
        Sanctum::actingAs($this->usuario(RolUsuario::ADMIN));
        $luis = $this->usuario(RolUsuario::CLIENTE, 'Luis', 'luis@test.com');

        $this->patchJson("/api/admin/usuarios/{$luis->id}/rol", ['rol' => 'organizador'])->assertOk();

        $fila = $this->bitacora()->where('accion', 'usuario.rol_cambiado')->first();
        $this->assertSame('Cambió el rol de Luis López (luis@test.com) de Cliente a Operador', $fila->descripcion);
        $this->assertSame((string) $luis->id, $fila->entidad_id);
    }

    public function test_registra_cuando_un_operador_intenta_una_funcion_exclusiva_del_admin(): void
    {
        Sanctum::actingAs($this->usuario(RolUsuario::ORGANIZADOR));

        $this->getJson('/api/admin/usuarios')->assertForbidden();

        $fila = $this->bitacora()->first();
        $this->assertSame(['sesion.acceso_denegado', 'fallido', 'organizador'], [$fila->accion, $fila->resultado, $fila->usuario_rol]);
        $this->assertStringContainsString('/api/admin/usuarios', $fila->descripcion);
    }

    public function test_no_registra_acciones_del_panel_rechazadas_por_validacion(): void
    {
        Sanctum::actingAs($this->usuario(RolUsuario::ADMIN));

        $this->postJson('/api/admin/eventos', [])->assertStatus(422);

        $this->assertSame(0, $this->bitacora()->count());
    }

    public function test_si_la_bitacora_falla_la_operacion_del_usuario_sigue_normal(): void
    {
        $ana = $this->usuario(RolUsuario::CLIENTE);
        Schema::connection('bitacora')->drop('bitacora');

        $this->postJson('/api/auth/login', ['email' => $ana->email, 'password' => 'Secreto#123'])
            ->assertOk()->assertJsonStructure(['token']);
    }

    public function test_los_registros_no_se_pueden_modificar_ni_borrar(): void
    {
        $this->postJson('/api/auth/login', ['email' => 'nadie@test.com', 'password' => 'Lo-que-sea#1']);
        $this->assertSame(1, $this->bitacora()->count());

        foreach ([fn () => $this->bitacora()->update(['descripcion' => 'alterado']), fn () => $this->bitacora()->delete()] as $intento) {
            try {
                $intento();
                $this->fail('La bitácora permitió alterar un registro.');
            } catch (QueryException $e) {
                $this->assertStringContainsString('inmutable', $e->getMessage());
            }
        }
        $this->assertSame(1, $this->bitacora()->count());
    }

    public function test_solo_el_administrador_consulta_la_bitacora_con_filtros(): void
    {
        $fila = fn (array $datos) => $this->bitacora()->insert($datos + [
            'usuario_id' => null, 'usuario_nombre' => null, 'usuario_correo' => null, 'usuario_rol' => null,
            'resultado' => 'exito', 'descripcion' => 'x', 'entidad' => null,
        ]);
        // Guatemala es UTC-6: el 20/10 a las 23:30 de Guatemala se guarda como 21/10 05:30 UTC
        $fila(['ocurrido_en' => '2026-10-21 05:30:00', 'categoria' => 'evento', 'accion' => 'evento.creado',
            'usuario_id' => 7, 'usuario_nombre' => 'Obryan Mazariegos', 'usuario_correo' => 'obryan@test.com', 'usuario_rol' => 'organizador']);
        $fila(['ocurrido_en' => '2026-10-21 18:00:00', 'categoria' => 'sesion', 'accion' => 'sesion.inicio_fallido',
            'resultado' => 'fallido', 'usuario_correo' => 'intruso@test.com']);
        $fila(['ocurrido_en' => '2026-10-22 15:00:00', 'categoria' => 'compra', 'accion' => 'compra.pago_confirmado',
            'usuario_id' => 9, 'usuario_nombre' => 'Ana Cliente', 'usuario_correo' => 'ana@test.com', 'usuario_rol' => 'cliente']);

        Sanctum::actingAs($this->usuario(RolUsuario::ORGANIZADOR));
        $this->getJson('/api/admin/bitacora')->assertForbidden();

        Sanctum::actingAs($this->usuario(RolUsuario::ADMIN));
        $acciones = fn (string $consulta) => array_column($this->getJson('/api/admin/bitacora?'.$consulta)->assertOk()->json('data'), 'accion');

        // Sin filtros: lo más reciente primero (el acceso denegado del operador también quedó)
        $this->assertSame(['sesion.acceso_denegado', 'compra.pago_confirmado', 'sesion.inicio_fallido', 'evento.creado'], $acciones(''));
        $this->assertSame(['compra.pago_confirmado'], $acciones('categoria=compra'));
        $this->assertSame(['sesion.acceso_denegado', 'sesion.inicio_fallido'], $acciones('resultado=fallido'));
        $this->assertSame(['sesion.acceso_denegado', 'evento.creado'], $acciones('rol=organizador'));
        $this->assertSame(['sesion.inicio_fallido'], $acciones('rol=sin_sesion'));
        $this->assertSame(['evento.creado'], $acciones('usuario=obryan'));
        $this->assertSame(['evento.creado'], $acciones('desde=2026-10-20&hasta=2026-10-20'));   // días de Guatemala
        $this->assertSame(['sesion.inicio_fallido'], $acciones('desde=2026-10-21&hasta=2026-10-21'));

        $this->getJson('/api/admin/bitacora?resultado=raro')->assertStatus(422);
    }
}
