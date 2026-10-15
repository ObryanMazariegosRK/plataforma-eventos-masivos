<?php

namespace Tests\Feature\Boletos;

use App\Domain\Enums\Auth\EstadoUsuario;
use App\Domain\Enums\Auth\RolUsuario;
use App\Models\Auth\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ValidarBoletoApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_rechaza_usuario_no_autenticado(): void
    {
        $this->postJson('/api/boletos/validar', [
            'codigo_qr' => str_repeat('a', 64),
            'evento_id' => 1,
        ])->assertUnauthorized();
    }

    public function test_cliente_no_puede_validar_boletos(): void
    {
        $datos = $this->crearEscenario();

        Sanctum::actingAs($datos['cliente']);

        $this->postJson('/api/boletos/validar', [
            'codigo_qr' => $datos['codigo'],
            'evento_id' => $datos['eventoId'],
        ])->assertForbidden();

        $this->assertDatabaseHas('boletos', [
            'id' => $datos['boletoId'],
            'estado' => 'emitido',
        ]);
    }

    public function test_administrador_valida_una_sola_vez(): void
    {
        $datos = $this->crearEscenario();

        Sanctum::actingAs($datos['admin']);

        $entrada = [
            'codigo_qr' => $datos['codigo'],
            'evento_id' => $datos['eventoId'],
        ];

        $this->postJson('/api/boletos/validar', $entrada)
            ->assertOk()
            ->assertJsonPath('boleto.estado', 'usado');

        $this->assertDatabaseHas('boletos', [
            'id' => $datos['boletoId'],
            'estado' => 'usado',
        ]);

        $this->assertNotNull(
            DB::table('boletos')
                ->where('id', $datos['boletoId'])
                ->value('usado_en')
        );

        // Segundo escaneo: debe rechazarse.
        $this->postJson('/api/boletos/validar', $entrada)
            ->assertStatus(409);

        $this->assertDatabaseCount('boletos', 1);
    }

    public function test_rechaza_boleto_de_otro_evento(): void
    {
        $datos = $this->crearEscenario();

        Sanctum::actingAs($datos['admin']);

        $this->postJson('/api/boletos/validar', [
            'codigo_qr' => $datos['codigo'],
            'evento_id' => $datos['eventoId'] + 1000,
        ])->assertStatus(409);

        $this->assertDatabaseHas('boletos', [
            'id' => $datos['boletoId'],
            'estado' => 'emitido',
        ]);
    }

    private function crearEscenario(): array
    {
        $cliente = $this->crearUsuario(
            'cliente-prueba@example.test',
            RolUsuario::CLIENTE
        );

        $admin = $this->crearUsuario(
            'admin-prueba@example.test',
            RolUsuario::ADMIN
        );

        $recinto = DB::table('recintos')->insertGetId([
            'nombre' => 'Recinto de Prueba',
            'direccion' => 'Zona 1',
            'ciudad' => 'Guatemala',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $evento = DB::table('eventos')->insertGetId([
            'recinto_id' => $recinto,
            'nombre' => 'Concierto QR',
            'fecha_evento' => now()->addMonth(),
            'estado' => 'publicado',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $localidad = DB::table('localidades')->insertGetId([
            'recinto_id' => $recinto,
            'nombre' => 'General',
            'tipo' => 'general',
            'capacidad' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $reserva = DB::table('reservas')->insertGetId([
            'usuario_id' => $cliente->id,
            'evento_id' => $evento,
            'estado' => 'confirmada',
            'expira_en' => now()->addMinutes(5),
            'total' => 150,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $inventario = DB::table('inventario')->insertGetId([
            'evento_id' => $evento,
            'localidad_id' => $localidad,
            'reserva_id' => $reserva,
            'estado' => 'vendido',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $codigo = str_repeat('a', 64);

        $boletoId = DB::table('boletos')->insertGetId([
            'reserva_id' => $reserva,
            'inventario_id' => $inventario,
            'usuario_id' => $cliente->id,
            'codigo_qr' => $codigo,
            'estado' => 'emitido',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'cliente' => $cliente,
            'admin' => $admin,
            'eventoId' => $evento,
            'boletoId' => $boletoId,
            'codigo' => $codigo,
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
