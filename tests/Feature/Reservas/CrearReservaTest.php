<?php

namespace Tests\Feature\Reservas;

use App\Domain\Enums\Auth\EstadoUsuario;
use App\Domain\Enums\Auth\RolUsuario;
use App\Models\Auth\Usuario as UsuarioModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CrearReservaTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_una_reserva_activa_a_nombre_del_usuario_del_token(): void
    {
        $usuario = $this->crearUsuario('ana@example.com');
        $eventoId = $this->crearEvento();

        $response = $this->withToken($this->tokenDe($usuario))->postJson('/api/reservas', [
            'evento_id' => $eventoId,
            'total'     => 250.75,
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['id', 'estado', 'expira_en', 'total'])
            ->assertJson(['estado' => 'activa', 'total' => 250.75]);

        $this->assertDatabaseHas('reservas', [
            'id'         => $response->json('id'),
            'usuario_id' => $usuario->id,
            'evento_id'  => $eventoId,
            'estado'     => 'activa',
        ]);
    }

    public function test_sin_token_responde_401(): void
    {
        $this->postJson('/api/reservas', [
            'evento_id' => $this->crearEvento(),
            'total'     => 100,
        ])->assertUnauthorized();

        $this->assertDatabaseCount('reservas', 0);
    }

    public function test_ignora_un_usuario_id_enviado_en_el_cuerpo(): void
    {
        $ana = $this->crearUsuario('ana@example.com');
        $otro = $this->crearUsuario('otro@example.com');

        // Ana intenta reservar "a nombre" de otro usuario
        $this->withToken($this->tokenDe($ana))->postJson('/api/reservas', [
            'usuario_id' => $otro->id,
            'evento_id'  => $this->crearEvento(),
            'total'      => 100,
        ])->assertCreated();

        $this->assertDatabaseHas('reservas', ['usuario_id' => $ana->id]);
        $this->assertDatabaseMissing('reservas', ['usuario_id' => $otro->id]);
    }

    public function test_valida_los_datos_de_entrada(): void
    {
        $usuario = $this->crearUsuario('ana@example.com');

        $this->withToken($this->tokenDe($usuario))->postJson('/api/reservas', [
            'evento_id' => 0,
            'total'     => 0,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['evento_id', 'total']);

        $this->assertDatabaseCount('reservas', 0);
    }

    private function crearUsuario(string $email): UsuarioModel
    {
        return UsuarioModel::create([
            'nombre'            => 'Ana',
            'apellido'          => 'López',
            'email'             => $email,
            'password'          => Hash::make('Secreto#123'),
            'rol'               => RolUsuario::CLIENTE,
            'estado'            => EstadoUsuario::ACTIVO,
            'email_verified_at' => now(),
        ]);
    }

    private function tokenDe(UsuarioModel $usuario): string
    {
        return $usuario->createToken('pruebas')->plainTextToken;
    }

    private function crearEvento(): int
    {
        $recintoId = DB::table('recintos')->insertGetId([
            'nombre'     => 'Estadio',
            'direccion'  => 'Zona 1',
            'ciudad'     => 'Guatemala',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('eventos')->insertGetId([
            'recinto_id'   => $recintoId,
            'nombre'       => 'Concierto',
            'fecha_evento' => now()->addMonth(),
            'estado'       => 'publicado',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }
}
