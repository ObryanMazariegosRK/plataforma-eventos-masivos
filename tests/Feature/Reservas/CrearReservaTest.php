<?php

namespace Tests\Feature\Reservas;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CrearReservaTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_una_reserva_activa(): void
    {
        [$usuarioId, $eventoId] = $this->crearUsuarioYEvento();

        $response = $this->postJson('/api/reservas', [
            'usuario_id' => $usuarioId,
            'evento_id'  => $eventoId,
            'total'      => 250.75,
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['id', 'estado', 'expira_en', 'total'])
            ->assertJson(['estado' => 'activa', 'total' => 250.75]);

        $this->assertDatabaseHas('reservas', [
            'id'         => $response->json('id'),
            'usuario_id' => $usuarioId,
            'evento_id'  => $eventoId,
            'estado'     => 'activa',
        ]);
    }

    public function test_valida_los_datos_de_entrada(): void
    {
        $this->postJson('/api/reservas', [
            'usuario_id' => 999,
            'evento_id'  => 0,
            'total'      => 0,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['usuario_id', 'evento_id', 'total']);

        $this->assertDatabaseCount('reservas', 0);
    }

    /** @return array{int, int} */
    private function crearUsuarioYEvento(): array
    {
        $usuarioId = DB::table('usuarios')->insertGetId([
            'nombre'     => 'Ana',
            'apellido'   => 'López',
            'email'      => 'ana@example.com',
            'password'   => bcrypt('secreto'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $recintoId = DB::table('recintos')->insertGetId([
            'nombre'     => 'Estadio',
            'direccion'  => 'Zona 1',
            'ciudad'     => 'Guatemala',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $eventoId = DB::table('eventos')->insertGetId([
            'recinto_id'   => $recintoId,
            'nombre'       => 'Concierto',
            'fecha_evento' => now()->addMonth(),
            'estado'       => 'publicado',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        return [$usuarioId, $eventoId];
    }
}
