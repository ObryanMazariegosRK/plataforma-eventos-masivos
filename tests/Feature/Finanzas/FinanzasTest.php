<?php

namespace Tests\Feature\Finanzas;

use App\Domain\Enums\Auth\EstadoUsuario;
use App\Domain\Enums\Auth\RolUsuario;
use App\Models\Auth\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinanzasTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function usuario(RolUsuario $rol): Usuario
    {
        return Usuario::create([
            'nombre' => 'Ana', 'apellido' => 'López', 'email' => $rol->value.'@test.com',
            'password' => bcrypt('Secreto#123'), 'rol' => $rol, 'estado' => EstadoUsuario::ACTIVO,
            'email_verified_at' => now(),
        ]);
    }

    private function recinto(): array
    {
        $recinto = DB::table('recintos')->insertGetId(['nombre' => 'Teatro', 'direccion' => 'Z1', 'ciudad' => 'Guatemala', 'estado' => 'activo', 'created_at' => now(), 'updated_at' => now()]);
        $localidad = DB::table('localidades')->insertGetId(['recinto_id' => $recinto, 'nombre' => 'Platea', 'tipo' => 'general', 'capacidad' => 20, 'created_at' => now(), 'updated_at' => now()]);

        return [$recinto, $localidad];
    }

    // Evento publicado con $lugares a Q$precio, $vendidos ya pagados en una reserva y sus costos
    private function eventoConVentas(string $nombre, string $fecha, int $lugares, int $vendidos, float $precio, array $costos): int
    {
        [$recinto, $localidad] = $this->recinto();
        $cliente = Usuario::create(['nombre' => 'Luis', 'apellido' => 'Pérez', 'email' => uniqid().'@test.com', 'password' => 'x', 'rol' => RolUsuario::CLIENTE, 'estado' => EstadoUsuario::ACTIVO]);
        $evento = DB::table('eventos')->insertGetId(['recinto_id' => $recinto, 'nombre' => $nombre, 'estado' => 'publicado', 'fecha_evento' => $fecha, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('precios')->insert(['evento_id' => $evento, 'localidad_id' => $localidad, 'precio' => $precio, 'created_at' => now(), 'updated_at' => now()]);

        $reserva = DB::table('reservas')->insertGetId(['usuario_id' => $cliente->id, 'evento_id' => $evento, 'estado' => 'confirmada', 'total' => $vendidos * $precio, 'expira_en' => now(), 'created_at' => now(), 'updated_at' => now()]);
        for ($i = 0; $i < $lugares; $i++) {
            DB::table('inventario')->insert(['evento_id' => $evento, 'localidad_id' => $localidad, 'estado' => $i < $vendidos ? 'vendido' : 'disponible', 'reserva_id' => $i < $vendidos ? $reserva : null, 'created_at' => now(), 'updated_at' => now()]);
        }
        if ($vendidos) {
            DB::table('pagos')->insert(['reserva_id' => $reserva, 'monto' => $vendidos * $precio, 'estado' => 'aprobado', 'intento' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }
        foreach ($costos as [$concepto, $monto]) {
            DB::table('costos_evento')->insert(['evento_id' => $evento, 'categoria' => 'otros', 'concepto' => $concepto, 'monto' => $monto, 'created_at' => now(), 'updated_at' => now()]);
        }

        return $evento;
    }

    public function test_el_operador_registra_los_costos_al_crear_el_evento_pero_no_ve_las_ganancias(): void
    {
        [$recinto, $localidad] = $this->recinto();
        Sanctum::actingAs($this->usuario(RolUsuario::ORGANIZADOR));

        $id = $this->postJson('/api/admin/eventos', [
            'nombre' => 'Gala de ópera', 'recinto_id' => $recinto, 'fecha_evento' => now()->addMonth()->format('Y-m-d H:i:s'),
            'precios' => [['localidad_id' => $localidad, 'precio' => 150]],
            'costos' => [
                ['categoria' => 'recinto', 'concepto' => 'Alquiler del teatro', 'monto' => 8000],
                ['categoria' => 'artistas', 'concepto' => 'Honorarios del cuarteto', 'monto' => 12000.50],
            ],
        ])->assertCreated()->json('data.id');

        $this->getJson("/api/admin/eventos/{$id}/costos")->assertOk()
            ->assertJsonPath('data.total', 20000.5)
            ->assertJsonPath('data.costos.0.categoria_etiqueta', 'Recinto')
            ->assertJsonPath('data.costos.1.concepto', 'Honorarios del cuarteto');

        $this->getJson('/api/admin/finanzas')->assertForbidden();
    }

    public function test_un_costo_invalido_no_deja_crear_el_evento(): void
    {
        [$recinto, $localidad] = $this->recinto();
        Sanctum::actingAs($this->usuario(RolUsuario::ORGANIZADOR));

        $this->postJson('/api/admin/eventos', [
            'nombre' => 'Gala de ópera', 'recinto_id' => $recinto, 'fecha_evento' => now()->addMonth()->format('Y-m-d H:i:s'),
            'precios' => [['localidad_id' => $localidad, 'precio' => 150]],
            'costos' => [['categoria' => 'recinto', 'concepto' => 'Alquiler', 'monto' => 0]],
        ])->assertStatus(422)->assertJsonValidationErrors('costos.0.monto');

        $this->assertSame(0, DB::table('eventos')->count());
    }

    public function test_guardar_costos_reemplaza_la_lista_y_conserva_el_historial(): void
    {
        $evento = $this->eventoConVentas('Jazz', now()->addMonth(), 10, 0, 100, [['Sonido', 500]]);
        Sanctum::actingAs($this->usuario(RolUsuario::ORGANIZADOR));

        $this->putJson("/api/admin/eventos/{$evento}/costos", ['costos' => [
            ['categoria' => 'publicidad', 'concepto' => 'Redes sociales', 'monto' => 300],
        ]])->assertOk()->assertJsonPath('data.total', 300);

        $this->assertSame(1, DB::table('costos_evento')->whereNull('deleted_at')->count());
        $this->assertSame(1, DB::table('costos_evento')->whereNotNull('deleted_at')->count());   // el anterior no se pierde

        $this->putJson("/api/admin/eventos/{$evento}/costos", ['costos' => []])->assertOk()->assertJsonPath('data.total', 0);
        $this->putJson('/api/admin/eventos/999/costos', ['costos' => []])->assertNotFound()->assertJsonPath('error', 'evento_no_encontrado');
    }

    public function test_calcula_la_ganancia_de_un_evento_realizado_y_el_punto_de_equilibrio_de_uno_en_venta(): void
    {
        Carbon::setTestNow('2026-10-22 18:00:00');   // 12:00 en Guatemala

        // Ya ocurrió: 10 boletos × Q100 = Q1,000; reembolso Q100; comisión 3.5 % = Q35; costos Q500
        $pasado = $this->eventoConVentas('Concierto', '2026-10-05 02:00:00', 20, 10, 100, [['Alquiler', 300], ['Artista', 200]]);
        $pago = DB::table('pagos')->join('reservas', 'reservas.id', '=', 'pagos.reserva_id')->where('reservas.evento_id', $pasado)->value('pagos.id');
        DB::table('reembolsos')->insert(['pago_id' => $pago, 'monto' => 100, 'estado' => 'procesado', 'created_at' => now(), 'updated_at' => now()]);

        // En venta: costos Q1,000 a Q100 (Q96.50 sin comisión) → 11 boletos para cubrirlos; van 3
        $proximo = $this->eventoConVentas('Gala', '2026-12-01 02:00:00', 20, 3, 100, [['Producción', 1000]]);
        $sinCostos = $this->eventoConVentas('Jazz', '2026-12-10 02:00:00', 10, 1, 100, []);

        Sanctum::actingAs($this->usuario(RolUsuario::ADMIN));
        $datos = $this->getJson('/api/admin/finanzas')->assertOk()->json('data');
        $eventos = collect([...$datos['historial'], ...$datos['proximos']])->keyBy('id');

        $this->assertEquals(
            ['ingresos_brutos' => 1000, 'reembolsos' => 100, 'comision' => 35, 'ingresos_netos' => 865, 'costos' => 500, 'ganancia' => 365, 'margen' => 0.365, 'resultado' => 'ganancia'],
            array_intersect_key($eventos[$pasado], array_flip(['ingresos_brutos', 'reembolsos', 'comision', 'ingresos_netos', 'costos', 'ganancia', 'margen', 'resultado'])),
        );
        $this->assertSame([11, true, 'en_venta'], [$eventos[$proximo]['equilibrio_boletos'], $eventos[$proximo]['equilibrio_alcanzable'], $eventos[$proximo]['resultado']]);
        $this->assertEqualsWithDelta(3 / 11, $eventos[$proximo]['avance_equilibrio'], 0.001);
        // Sin costos no se puede hablar de equilibrio ni de ganancia real
        $this->assertSame(['sin_costos', false], [$eventos[$sinCostos]['resultado'], $eventos[$sinCostos]['tiene_costos']]);

        // Historial = eventos que ya ocurrieron dentro del período; los próximos van aparte
        $this->assertSame([$pasado], array_column($datos['historial'], 'id'));
        $this->assertSame([$proximo, $sinCostos], array_column($datos['proximos'], 'id'));
        $this->assertEquals([365, 0.365, 1], [$datos['totales']['ganancia'], $datos['totales']['margen'], $datos['totales']['eventos_cerrados']]);

        // Fuera del período elegido, el evento realizado ya no cuenta para la ganancia
        $datos = $this->getJson('/api/admin/finanzas?desde=2026-10-10&hasta=2026-10-22')->assertOk()->json('data');
        $this->assertSame([], $datos['historial']);
        $this->assertEquals(0, $datos['totales']['ganancia']);
    }

    public function test_un_periodo_largo_agrupa_las_ventas_por_semana_o_por_mes(): void
    {
        Carbon::setTestNow('2026-10-22 18:00:00');
        Sanctum::actingAs($this->usuario(RolUsuario::ADMIN));

        $semanas = $this->getJson('/api/admin/finanzas?desde=2026-07-01&hasta=2026-10-22')->assertOk()->json('data');
        $this->assertSame('semana', $semanas['periodo']['agrupacion']);
        $this->assertSame(['2026-07-01', '2026-07-05'], [$semanas['ventas'][0]['inicio'], $semanas['ventas'][0]['fin']]);   // del miércoles al domingo

        $meses = $this->getJson('/api/admin/finanzas?desde=2026-01-01&hasta=2026-10-22')->assertOk()->json('data');
        $this->assertSame('mes', $meses['periodo']['agrupacion']);
        $this->assertCount(10, $meses['ventas']);

        $this->getJson('/api/admin/finanzas?desde=2025-01-01&hasta=2026-10-22')->assertStatus(422);
        $this->getJson('/api/admin/finanzas?desde=2026-10-22&hasta=2026-10-01')->assertStatus(422);
    }

    public function test_agrupa_las_ventas_por_dia_de_guatemala_y_compara_con_el_periodo_anterior(): void
    {
        Carbon::setTestNow('2026-10-22 18:00:00');
        $evento = $this->eventoConVentas('Concierto', '2026-12-05 02:00:00', 20, 0, 100, []);
        $reserva = DB::table('reservas')->insertGetId(['usuario_id' => Usuario::first()->id, 'evento_id' => $evento, 'estado' => 'confirmada', 'total' => 0, 'expira_en' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $pago = fn (float $monto, string $cuando) => DB::table('pagos')->insert(['reserva_id' => $reserva, 'monto' => $monto, 'estado' => 'aprobado', 'intento' => 1, 'created_at' => $cuando, 'updated_at' => $cuando]);

        $pago(200, '2026-10-22 03:00:00');   // 21/10 a las 21:00 en Guatemala
        $pago(300, '2026-10-22 15:00:00');   // 22/10
        $pago(100, '2026-09-10 15:00:00');   // período anterior
        DB::table('pagos')->insert(['reserva_id' => $reserva, 'monto' => 999, 'estado' => 'rechazado', 'intento' => 2, 'created_at' => now(), 'updated_at' => now()]);

        Sanctum::actingAs($this->usuario(RolUsuario::ADMIN));
        $datos = $this->getJson('/api/admin/finanzas')->assertOk()->json('data');
        $dias = collect($datos['ventas'])->keyBy('inicio');

        $this->assertSame('dia', $datos['periodo']['agrupacion']);
        $this->assertCount(30, $datos['ventas']);
        $this->assertSame(['2026-09-23', '2026-10-22'], [$datos['periodo']['desde'], $datos['periodo']['hasta']]);
        $this->assertEquals(200, $dias['2026-10-21']['monto']);
        $this->assertEquals(300, $dias['2026-10-22']['monto']);
        $this->assertEquals([500, 100, 4.0], [$datos['totales']['ventas'], $datos['totales']['ventas_anteriores'], $datos['totales']['variacion']]);
    }
}
