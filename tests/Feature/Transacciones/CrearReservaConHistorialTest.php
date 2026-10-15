<?php

namespace Tests\Feature\Transacciones;

use App\Application\DTOs\Reservas\CrearReservaDTO;
use App\Application\UseCases\Reservas\CrearReservaUseCase;
use App\Application\UseCases\Transacciones\CrearReservaConHistorialUseCase;
use App\Application\UseCases\Transacciones\RegistrarOperacionHistorialUseCase;
use App\Domain\Enums\Auth\EstadoUsuario;
use App\Domain\Enums\Auth\RolUsuario;
use App\Models\Auth\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class CrearReservaConHistorialTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_reserva_y_registra_historial(): void
    {
        $dto = $this->prepararEscenario();

        $casoUso = app(CrearReservaConHistorialUseCase::class);

        $reserva = $casoUso->execute($dto);

        $this->assertNotNull($reserva->getId());

        $this->assertDatabaseHas('reservas', [
            'id' => $reserva->getId(),
            'usuario_id' => $dto->usuarioId,
            'evento_id' => $dto->eventoId,
            'estado' => 'activa',
        ]);

        $this->assertDatabaseHas('historial_operaciones', [
            'tipo' => 'reserva_creada',
            'usuario_id' => $dto->usuarioId,
            'evento_id' => $dto->eventoId,
            'reserva_id' => $reserva->getId(),
            'clave_idempotencia' =>
                'reserva-creada-' . $reserva->getId(),
        ]);

        $this->assertDatabaseCount('reservas', 1);
        $this->assertDatabaseCount('historial_operaciones', 1);
    }

    public function test_revierte_reserva_si_falla_historial(): void
    {
        $dto = $this->prepararEscenario();

        // Simulamos un error al registrar el historial.
        $historial = Mockery::mock(
            RegistrarOperacionHistorialUseCase::class
        );

        $historial->shouldReceive('execute')
            ->once()
            ->andThrow(
                new RuntimeException('Fallo simulado del historial')
            );

        $casoUso = new CrearReservaConHistorialUseCase(
            app(CrearReservaUseCase::class),
            $historial
        );

        try {
            $casoUso->execute($dto);

            $this->fail(
                'Se esperaba un error al guardar el historial.'
            );
        } catch (RuntimeException $e) {
            $this->assertSame(
                'Fallo simulado del historial',
                $e->getMessage()
            );
        }

        // Ninguna reserva debe permanecer guardada.
        $this->assertDatabaseCount('reservas', 0);

        // Tampoco deben existir registros de historial.
        $this->assertDatabaseCount('historial_operaciones', 0);
    }

    private function prepararEscenario(): CrearReservaDTO
    {
        $usuario = Usuario::create([
            'nombre' => 'Cliente',
            'apellido' => 'Prueba',
            'email' => 'cliente-reserva@test.com',
            'password' => bcrypt('Secreto#123'),
            'rol' => RolUsuario::CLIENTE,
            'estado' => EstadoUsuario::ACTIVO,
            'email_verified_at' => now(),
        ]);

        $recintoId = DB::table('recintos')->insertGetId([
            'nombre' => 'Recinto de Prueba',
            'direccion' => 'Zona 1',
            'ciudad' => 'Guatemala',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $eventoId = DB::table('eventos')->insertGetId([
            'recinto_id' => $recintoId,
            'nombre' => 'Evento de Prueba',
            'fecha_evento' => now()->addMonth(),
            'estado' => 'publicado',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return new CrearReservaDTO(
            usuarioId: $usuario->id,
            eventoId: $eventoId,
            total: 150.00,
            ttlSegundos: 300,
        );
    }
}
