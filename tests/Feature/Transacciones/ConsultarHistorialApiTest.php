<?php

namespace Tests\Feature\Transacciones;

use App\Domain\Enums\Auth\EstadoUsuario;
use App\Domain\Enums\Auth\RolUsuario;
use App\Models\Auth\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConsultarHistorialApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_rechaza_usuario_sin_autenticacion(): void
    {
        $this->getJson('/api/transacciones')
            ->assertUnauthorized();
    }

    public function test_cliente_no_puede_consultar_historial(): void
    {
        $cliente = $this->crearUsuario(
            'cliente@test.com',
            RolUsuario::CLIENTE
        );

        Sanctum::actingAs($cliente);

        $this->getJson('/api/transacciones')
            ->assertForbidden();
    }

    public function test_administrador_puede_consultar_historial(): void
    {
        $admin = $this->crearUsuario(
            'admin@test.com',
            RolUsuario::ADMIN
        );

        Sanctum::actingAs($admin);

        $this->getJson('/api/transacciones')
            ->assertOk()
            ->assertJsonPath(
                'mensaje',
                'Historial consultado correctamente.'
            )
            ->assertJsonStructure([
                'historial' => [
                    'data',
                    'current_page',
                    'total'
                ]
            ]);
    }

    public function test_filtra_por_usuario_tipo_y_fechas(): void
    {
        $admin = $this->crearUsuario(
            'admin@test.com',
            RolUsuario::ADMIN
        );

        $cliente = $this->crearUsuario(
            'cliente@test.com',
            RolUsuario::CLIENTE
        );

        $otro = $this->crearUsuario(
            'otro@test.com',
            RolUsuario::CLIENTE
        );

        $venta = $this->registrarMovimiento(
            $cliente->id,
            'venta',
            '150.00',
            now(),
            $admin->id
        );

        $this->registrarMovimiento(
            $cliente->id,
            'reembolso',
            '50.00',
            now(),
            $admin->id
        );

        $this->registrarMovimiento(
            $otro->id,
            'venta',
            '200.00',
            now()->subDays(10),
            $admin->id
        );

        Sanctum::actingAs($admin);

        $this->getJson(
            '/api/transacciones?' . http_build_query([
                'usuario_id' => $cliente->id,
                'tipo' => 'venta',
                'fecha_desde' => now()->subDay()->toDateString(),
                'fecha_hasta' => now()->toDateString(),
            ])
        )
            ->assertOk()
            ->assertJsonPath('historial.total', 1)
            ->assertJsonPath(
                'historial.data.0.id',
                $venta
            );
    }

    public function test_filtra_por_evento_y_aplica_paginacion(): void
    {
        $admin = $this->crearUsuario(
            'admin@test.com',
            RolUsuario::ADMIN
        );

        $cliente = $this->crearUsuario(
            'cliente@test.com',
            RolUsuario::CLIENTE
        );

        $recinto = DB::table('recintos')->insertGetId([
            'nombre' => 'Recinto Prueba',
            'direccion' => 'Zona 1',
            'ciudad' => 'Guatemala',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $evento = DB::table('eventos')->insertGetId([
            'recinto_id' => $recinto,
            'nombre' => 'Evento Historial',
            'fecha_evento' => now()->addMonth(),
            'estado' => 'publicado',
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

        $primera = $this->registrarMovimiento(
            $cliente->id,
            'venta',
            '150.00',
            now()->subMinute(),
            $admin->id,
            $reserva
        );

        $this->registrarMovimiento(
            $cliente->id,
            'reembolso',
            '50.00',
            now(),
            $admin->id,
            $reserva
        );

        $this->registrarMovimiento(
            $cliente->id,
            'venta',
            '75.00',
            now(),
            $admin->id
        );

        Sanctum::actingAs($admin);

        $this->getJson(
            '/api/transacciones?' . http_build_query([
                'evento_id' => $evento,
                'per_page' => 1,
                'page' => 2,
            ])
        )
            ->assertOk()
            ->assertJsonPath('historial.total', 2)
            ->assertJsonPath('historial.current_page', 2)
            ->assertJsonCount(1, 'historial.data')
            ->assertJsonPath(
                'historial.data.0.id',
                $primera
            );
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

    private function registrarMovimiento(
        int $usuarioId,
        string $tipo,
        string $monto,
        $fecha,
        int $creadoPor,
        ?int $reservaId = null
    ): int {
        return DB::table('transacciones')->insertGetId([
            'tipo' => $tipo,
            'monto' => $monto,
            'usuario_id' => $usuarioId,
            'reserva_id' => $reservaId,
            'pago_id' => null,
            'referencia' => null,
            'descripcion' => 'Movimiento de prueba',
            'created_by' => $creadoPor,
            'created_at' => $fecha,
        ]);
    }
}
