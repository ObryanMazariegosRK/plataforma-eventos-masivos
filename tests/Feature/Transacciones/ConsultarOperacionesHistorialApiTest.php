<?php

namespace Tests\Feature\Transacciones;

use App\Domain\Enums\Auth\EstadoUsuario;
use App\Domain\Enums\Auth\RolUsuario;
use App\Models\Auth\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConsultarOperacionesHistorialApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_rechaza_usuario_no_autenticado(): void
    {
        $this->getJson('/api/historial-operaciones')
            ->assertUnauthorized();
    }

    public function test_cliente_no_puede_consultar_operaciones(): void
    {
        $cliente = $this->crearUsuario(
            'cliente-historial@test.com',
            RolUsuario::CLIENTE
        );

        Sanctum::actingAs($cliente);

        $this->getJson('/api/historial-operaciones')
            ->assertForbidden();
    }

    public function test_administrador_filtra_operaciones(): void
    {
        $admin = $this->crearUsuario(
            'admin-historial@test.com',
            RolUsuario::ADMIN
        );

        $this->registrarOperacion(
            'reserva_creada',
            10,
            20,
            'reserva-creada-100'
        );

        $this->registrarOperacion(
            'reserva_cancelada',
            10,
            20,
            'reserva-cancelada-100'
        );

        $this->registrarOperacion(
            'reserva_creada',
            11,
            21,
            'reserva-creada-101'
        );

        Sanctum::actingAs($admin);

        $this->getJson(
            '/api/historial-operaciones?' . http_build_query([
                'usuario_id' => 10,
                'evento_id' => 20,
                'tipo' => 'reserva_creada',
            ])
        )
            ->assertOk()
            ->assertJsonPath('historial.total', 1)
            ->assertJsonPath(
                'historial.data.0.clave_idempotencia',
                null
            )
            ->assertJsonPath(
                'historial.data.0.tipo',
                'reserva_creada'
            )
            ->assertJsonPath(
                'historial.data.0.usuario_id',
                10
            );
    }

    public function test_administrador_consulta_fechas_y_paginacion(): void
    {
        $admin = $this->crearUsuario(
            'admin-fechas@test.com',
            RolUsuario::ADMIN
        );

        $this->registrarOperacion(
            'reserva_creada',
            10,
            20,
            'operacion-antigua',
            now()->subDays(10)
        );

        $this->registrarOperacion(
            'reserva_liberada',
            10,
            20,
            'operacion-reciente-1',
            now()->subHour()
        );

        $this->registrarOperacion(
            'reserva_cancelada',
            10,
            20,
            'operacion-reciente-2',
            now()
        );

        Sanctum::actingAs($admin);

        $this->getJson(
            '/api/historial-operaciones?' . http_build_query([
                'fecha_desde' => now()->subDay()->toDateString(),
                'fecha_hasta' => now()->toDateString(),
                'per_page' => 1,
                'page' => 2,
            ])
        )
            ->assertOk()
            ->assertJsonPath('historial.total', 2)
            ->assertJsonPath('historial.current_page', 2)
            ->assertJsonCount(1, 'historial.data')
            ->assertJsonPath(
                'historial.data.0.tipo',
                'reserva_liberada'
            );

        $this->getJson(
            '/api/historial-operaciones?tipo=operacion_inexistente'
        )->assertUnprocessable();
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

    private function registrarOperacion(
        string $tipo,
        int $usuarioId,
        int $eventoId,
        string $clave,
        $fecha = null
    ): void {
        DB::table('historial_operaciones')->insert([
            'tipo' => $tipo,
            'usuario_id' => $usuarioId,
            'evento_id' => $eventoId,
            'reserva_id' => null,
            'inventario_id' => null,
            'pago_id' => null,
            'registrado_por' => null,
            'descripcion' => 'Operación de prueba',
            'datos' => null,
            'clave_idempotencia' => $clave,
            'created_at' => $fecha ?? now(),
        ]);
    }
}
