<?php

namespace Tests\Feature\Reembolsos;

use App\Domain\Enums\Auth\EstadoUsuario;
use App\Domain\Enums\Auth\RolUsuario;
use App\Models\Auth\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SolicitarReembolsoApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_rechaza_usuario_no_autenticado(): void
    {
        $this->postJson('/api/reembolsos', [
            'pago_id' => 1,
        ])->assertUnauthorized();
    }

    public function test_administrador_no_puede_solicitar_como_cliente(): void
    {
        $admin = $this->crearUsuario(
            'admin-reembolso@test.com',
            RolUsuario::ADMIN
        );

        Sanctum::actingAs($admin);

        $this->postJson('/api/reembolsos', [
            'pago_id' => 1,
        ])->assertForbidden();
    }

    public function test_rechaza_datos_invalidos(): void
    {
        $cliente = $this->crearUsuario(
            'cliente-reembolso@test.com',
            RolUsuario::CLIENTE
        );

        Sanctum::actingAs($cliente);

        $this->postJson('/api/reembolsos', [
            'pago_id' => -1,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('pago_id');

        $this->assertDatabaseCount('reembolsos', 0);
    }

    public function test_cliente_solicita_reembolso_correctamente(): void
    {
        $datos = $this->crearEscenario(120);

        Sanctum::actingAs($datos['cliente']);

        $this->postJson('/api/reembolsos', [
            'pago_id' => $datos['pago_id'],
            'motivo' => 'No podre asistir',
        ])
            ->assertCreated()
            ->assertJsonPath(
                'reembolso.estado',
                'solicitado'
            )
            ->assertJsonPath(
                'reembolso.pago_id',
                $datos['pago_id']
            );

        // Cinco dias antes del evento:
        // Q500 menos 20% = Q400.
        $this->assertDatabaseHas('reembolsos', [
            'pago_id' => $datos['pago_id'],
            'monto' => 400,
            'estado' => 'solicitado',
            'motivo' => 'No podre asistir',
            'created_by' => $datos['cliente']->id,
        ]);

        $this->assertDatabaseCount('reembolsos', 1);

        // La solicitud no debe modificar el pago.
        $this->assertDatabaseHas('pagos', [
            'id' => $datos['pago_id'],
            'estado' => 'aprobado',
        ]);

        // Tampoco debe cancelar la reserva todavía.
        $this->assertDatabaseHas('reservas', [
            'id' => $datos['reserva_id'],
            'estado' => 'confirmada',
        ]);
    }

    public function test_rechaza_solicitud_duplicada(): void
    {
        $datos = $this->crearEscenario();

        Sanctum::actingAs($datos['cliente']);

        $solicitud = [
            'pago_id' => $datos['pago_id'],
        ];

        $this->postJson('/api/reembolsos', $solicitud)
            ->assertCreated();

        $this->postJson('/api/reembolsos', $solicitud)
            ->assertStatus(409);

        $this->assertDatabaseCount('reembolsos', 1);
    }

    public function test_rechaza_pago_de_otro_cliente(): void
    {
        $datos = $this->crearEscenario();

        $otroCliente = $this->crearUsuario(
            'otro-cliente@test.com',
            RolUsuario::CLIENTE
        );

        Sanctum::actingAs($otroCliente);

        $this->postJson('/api/reembolsos', [
            'pago_id' => $datos['pago_id'],
        ])->assertStatus(409);

        $this->assertDatabaseCount('reembolsos', 0);
    }

    private function crearEscenario(int $horas = 240): array
    {
        $cliente = $this->crearUsuario(
            'cliente-escenario@test.com',
            RolUsuario::CLIENTE
        );

        $recintoId = DB::table('recintos')->insertGetId([
            'nombre' => 'Recinto Reembolsos',
            'direccion' => 'Zona 1',
            'ciudad' => 'Guatemala',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $eventoId = DB::table('eventos')->insertGetId([
            'recinto_id' => $recintoId,
            'nombre' => 'Evento de Prueba',
            'fecha_evento' => now()->addHours($horas),
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

        return [
            'cliente' => $cliente,
            'evento_id' => $eventoId,
            'reserva_id' => $reservaId,
            'pago_id' => $pagoId,
        ];
    }

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
}
