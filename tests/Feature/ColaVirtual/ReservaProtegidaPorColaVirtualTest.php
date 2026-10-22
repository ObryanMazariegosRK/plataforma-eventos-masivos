<?php

namespace Tests\Feature\ColaVirtual;

use App\Models\Auth\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReservaProtegidaPorColaVirtualTest extends TestCase
{
    use RefreshDatabase;

    public function test_usuario_sin_turno_no_puede_reservar_en_evento_con_cola_activa(): void
    {
        // Crear usuario autenticado.
        $usuario = Usuario::create([
            'nombre' => 'Cliente',
            'apellido' => 'Prueba',
            'email' => 'cliente-cola@example.com',
            'password' => Hash::make('Secreto#123'),
            'rol' => 'cliente',
            'estado' => 'activo',
            'email_verified_at' => now(),
        ]);

        // Crear recinto.
        $recintoId = DB::table('recintos')->insertGetId([
            'nombre' => 'Recinto Cola',
            'direccion' => 'Zona 1',
            'ciudad' => 'Guatemala',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Crear evento publicado con Cola Virtual activa.
        $eventoId = DB::table('eventos')->insertGetId([
            'recinto_id' => $recintoId,
            'nombre' => 'Evento con Cola Virtual',
            'fecha_evento' => now()->addMonth(),
            'estado' => 'publicado',
            'cola_virtual_activa' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Preparar un asiento disponible.
        $localidadId = DB::table('localidades')->insertGetId([
            'recinto_id' => $recintoId,
            'nombre' => 'General',
            'tipo' => 'numerada',
            'capacidad' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $asientoId = DB::table('asientos')->insertGetId([
            'localidad_id' => $localidadId,
            'fila' => 'A',
            'numero' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('precios')->insert([
            'evento_id' => $eventoId,
            'localidad_id' => $localidadId,
            'precio' => 150.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $inventarioId = DB::table('inventario')->insertGetId([
            'evento_id' => $eventoId,
            'localidad_id' => $localidadId,
            'asiento_id' => $asientoId,
            'estado' => 'disponible',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // El usuario NO tiene ningún turno en Cola Virtual.
        $this->assertDatabaseCount('cola_virtual', 0);

        // Intenta reservar directamente mediante la API.
        $token = $usuario->createToken('prueba-reserva-cola')->plainTextToken;

        $respuesta = $this->withToken($token)
            ->postJson('/api/reservas', [
                'evento_id' => $eventoId,
                'inventario_ids' => [$inventarioId],
            ]);

        // Debe rechazarse porque no tiene turno habilitado.
        $respuesta->assertUnprocessable()
            ->assertJsonValidationErrors(['cola_virtual']);

        // No debe existir ninguna reserva.
        $this->assertDatabaseCount('reservas', 0);

        // El asiento debe seguir disponible.
        $this->assertDatabaseHas('inventario', [
            'id' => $inventarioId,
            'estado' => 'disponible',
            'reserva_id' => null,
        ]);

        // SEGUNDO ESCENARIO: el usuario ya tiene un turno,
        // pero todavía está esperando.

        DB::table('cola_virtual')->insert([
            'evento_id' => $eventoId,
            'usuario_id' => $usuario->id,
            'posicion' => 1,
            'token' => bin2hex(random_bytes(32)),
            'estado' => 'esperando',
            'habilitado_hasta' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Intentar reservar nuevamente.
        $segundaRespuesta = $this->withToken($token)
            ->postJson('/api/reservas', [
                'evento_id' => $eventoId,
                'inventario_ids' => [$inventarioId],
            ]);

        // Debe seguir rechazando la reserva.
        $segundaRespuesta->assertUnprocessable()
            ->assertJsonValidationErrors(['cola_virtual']);

        // No se crea ninguna reserva.
        $this->assertDatabaseCount('reservas', 0);

        // El turno debe seguir esperando.
        $this->assertDatabaseHas('cola_virtual', [
            'evento_id' => $eventoId,
            'usuario_id' => $usuario->id,
            'estado' => 'esperando',
        ]);

        // El asiento sigue disponible.
        $this->assertDatabaseHas('inventario', [
            'id' => $inventarioId,
            'estado' => 'disponible',
            'reserva_id' => null,
        ]);

        // TERCER ESCENARIO: habilitar el turno del usuario.
        DB::table('cola_virtual')
            ->where('evento_id', $eventoId)
            ->where('usuario_id', $usuario->id)
            ->update([
                'estado' => 'habilitado',
                'habilitado_hasta' => now()->addMinutes(5),
                'updated_at' => now(),
            ]);

        // El usuario intenta reservar con su turno vigente.
        $terceraRespuesta = $this->withToken($token)
            ->postJson('/api/reservas', [
                'evento_id' => $eventoId,
                'inventario_ids' => [$inventarioId],
            ]);

        // Ahora sí debe permitirse la reserva.
        $terceraRespuesta->assertCreated()
            ->assertJson([
                'estado' => 'activa',
                'total' => 150,
            ]);

        // La reserva debe pertenecer al usuario correcto.
        $this->assertDatabaseHas('reservas', [
            'id' => $terceraRespuesta->json('id'),
            'evento_id' => $eventoId,
            'usuario_id' => $usuario->id,
            'estado' => 'activa',
        ]);

        // El asiento debe quedar bloqueado por la reserva.
        $this->assertDatabaseHas('inventario', [
            'id' => $inventarioId,
            'reserva_id' => $terceraRespuesta->json('id'),
            'estado' => 'bloqueado',
        ]);

        $this->assertDatabaseCount('reservas', 1);
        
        // Una reserva exitosa debe consumir la autorización.
        $this->assertDatabaseHas('cola_virtual', [
            'evento_id' => $eventoId,
            'usuario_id' => $usuario->id,
            'estado' => 'atendido',
        ]);

        // CUARTO ESCENARIO: el turno ya venció.
        DB::table('cola_virtual')
            ->where('evento_id', $eventoId)
            ->where('usuario_id', $usuario->id)
            ->update([
                'estado' => 'habilitado',
                'habilitado_hasta' => now()->subMinute(),
                'updated_at' => now(),
            ]);

        // Intentar reservar utilizando un turno vencido.
        $cuartaRespuesta = $this->withToken($token)
            ->postJson('/api/reservas', [
                'evento_id' => $eventoId,
                'inventario_ids' => [$inventarioId],
            ]);

        // Debe rechazar la autorización de Cola Virtual.
        $cuartaRespuesta->assertUnprocessable()
            ->assertJsonValidationErrors(['cola_virtual']);

        // No debe crearse una segunda reserva.
        $this->assertDatabaseCount('reservas', 1);

        // La reserva anterior debe permanecer intacta.
        $this->assertDatabaseHas('reservas', [
            'id' => $terceraRespuesta->json('id'),
            'usuario_id' => $usuario->id,
            'estado' => 'activa',
        ]);
        
        // QUINTO ESCENARIO: una reserva fallida no consume el turno.

        // Simular una nueva ventana de compra vigente.
        DB::table('cola_virtual')
            ->where('evento_id', $eventoId)
            ->where('usuario_id', $usuario->id)
            ->update([
                'estado' => 'habilitado',
                'habilitado_hasta' => now()->addMinutes(5),
                'updated_at' => now(),
            ]);

        // Intentar reservar el asiento que ya está ocupado.
        $quintaRespuesta = $this->withToken($token)
            ->postJson('/api/reservas', [
                'evento_id' => $eventoId,
                'inventario_ids' => [$inventarioId],
            ]);

        // Debe rechazar la reserva por inventario no disponible.
        $quintaRespuesta->assertUnprocessable()
            ->assertJsonValidationErrors(['inventario_ids']);

        // La reserva fallida NO debe consumir el turno.
        $this->assertDatabaseHas('cola_virtual', [
            'evento_id' => $eventoId,
            'usuario_id' => $usuario->id,
            'estado' => 'habilitado',
        ]);

        // No debe haberse creado una segunda reserva.
        $this->assertDatabaseCount('reservas', 1);

        
        // SEXTO ESCENARIO: un turno atendido no puede reutilizarse.

        // Simular que el turno ya fue consumido.
        DB::table('cola_virtual')
            ->where('evento_id', $eventoId)
            ->where('usuario_id', $usuario->id)
            ->update([
                'estado' => 'atendido',
                'habilitado_hasta' => now()->addMinutes(5),
                'updated_at' => now(),
            ]);

        // Intentar crear otra reserva con el turno atendido.
        $sextaRespuesta = $this->withToken($token)
            ->postJson('/api/reservas', [
                'evento_id' => $eventoId,
                'inventario_ids' => [$inventarioId],
            ]);

        // Debe rechazarse específicamente por Cola Virtual.
        $sextaRespuesta->assertUnprocessable()
            ->assertJsonValidationErrors(['cola_virtual']);

        // El turno debe continuar atendido.
        $this->assertDatabaseHas('cola_virtual', [
            'evento_id' => $eventoId,
            'usuario_id' => $usuario->id,
            'estado' => 'atendido',
        ]);

        // No se crea otra reserva.
        $this->assertDatabaseCount('reservas', 1);

    }
    
}
