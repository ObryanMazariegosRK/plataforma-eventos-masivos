<?php

namespace Tests\Feature\Admin;

use App\Domain\Enums\Auth\EstadoUsuario;
use App\Domain\Enums\Auth\RolUsuario;
use App\Models\Auth\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CatalogoEventosAdminTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(RolUsuario $rol): Usuario
    {
        return Usuario::create([
            'nombre' => 'Ana', 'apellido' => 'López', 'email' => $rol->value.'@test.com',
            'password' => bcrypt('Secreto#123'), 'rol' => $rol, 'estado' => EstadoUsuario::ACTIVO,
            'email_verified_at' => now(),
        ]);
    }

    private function crearEventos(): array
    {
        $teatro = DB::table('recintos')->insertGetId(['nombre' => 'Teatro', 'direccion' => 'Z1', 'ciudad' => 'Guatemala', 'created_at' => now(), 'updated_at' => now()]);
        $auditorio = DB::table('recintos')->insertGetId(['nombre' => 'Auditorio', 'direccion' => 'Z10', 'ciudad' => 'Antigua', 'created_at' => now(), 'updated_at' => now()]);
        $evento = fn (string $nombre, int $recinto, string $estado, $fecha) => DB::table('eventos')->insertGetId([
            'recinto_id' => $recinto, 'nombre' => $nombre, 'estado' => $estado, 'fecha_evento' => $fecha,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $gala = $evento('Gala de ópera', $teatro, 'publicado', now()->addDays(10));
        $evento('Concierto de jazz', $auditorio, 'borrador', now()->addDays(3));
        $evento('Función pasada', $teatro, 'finalizado', now()->subDays(5));

        // ocupación de la gala: 4 lugares, 1 vendido
        foreach (['vendido', 'disponible', 'disponible', 'bloqueado'] as $estado) {
            DB::table('inventario')->insert(['evento_id' => $gala, 'localidad_id' => DB::table('localidades')->insertGetId([
                'recinto_id' => $teatro, 'nombre' => 'Platea '.$estado.uniqid(), 'tipo' => 'general', 'capacidad' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ]), 'estado' => $estado, 'created_at' => now(), 'updated_at' => now()]);
        }

        return compact('teatro', 'auditorio');
    }

    public function test_lista_todos_los_estados_con_ocupacion_y_proximos_primero(): void
    {
        $this->crearEventos();
        Sanctum::actingAs($this->usuario(RolUsuario::ORGANIZADOR));   // el agente operativo también lo usa

        $respuesta = $this->getJson('/api/admin/eventos/catalogo')->assertOk()
            ->assertJsonPath('meta.total', 3);

        $this->assertSame(['Concierto de jazz', 'Gala de ópera', 'Función pasada'], array_column($respuesta->json('data'), 'nombre'));
        $this->assertSame(['lugares' => 4, 'vendidos' => 1], array_intersect_key($respuesta->json('data.1'), ['lugares' => 0, 'vendidos' => 0]));
    }

    public function test_filtra_en_el_servidor_por_nombre_estado_recinto_y_fechas(): void
    {
        ['auditorio' => $auditorio] = $this->crearEventos();
        Sanctum::actingAs($this->usuario(RolUsuario::ADMIN));
        $nombres = fn (string $consulta) => array_column($this->getJson('/api/admin/eventos/catalogo?'.$consulta)->assertOk()->json('data'), 'nombre');

        $this->assertSame(['Gala de ópera'], $nombres('nombre=gala'));
        $this->assertSame(['Concierto de jazz'], $nombres('estado=borrador'));
        $this->assertSame(['Concierto de jazz'], $nombres("recinto_id={$auditorio}"));
        $this->assertSame(['Concierto de jazz', 'Gala de ópera'], $nombres('desde='.now()->toDateString()));
        $this->assertSame(['Gala de ópera'], $nombres('por_pagina=1&pagina=2'));
    }

    public function test_un_cliente_no_puede_usarlo_y_valida_los_filtros(): void
    {
        Sanctum::actingAs($this->usuario(RolUsuario::CLIENTE));
        $this->getJson('/api/admin/eventos/catalogo')->assertForbidden();

        Sanctum::actingAs($this->usuario(RolUsuario::ADMIN));
        $this->getJson('/api/admin/eventos/catalogo?estado=inventado')->assertUnprocessable();
        $this->getJson('/api/admin/eventos/catalogo?desde=2026-12-01&hasta=2026-11-01')->assertUnprocessable();
    }
}
