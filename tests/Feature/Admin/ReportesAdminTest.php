<?php

namespace Tests\Feature\Admin;

use App\Application\Abstractions\Admin\IGenerarReporteAdminUseCase;
use App\Application\DTOs\Admin\GenerarReporteAdminDTO;
use App\Domain\Enums\Auth\EstadoUsuario;
use App\Domain\Enums\Auth\RolUsuario;
use App\Models\Auth\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ReportesAdminTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->usuario(RolUsuario::ADMIN, 'Ana', 'admin@test.com');
    }

    private function usuario(RolUsuario $rol, string $nombre, string $email): Usuario
    {
        return Usuario::create([
            'nombre' => $nombre, 'apellido' => 'López', 'email' => $email, 'password' => bcrypt('Secreto#123'),
            'rol' => $rol, 'estado' => EstadoUsuario::ACTIVO, 'email_verified_at' => now(),
        ]);
    }

    // Gala: 4 lugares, 2 vendidos en una reserva pagada (Q250) y un pago rechazado; Jazz: borrador
    private function datos(): array
    {
        $cliente = $this->usuario(RolUsuario::CLIENTE, 'Luis', 'luis@test.com');
        $recinto = DB::table('recintos')->insertGetId(['nombre' => 'Teatro Soirée', 'direccion' => 'Z1', 'ciudad' => 'Guatemala', 'created_at' => now(), 'updated_at' => now()]);
        $localidad = DB::table('localidades')->insertGetId(['recinto_id' => $recinto, 'nombre' => 'Platea', 'tipo' => 'general', 'capacidad' => 4, 'created_at' => now(), 'updated_at' => now()]);
        $evento = fn (string $nombre, string $estado) => DB::table('eventos')->insertGetId([
            'recinto_id' => $recinto, 'nombre' => $nombre, 'estado' => $estado, 'fecha_evento' => '2026-12-05 02:00:00',
            'created_by' => $this->admin->id, 'created_at' => '2026-10-10 18:00:00', 'updated_at' => now(),
        ]);
        $gala = $evento('Gala de ópera', 'publicado');
        $evento('Noche de jazz', 'borrador');

        $reserva = DB::table('reservas')->insertGetId(['usuario_id' => $cliente->id, 'evento_id' => $gala, 'estado' => 'confirmada', 'total' => 250, 'expira_en' => now(), 'created_at' => now(), 'updated_at' => now()]);
        foreach (['vendido', 'vendido', 'disponible', 'disponible'] as $i => $estado) {
            DB::table('inventario')->insert(['evento_id' => $gala, 'localidad_id' => $localidad, 'estado' => $estado, 'reserva_id' => $i < 2 ? $reserva : null, 'created_at' => now(), 'updated_at' => now()]);
        }
        $pasarela = DB::table('pasarelas')->insertGetId(['nombre' => 'Pasarela simulada', 'codigo' => 'simulada', 'activa' => true, 'created_at' => now(), 'updated_at' => now()]);
        foreach ([['rechazado', 1], ['aprobado', 2]] as [$estado, $intento]) {
            DB::table('pagos')->insert(['reserva_id' => $reserva, 'pasarela_id' => $pasarela, 'monto' => 250, 'estado' => $estado, 'intento' => $intento, 'created_at' => now(), 'updated_at' => now()]);
        }

        return compact('gala', 'reserva');
    }

    private function informe(string $tipo, ?string $desde = null, ?string $hasta = null, int $limite = 100): array
    {
        return app(IGenerarReporteAdminUseCase::class)->execute(new GenerarReporteAdminDTO(
            tipo: $tipo, limite: $limite, zonaHoraria: 'America/Guatemala', desde: $desde, hasta: $hasta, generadoPor: 'Ana López',
        ));
    }

    public function test_el_informe_de_eventos_desglosa_cada_evento_con_su_ocupacion_e_ingresos(): void
    {
        $this->datos();
        $informe = $this->informe('eventos');

        $this->assertSame(['Eventos', 'Publicados', 'Boletos vendidos', 'Ingresos'], array_column($informe['indicadores'], 'etiqueta'));
        $this->assertSame([2, 1, 2, 250.0], array_column($informe['indicadores'], 'valor'));
        // Todos los estados, aunque no haya eventos en ellos
        $this->assertSame(['Borrador' => 1, 'Publicado' => 1, 'Agotado' => 0, 'Finalizado' => 0, 'Cancelado' => 0],
            array_column($informe['desglose']['filas'], 'cantidad', 'etiqueta'));

        $gala = $informe['filas'][0];
        $this->assertSame(['Gala de ópera', 'Teatro Soirée', 'Publicado', 4, 2, 0.5, 250.0, 'Ana López'],
            [$gala['nombre'], $gala['recinto'], $gala['estado'], $gala['lugares'], $gala['vendidos'], $gala['ocupacion'], $gala['ingresos'], $gala['creado_por']]);
        // UTC → hora de Guatemala (UTC-6)
        $this->assertSame('04/12/2026 20:00', $gala['fecha_evento']->format('d/m/Y H:i'));
        $this->assertSame('10/10/2026 12:00', $gala['created_at']->format('d/m/Y H:i'));
        $this->assertNull($informe['filas'][1]['ocupacion']);   // borrador: aún sin lugares
    }

    public function test_los_informes_de_reservas_pagos_y_usuarios_traen_el_detalle(): void
    {
        ['reserva' => $reserva] = $this->datos();

        $reservas = $this->informe('reservas');
        $this->assertSame([$reserva, 'Gala de ópera', 'Luis López', 'luis@test.com', 2, 250.0, 'Confirmada (pagada)'],
            array_values(array_slice($reservas['filas'][0], 0, 7)));
        $this->assertNotNull($reservas['filas'][0]['pagada_en']);

        $pagos = $this->informe('pagos');
        $this->assertSame(250.0, $pagos['indicadores'][3]['valor']);   // solo el aprobado
        $this->assertSame(['Rechazado', 'Aprobado'], array_column($pagos['filas'], 'estado'));
        $this->assertSame('Pasarela simulada', $pagos['filas'][0]['pasarela']);

        $usuarios = $this->informe('usuarios');
        $this->assertSame('Por rol', $usuarios['desglose']['titulo']);
        $luis = collect($usuarios['filas'])->firstWhere('email', 'luis@test.com');
        $this->assertSame(['Cliente', 'Sí', 'No', 1], [$luis['rol'], $luis['verificado'], $luis['google'], $luis['compras']]);
        $this->assertStringNotContainsString('password', json_encode($usuarios));
    }

    public function test_filtra_por_dias_de_guatemala_y_avisa_si_el_periodo_es_demasiado_grande(): void
    {
        $this->datos();   // eventos creados el 10/10 a las 12:00 de Guatemala

        $this->assertSame(2, $this->informe('eventos', '2026-10-10', '2026-10-10')['total']);
        $this->assertSame(0, $this->informe('eventos', '2026-10-11')['total']);
        $this->assertSame('Del 10/10/2026 al 10/10/2026', $this->informe('eventos', '2026-10-10', '2026-10-10')['periodo']);

        Sanctum::actingAs($this->admin);
        config(['reportes.limite_pdf' => 1]);
        $this->getJson('/api/admin/reportes/pdf?tipo=eventos')
            ->assertStatus(422)->assertJsonPath('error', 'reporte_demasiado_grande');
    }

    public function test_descarga_el_excel_con_el_detalle_en_la_primera_hoja(): void
    {
        $this->datos();
        Sanctum::actingAs($this->admin);

        $respuesta = $this->get('/api/admin/reportes/excel?tipo=eventos')->assertOk();
        $hoja = IOFactory::load($respuesta->baseResponse->getFile()->getPathname())->getActiveSheet();
        $textos = collect($hoja->toArray(null, false, false))->flatten()->filter()->map(fn ($v) => (string) $v);

        $this->assertTrue($textos->contains('Reporte de eventos'));
        $this->assertTrue($textos->contains('Gala de ópera'));
        $this->assertTrue($textos->contains('Noche de jazz'));
        $this->assertTrue($textos->contains('Detalle · 2 registros'));

        $this->get('/api/admin/reportes/pdf?tipo=reservas')->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_el_resumen_cuenta_operadores_y_solo_el_monto_de_pagos_aprobados(): void
    {
        $this->datos();
        $this->usuario(RolUsuario::ORGANIZADOR, 'Obryan', 'obryan@test.com');
        Sanctum::actingAs($this->admin);

        $resumen = $this->getJson('/api/admin/resumen')->assertOk();

        $resumen->assertJsonPath('totales.operadores', 1)
            ->assertJsonPath('totales.pagos', 2)
            ->assertJsonPath('totales.pagos_aprobados', 1);
        $this->assertEquals(250, $resumen->json('totales.monto_pagos_aprobados'));   // no 500: el rechazado no cuenta
        $this->assertSame(['activa', 'confirmada', 'vencida', 'cancelada'], array_column($resumen->json('reservas_por_estado'), 'estado'));
        $this->assertSame([0, 1, 0, 0], array_column($resumen->json('reservas_por_estado'), 'total'));
        $this->assertSame(['Borrador', 'Publicado', 'Agotado', 'Finalizado', 'Cancelado'], array_column($resumen->json('eventos_por_estado'), 'etiqueta'));
    }
}
