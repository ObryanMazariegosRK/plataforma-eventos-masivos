<?php

namespace Tests\Feature\Reembolsos;

use App\Domain\Enums\Auth\EstadoUsuario;
use App\Domain\Enums\Auth\RolUsuario;
use App\Models\Auth\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CancelarEventoApiTest extends TestCase
{
    use RefreshDatabase;

    private function crearUsuario(
        string $email,
        RolUsuario $rol = RolUsuario::CLIENTE
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

    private function crearEvento(
        string $estado = 'publicado'
    ): int {
        $recintoId = DB::table('recintos')->insertGetId([
            'nombre' => 'Recinto API Cancelaciones',
            'direccion' => 'Zona 1',
            'ciudad' => 'Guatemala',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('eventos')->insertGetId([
            'recinto_id' => $recintoId,
            'nombre' => 'Concierto de Prueba',
            'fecha_evento' => now()->addDays(10),
            'estado' => $estado,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function crearPago(
        int $eventoId,
        int $usuarioId,
        float $monto
    ): int {
        $reservaId = DB::table('reservas')->insertGetId([
            'usuario_id' => $usuarioId,
            'evento_id' => $eventoId,
            'estado' => 'confirmada',
            'expira_en' => now()->addMinutes(5),
            'total' => $monto,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('pagos')->insertGetId([
            'reserva_id' => $reservaId,
            'monto' => $monto,
            'estado' => 'aprobado',
            'intento' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_rechaza_usuario_no_autenticado(): void
    {
        $this->postJson(
            '/api/eventos/1/cancelar'
        )->assertUnauthorized();
    }

    public function test_cliente_no_puede_cancelar_eventos(): void
    {
        $cliente = $this->crearUsuario(
            'cliente-no-admin@test.com'
        );

        $eventoId = $this->crearEvento();

        Sanctum::actingAs($cliente);

        $this->postJson(
            "/api/eventos/{$eventoId}/cancelar"
        )->assertForbidden();

        $this->assertDatabaseHas('eventos', [
            'id' => $eventoId,
            'estado' => 'publicado',
        ]);

        $this->assertDatabaseCount(
            'historial_operaciones',
            0
        );
    }

    public function test_administrador_cancela_evento_correctamente(): void
    {
        $admin = $this->crearUsuario(
            'admin-api-cancelar@test.com',
            RolUsuario::ADMIN
        );

        $cliente1 = $this->crearUsuario(
            'cliente1-api-cancelar@test.com'
        );

        $cliente2 = $this->crearUsuario(
            'cliente2-api-cancelar@test.com'
        );

        $eventoId = $this->crearEvento();

        $pago1 = $this->crearPago(
            $eventoId,
            $cliente1->id,
            500
        );

        $pago2 = $this->crearPago(
            $eventoId,
            $cliente2->id,
            250
        );

        Sanctum::actingAs($admin);

        $respuesta = $this->postJson(
            "/api/eventos/{$eventoId}/cancelar"
        );

        $respuesta
            ->assertOk()
            ->assertJsonPath(
                'resultado.estado',
                'cancelado'
            )
            ->assertJsonPath(
                'resultado.pagos_aprobados',
                2
            )
            ->assertJsonPath(
                'resultado.reembolsos_creados',
                2
            );

        $this->assertEquals(
            750,
            $respuesta->json('resultado.monto_preparado')
        );

        // El evento queda cancelado.
        $this->assertDatabaseHas('eventos', [
            'id' => $eventoId,
            'estado' => 'cancelado',
            'updated_by' => $admin->id,
        ]);

        // Se aprueba el reembolso completo de cada pago.
        // Estos pagos de prueba no tienen cobro en una pasarela,
        // así que la devolución queda pendiente (aprobado).
        $this->assertDatabaseHas('reembolsos', [
            'pago_id' => $pago1,
            'monto' => 500,
            'estado' => 'aprobado',
        ]);

        $this->assertDatabaseHas('reembolsos', [
            'pago_id' => $pago2,
            'monto' => 250,
            'estado' => 'aprobado',
        ]);

        $respuesta->assertJsonPath('resultado.reembolsos_devueltos', 0);

        $this->assertDatabaseCount('reembolsos', 2);

        // Los pagos permanecen aprobados.
        // Todavia no se ha devuelto dinero.
        $this->assertDatabaseHas('pagos', [
            'id' => $pago1,
            'estado' => 'aprobado',
        ]);

        // Debe quedar registrado en el historial.
        $this->assertDatabaseHas('historial_operaciones', [
            'tipo' => 'evento_cancelado',
            'evento_id' => $eventoId,
            'registrado_por' => $admin->id,
            'clave_idempotencia' =>
                'evento_cancelado:' . $eventoId,
        ]);

        $this->assertDatabaseCount(
            'historial_operaciones',
            1
        );
    }

    public function test_no_permite_cancelar_dos_veces(): void
    {
        $admin = $this->crearUsuario(
            'admin-api-duplicado@test.com',
            RolUsuario::ADMIN
        );

        $eventoId = $this->crearEvento();

        Sanctum::actingAs($admin);

        $url = "/api/eventos/{$eventoId}/cancelar";

        $this->postJson($url)->assertOk();

        $this->postJson($url)->assertStatus(409);

        $this->assertDatabaseCount(
            'historial_operaciones',
            1
        );
    }

    public function test_no_permite_cancelar_evento_en_borrador(): void
    {
        $admin = $this->crearUsuario(
            'admin-api-borrador@test.com',
            RolUsuario::ADMIN
        );

        $eventoId = $this->crearEvento('borrador');

        Sanctum::actingAs($admin);

        $this->postJson(
            "/api/eventos/{$eventoId}/cancelar"
        )->assertStatus(409);

        $this->assertDatabaseHas('eventos', [
            'id' => $eventoId,
            'estado' => 'borrador',
        ]);

        $this->assertDatabaseCount(
            'historial_operaciones',
            0
        );
    }

    public function test_error_financiero_revierte_cancelacion(): void
    {
        $admin = $this->crearUsuario(
            'admin-api-error@test.com',
            RolUsuario::ADMIN
        );

        $cliente = $this->crearUsuario(
            'cliente-api-error@test.com'
        );

        $eventoId = $this->crearEvento();

        $pagoId = $this->crearPago(
            $eventoId,
            $cliente->id,
            500
        );

        // Reembolso inconsistente:
        // Q600 sobre un pago de Q500.
        DB::table('reembolsos')->insert([
            'pago_id' => $pagoId,
            'monto' => 600,
            'estado' => 'solicitado',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $this->postJson(
            "/api/eventos/{$eventoId}/cancelar"
        )->assertStatus(409);

        // No se cancela el evento.
        $this->assertDatabaseHas('eventos', [
            'id' => $eventoId,
            'estado' => 'publicado',
        ]);

        // No se crean solicitudes adicionales.
        $this->assertDatabaseCount('reembolsos', 1);

        // No se registra una cancelacion fallida.
        $this->assertDatabaseCount(
            'historial_operaciones',
            0
        );
    }
}
