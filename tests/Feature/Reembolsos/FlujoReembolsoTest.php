<?php

namespace Tests\Feature\Reembolsos;

use App\Application\Abstractions\Boletos\IEmitirBoletosUseCase;
use App\Domain\Abstractions\Pasarelas\IPasarelaPagoResolver;
use App\Domain\Enums\Auth\EstadoUsuario;
use App\Domain\Enums\Auth\RolUsuario;
use App\Jobs\Notificaciones\EnviarNotificacionCorreoJob;
use App\Models\Auth\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\Fakes\Pagos\PasarelaPagoFalsa;
use Tests\TestCase;

// Reembolso de punta a punta: vista previa del cliente (100/80/50/nada), solicitud, aprobación
// (boletos anulados, lugares de nuevo a la venta, devolución en la pasarela), rechazo y panel.
class FlujoReembolsoTest extends TestCase
{
    use RefreshDatabase;

    private PasarelaPagoFalsa $pasarela;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake([EnviarNotificacionCorreoJob::class]);
        $this->pasarela = new PasarelaPagoFalsa;
        $this->app->instance(IPasarelaPagoResolver::class, $this->pasarela);
    }

    private function usuario(string $email, RolUsuario $rol = RolUsuario::CLIENTE): Usuario
    {
        return Usuario::create([
            'nombre' => 'Ana', 'apellido' => 'Pérez', 'email' => $email, 'password' => bcrypt('Secreto#123'),
            'rol' => $rol, 'estado' => EstadoUsuario::ACTIVO, 'email_verified_at' => now(),
        ]);
    }

    /** Compra pagada de 2 butacas numeradas (Q250 c/u) con sus boletos emitidos. */
    private function compra(Usuario $cliente, $fechaEvento): array
    {
        $pasarela = DB::table('pasarelas')->insertGetId(['nombre' => 'Simulada', 'codigo' => 'simulada', 'activa' => true, 'created_at' => now(), 'updated_at' => now()]);
        $recinto = DB::table('recintos')->insertGetId(['nombre' => 'Teatro Soirée', 'direccion' => 'Z1', 'ciudad' => 'Guatemala', 'created_at' => now(), 'updated_at' => now()]);
        $platea = DB::table('localidades')->insertGetId(['recinto_id' => $recinto, 'nombre' => 'Platea', 'tipo' => 'numerada', 'capacidad' => 2, 'created_at' => now(), 'updated_at' => now()]);
        $evento = DB::table('eventos')->insertGetId(['recinto_id' => $recinto, 'nombre' => 'Gala de ópera', 'estado' => 'publicado', 'fecha_evento' => $fechaEvento, 'created_at' => now(), 'updated_at' => now()]);
        $reserva = DB::table('reservas')->insertGetId(['usuario_id' => $cliente->id, 'evento_id' => $evento, 'estado' => 'confirmada', 'expira_en' => now(), 'total' => 500, 'created_at' => now(), 'updated_at' => now()]);
        $pago = DB::table('pagos')->insertGetId([
            'reserva_id' => $reserva, 'monto' => 500, 'estado' => 'aprobado', 'pasarela_id' => $pasarela,
            'orden_externa' => 'ORD-1', 'referencia_externa' => 'CAP-ORD-1', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $inventario = [];
        foreach ([1, 2] as $numero) {
            $asiento = DB::table('asientos')->insertGetId(['localidad_id' => $platea, 'fila' => 'A', 'numero' => $numero, 'created_at' => now(), 'updated_at' => now()]);
            $inventario[] = DB::table('inventario')->insertGetId(['evento_id' => $evento, 'localidad_id' => $platea, 'asiento_id' => $asiento, 'estado' => 'vendido', 'reserva_id' => $reserva, 'created_at' => now(), 'updated_at' => now()]);
        }
        app(IEmitirBoletosUseCase::class)->execute($pago);

        return compact('evento', 'reserva', 'pago', 'inventario', 'platea');
    }

    public function test_la_vista_previa_aplica_la_politica_segun_el_tiempo_que_falta(): void
    {
        $cliente = $this->usuario('cliente@test.com');
        $compra = $this->compra($cliente, now()->addDays(10));
        Sanctum::actingAs($cliente);

        $casos = [
            [now()->addDays(10), 100, 500.0],
            [now()->addDays(4), 80, 400.0],
            [now()->addHours(30), 50, 250.0],
        ];
        foreach ($casos as [$fecha, $porcentaje, $monto]) {
            DB::table('eventos')->where('id', $compra['evento'])->update(['fecha_evento' => $fecha]);
            $vista = $this->getJson("/api/reservas/{$compra['reserva']}/reembolso")->assertOk()->json('data');
            $this->assertTrue($vista['puede_solicitar']);
            $this->assertEquals($porcentaje, $vista['calculo']['porcentaje_reembolso']);
            $this->assertEquals($monto, $vista['calculo']['monto_reembolso']);
        }

        // Menos de 24 horas: ya no se devuelve nada
        DB::table('eventos')->where('id', $compra['evento'])->update(['fecha_evento' => now()->addHours(10)]);
        $vista = $this->getJson("/api/reservas/{$compra['reserva']}/reembolso")->assertOk()->json('data');
        $this->assertFalse($vista['puede_solicitar']);
        $this->assertStringContainsString('24 horas', $vista['motivo_no']);
        $this->assertSame([168, 72, 24], array_column($vista['politica'], 'horas_minimas'));
        $this->assertEquals([100, 80, 50], array_column($vista['politica'], 'porcentaje_reembolso'));

        $this->postJson("/api/reservas/{$compra['reserva']}/reembolso")->assertStatus(422);

        // La compra de otra persona no existe para mí
        Sanctum::actingAs($this->usuario('otra@test.com'));
        $this->getJson("/api/reservas/{$compra['reserva']}/reembolso")->assertNotFound();
        $this->postJson("/api/reservas/{$compra['reserva']}/reembolso")->assertNotFound();
    }

    public function test_aprobar_anula_los_boletos_libera_los_lugares_y_devuelve_el_dinero(): void
    {
        $cliente = $this->usuario('cliente@test.com');
        $admin = $this->usuario('admin@test.com', RolUsuario::ADMIN);
        $compra = $this->compra($cliente, now()->addDays(4));

        Sanctum::actingAs($cliente);
        $vista = $this->postJson("/api/reservas/{$compra['reserva']}/reembolso", ['motivo' => 'No podré asistir'])
            ->assertCreated()->json('data');
        $this->assertSame('solicitado', $vista['reembolso']['estado']);
        $this->assertEquals(400, $vista['reembolso']['monto']);
        $this->postJson("/api/reservas/{$compra['reserva']}/reembolso")->assertStatus(422);   // una sola solicitud

        // Mientras se revisa, los boletos siguen válidos
        $this->assertSame(2, DB::table('boletos')->where('estado', 'emitido')->count());

        Sanctum::actingAs($admin);
        $this->patchJson("/api/reembolsos/{$vista['reembolso']['id']}/resolver", ['decision' => 'aprobar'])
            ->assertOk()
            ->assertJsonPath('reembolso.estado', 'procesado')
            ->assertJsonPath('devolucion_pendiente', false);

        $this->assertSame(['cobro' => 'CAP-ORD-1', 'monto' => 400.0], $this->pasarela->reembolsos["reembolso-{$vista['reembolso']['id']}"]);
        $this->assertDatabaseHas('reembolsos', ['id' => $vista['reembolso']['id'], 'estado' => 'procesado', 'referencia_externa' => "REF-reembolso-{$vista['reembolso']['id']}"]);
        $this->assertDatabaseHas('transacciones', ['tipo' => 'reembolso', 'monto' => 400, 'pago_id' => $compra['pago'], 'usuario_id' => $cliente->id]);
        $this->assertDatabaseHas('historial_operaciones', ['tipo' => 'reembolso_confirmado', 'reserva_id' => $compra['reserva']]);
        $this->assertSame(2, DB::table('boletos')->where('estado', 'anulado')->count());
        $this->assertSame(2, DB::table('inventario')->where('estado', 'disponible')->whereNull('reserva_id')->count());
        Queue::assertPushed(EnviarNotificacionCorreoJob::class, 1);
        $this->assertDatabaseHas('notificaciones', ['usuario_id' => $cliente->id, 'asunto' => 'Reembolso realizado']);

        // Mis compras sigue mostrando la compra, sus lugares (boletos anulados) y el reembolso
        Sanctum::actingAs($cliente);
        $compraListada = $this->getJson('/api/mis-compras')->assertOk()->json('proximas.0');
        $this->assertSame(['estado' => 'procesado', 'monto' => 400], array_intersect_key($compraListada['reembolso'], ['estado' => 1, 'monto' => 1]));
        $this->assertCount(2, $compraListada['lugares']);
        $this->assertSame('anulado', $compraListada['lugares'][0]['boleto']['estado']);

        // El lugar se vuelve a vender: la nueva compra recibe su propio boleto
        $otro = $this->usuario('otro@test.com');
        $reventa = DB::table('reservas')->insertGetId(['usuario_id' => $otro->id, 'evento_id' => $compra['evento'], 'estado' => 'confirmada', 'expira_en' => now(), 'total' => 250, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('inventario')->where('id', $compra['inventario'][0])->update(['estado' => 'vendido', 'reserva_id' => $reventa]);
        $pagoReventa = DB::table('pagos')->insertGetId(['reserva_id' => $reventa, 'monto' => 250, 'estado' => 'aprobado', 'created_at' => now(), 'updated_at' => now()]);
        $this->assertCount(1, app(IEmitirBoletosUseCase::class)->execute($pagoReventa));
        $this->assertSame(2, DB::table('boletos')->where('inventario_id', $compra['inventario'][0])->count());
        $this->assertCount(2, $this->getJson('/api/mis-compras')->json('proximas.0.lugares'));
    }

    public function test_si_la_pasarela_falla_queda_aprobado_y_se_puede_reintentar(): void
    {
        $cliente = $this->usuario('cliente@test.com');
        $admin = $this->usuario('admin@test.com', RolUsuario::ADMIN);
        $compra = $this->compra($cliente, now()->addDays(10));
        Sanctum::actingAs($cliente);
        $id = $this->postJson("/api/reservas/{$compra['reserva']}/reembolso")->assertCreated()->json('data.reembolso.id');

        $this->pasarela->aprobar = false;
        Sanctum::actingAs($admin);
        $this->patchJson("/api/reembolsos/{$id}/resolver", ['decision' => 'aprobar'])
            ->assertOk()
            ->assertJsonPath('reembolso.estado', 'aprobado')
            ->assertJsonPath('devolucion_pendiente', true);
        $this->assertSame(2, DB::table('boletos')->where('estado', 'anulado')->count());
        $this->postJson("/api/admin/reembolsos/{$id}/procesar")->assertStatus(409)->assertJsonPath('error', 'reembolso_no_procesado');

        $this->pasarela->aprobar = true;
        $this->postJson("/api/admin/reembolsos/{$id}/procesar")->assertOk()->assertJsonPath('data.estado', 'procesado');
        // Reintentar sobre uno ya devuelto no devuelve otra vez
        $this->postJson("/api/admin/reembolsos/{$id}/procesar")->assertOk();
        $this->assertSame(1, DB::table('transacciones')->where('tipo', 'reembolso')->count());
    }

    public function test_rechazar_conserva_los_boletos_y_avisa_al_cliente(): void
    {
        $cliente = $this->usuario('cliente@test.com');
        $compra = $this->compra($cliente, now()->addDays(10));
        Sanctum::actingAs($cliente);
        $id = $this->postJson("/api/reservas/{$compra['reserva']}/reembolso")->json('data.reembolso.id');

        Sanctum::actingAs($this->usuario('admin@test.com', RolUsuario::ADMIN));
        $this->patchJson("/api/reembolsos/{$id}/resolver", ['decision' => 'rechazar'])->assertOk()->assertJsonPath('reembolso.estado', 'rechazado');

        $this->assertSame(2, DB::table('boletos')->where('estado', 'emitido')->count());
        $this->assertSame(2, DB::table('inventario')->where('reserva_id', $compra['reserva'])->count());
        $this->assertSame([], $this->pasarela->reembolsos);
        $this->assertDatabaseHas('notificaciones', ['usuario_id' => $cliente->id, 'asunto' => 'Solicitud de reembolso rechazada']);
    }

    public function test_el_panel_lista_las_solicitudes_por_estado_y_solo_para_el_administrador(): void
    {
        $cliente = $this->usuario('cliente@test.com');
        $compra = $this->compra($cliente, now()->addDays(10));
        Sanctum::actingAs($cliente);
        $this->postJson("/api/reservas/{$compra['reserva']}/reembolso", ['motivo' => 'Viaje'])->assertCreated();
        $this->getJson('/api/admin/reembolsos')->assertForbidden();

        Sanctum::actingAs($this->usuario('operador@test.com', RolUsuario::ORGANIZADOR));
        $this->getJson('/api/admin/reembolsos')->assertForbidden();

        Sanctum::actingAs($this->usuario('admin@test.com', RolUsuario::ADMIN));
        $datos = $this->getJson('/api/admin/reembolsos')->assertOk()->json('data');
        $this->assertSame(1, $datos['meta']['total']);
        $this->assertSame(['solicitado', 'aprobado', 'procesado', 'rechazado'], array_column($datos['por_estado'], 'estado'));
        $this->assertSame(1, $datos['por_estado'][0]['total']);
        $fila = $datos['reembolsos'][0];
        $this->assertSame(['Viaje', 'Gala de ópera', 'cliente@test.com', 2, 500.0], [
            $fila['motivo'], $fila['evento']['nombre'], $fila['cliente']['email'], $fila['lugares'], (float) $fila['monto_pagado'],
        ]);

        $this->assertSame(0, $this->getJson('/api/admin/reembolsos?estado=procesado')->json('data.meta.total'));
        $this->getJson('/api/admin/reembolsos?estado=otro')->assertStatus(422);
    }

    public function test_cancelar_el_evento_devuelve_el_100_por_ciento_automaticamente(): void
    {
        $cliente = $this->usuario('cliente@test.com');
        $compra = $this->compra($cliente, now()->addDays(4));
        // El cliente ya había pedido su 80 %
        Sanctum::actingAs($cliente);
        $this->postJson("/api/reservas/{$compra['reserva']}/reembolso")->assertCreated();

        Sanctum::actingAs($this->usuario('admin@test.com', RolUsuario::ADMIN));
        $this->postJson("/api/eventos/{$compra['evento']}/cancelar")
            ->assertOk()
            ->assertJsonPath('resultado.reembolsos_devueltos', 2);

        // 400 que pidió + 100 que faltaban, ambos devueltos; los lugares no vuelven a la venta
        $this->assertSame(500.0, (float) DB::table('reembolsos')->where('estado', 'procesado')->sum('monto'));
        $this->assertSame(2, DB::table('inventario')->where('reserva_id', $compra['reserva'])->count());

        Sanctum::actingAs($cliente);
        $listada = $this->getJson('/api/mis-compras')->json('proximas.0');
        $this->assertTrue($listada['evento_cancelado']);
        $this->assertSame('procesado', $listada['reembolso']['estado']);
        $this->assertEquals(500, $listada['reembolso']['monto']);
        $this->assertStringContainsString('cancelado', $this->getJson("/api/reservas/{$compra['reserva']}/reembolso")->json('data.motivo_no'));
    }
}
