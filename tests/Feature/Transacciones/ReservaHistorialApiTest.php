<?php

namespace Tests\Feature\Transacciones;

use App\Domain\Enums\Auth\EstadoUsuario;
use App\Domain\Enums\Auth\RolUsuario;
use App\Models\Auth\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReservaHistorialApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_crear_reserva_desde_api_registra_historial(): void
    {
        // Crear un cliente de prueba.
        $cliente = $this->crearCliente();

        // Crear un recinto y evento de prueba.
        $eventoId = $this->crearEvento();

        // Autenticar al cliente.
        Sanctum::actingAs($cliente);

        // Solicitar la creación de la reserva.
        $respuesta = $this->postJson('/api/reservas', [
            'evento_id' => $eventoId,
            'total' => 150.00,
        ]);

        // La API debe confirmar la creación.
        $respuesta->assertCreated()
            ->assertJsonPath('estado', 'activa');

        $reservaId = $respuesta->json('id');

        $this->assertNotNull($reservaId);

        // Confirmar que la reserva fue guardada.
        $this->assertDatabaseHas('reservas', [
            'id' => $reservaId,
            'usuario_id' => $cliente->id,
            'evento_id' => $eventoId,
            'estado' => 'activa',
        ]);

        // Confirmar el registro automático del historial.
        $this->assertDatabaseHas('historial_operaciones', [
            'tipo' => 'reserva_creada',
            'usuario_id' => $cliente->id,
            'evento_id' => $eventoId,
            'reserva_id' => $reservaId,
            'registrado_por' => $cliente->id,
            'clave_idempotencia' => 'reserva-creada-' . $reservaId,
        ]);

        $this->assertDatabaseCount('reservas', 1);
        $this->assertDatabaseCount('historial_operaciones', 1);

        // Verificar los datos adicionales del historial.
        $operacion = DB::table('historial_operaciones')
            ->where('reserva_id', $reservaId)
            ->first();

        $datos = json_decode($operacion->datos, true);

        $this->assertEquals('activa', $datos['estado']);
        $this->assertEquals(150.00, $datos['total']);
        $this->assertArrayHasKey('expira_en', $datos);
    }

    public function test_no_registra_historial_si_reserva_es_invalida(): void
    {
        $cliente = $this->crearCliente();

        Sanctum::actingAs($cliente);

        // Intentar reservar un evento inexistente.
        $this->postJson('/api/reservas', [
            'evento_id' => 999999,
            'total' => 150.00,
        ])->assertUnprocessable();

        $this->assertDatabaseCount('reservas', 0);
        $this->assertDatabaseCount('historial_operaciones', 0);
    }

    private function crearCliente(): Usuario
    {
        return Usuario::create([
            'nombre' => 'Cliente',
            'apellido' => 'Prueba',
            'email' => 'cliente-api-historial@test.com',
            'password' => bcrypt('Secreto#123'),
            'rol' => RolUsuario::CLIENTE,
            'estado' => EstadoUsuario::ACTIVO,
            'email_verified_at' => now(),
        ]);
    }

    private function crearEvento(): int
    {
        $recintoId = DB::table('recintos')->insertGetId([
            'nombre' => 'Recinto Historial API',
            'direccion' => 'Zona 1',
            'ciudad' => 'Guatemala',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('eventos')->insertGetId([
            'recinto_id' => $recintoId,
            'nombre' => 'Concierto de Prueba',
            'fecha_evento' => now()->addMonth(),
            'estado' => 'publicado',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
