<?php

namespace Tests\Feature\Reembolsos;

use App\Domain\Enums\Auth\EstadoUsuario;
use App\Domain\Enums\Auth\RolUsuario;
use App\Models\Auth\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ResolverReembolsoApiTest extends TestCase
{
    use RefreshDatabase;

    private function crearUsuario(
        string $email,
        RolUsuario $rol
    ): Usuario {
        return Usuario::create([
            'nombre' => 'Usuario',
            'apellido' => 'Prueba',
            'email' => $email,
            'password' => bcrypt('Secreto#123'),
            'rol' => $rol,
            'estado' => EstadoUsuario::ACTIVO,
            'email_verified_at' => now(),
        ]);
    }

    private function crearEscenario(): array
    {
        $cliente = $this->crearUsuario(
            'cliente-api-resolver@test.com',
            RolUsuario::CLIENTE
        );

        $admin = $this->crearUsuario(
            'admin-api-resolver@test.com',
            RolUsuario::ADMIN
        );

        $recintoId = DB::table('recintos')->insertGetId([
            'nombre' => 'Recinto Prueba',
            'direccion' => 'Zona 1',
            'ciudad' => 'Guatemala',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $eventoId = DB::table('eventos')->insertGetId([
            'recinto_id' => $recintoId,
            'nombre' => 'Evento Prueba',
            'fecha_evento' => now()->addDays(10),
            'estado' => 'publicado',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $reservaId = DB::table('reservas')->insertGetId([
            'usuario_id' => $cliente->id,
            'evento_id' => $eventoId,
            'estado' => 'confirmada',
            'expira_en' => now()->addMinutes(5),
            'total' => 500,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $pagoId = DB::table('pagos')->insertGetId([
            'reserva_id' => $reservaId,
            'monto' => 500,
            'estado' => 'aprobado',
            'intento' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $reembolsoId = DB::table('reembolsos')->insertGetId([
            'pago_id' => $pagoId,
            'monto' => 400,
            'estado' => 'solicitado',
            'motivo' => 'No podre asistir',
            'created_by' => $cliente->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'cliente' => $cliente,
            'admin' => $admin,
            'pago_id' => $pagoId,
            'reembolso_id' => $reembolsoId,
        ];
    }

    public function test_rechaza_usuario_no_autenticado(): void
    {
        $this->patchJson(
            '/api/reembolsos/1/resolver',
            ['decision' => 'aprobar']
        )->assertUnauthorized();
    }

    public function test_cliente_no_puede_resolver_reembolsos(): void
    {
        $datos = $this->crearEscenario();

        Sanctum::actingAs($datos['cliente']);

        $this->patchJson(
            "/api/reembolsos/{$datos['reembolso_id']}/resolver",
            ['decision' => 'aprobar']
        )->assertForbidden();

        $this->assertDatabaseHas('reembolsos', [
            'id' => $datos['reembolso_id'],
            'estado' => 'solicitado',
        ]);
    }

    public function test_rechaza_decision_invalida(): void
    {
        $datos = $this->crearEscenario();

        Sanctum::actingAs($datos['admin']);

        $this->patchJson(
            "/api/reembolsos/{$datos['reembolso_id']}/resolver",
            ['decision' => 'procesar']
        )
            ->assertUnprocessable()
            ->assertJsonValidationErrors('decision');
    }

    public function test_administrador_aprueba_solicitud(): void
    {
        $datos = $this->crearEscenario();

        Sanctum::actingAs($datos['admin']);

        $this->patchJson(
            "/api/reembolsos/{$datos['reembolso_id']}/resolver",
            ['decision' => 'aprobar']
        )
            ->assertOk()
            ->assertJsonPath('reembolso.estado', 'aprobado')
            ->assertJsonPath('reembolso.monto', 400);

        $this->assertDatabaseHas('reembolsos', [
            'id' => $datos['reembolso_id'],
            'estado' => 'aprobado',
            'updated_by' => $datos['admin']->id,
        ]);

        // El pago sigue aprobado: aun no hay devolucion real.
        $this->assertDatabaseHas('pagos', [
            'id' => $datos['pago_id'],
            'estado' => 'aprobado',
        ]);
    }

    public function test_administrador_rechaza_solicitud(): void
    {
        $datos = $this->crearEscenario();

        Sanctum::actingAs($datos['admin']);

        $this->patchJson(
            "/api/reembolsos/{$datos['reembolso_id']}/resolver",
            ['decision' => 'rechazar']
        )
            ->assertOk()
            ->assertJsonPath('reembolso.estado', 'rechazado');

        $this->assertDatabaseHas('reembolsos', [
            'id' => $datos['reembolso_id'],
            'estado' => 'rechazado',
            'updated_by' => $datos['admin']->id,
        ]);
    }

    public function test_no_permite_resolver_dos_veces(): void
    {
        $datos = $this->crearEscenario();

        Sanctum::actingAs($datos['admin']);

        $url = "/api/reembolsos/{$datos['reembolso_id']}/resolver";

        $this->patchJson($url, [
            'decision' => 'aprobar',
        ])->assertOk();

        $this->patchJson($url, [
            'decision' => 'rechazar',
        ])->assertStatus(409);

        $this->assertDatabaseHas('reembolsos', [
            'id' => $datos['reembolso_id'],
            'estado' => 'aprobado',
        ]);
    }
}
