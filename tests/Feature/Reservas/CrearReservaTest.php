<?php

namespace Tests\Feature\Reservas;

use App\Domain\Enums\Auth\EstadoUsuario;
use App\Domain\Enums\Auth\RolUsuario;
use App\Models\Auth\Usuario as UsuarioModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use App\Application\UseCases\Reservas\CancelarReservaUseCase;
use App\Application\UseCases\Reservas\ExpirarReservasVencidasUseCase;

class CrearReservaTest extends TestCase
{
    use RefreshDatabase;


    public function test_crea_una_reserva_activa_a_nombre_del_usuario_del_token(): void
    {
        $usuario = $this->crearUsuario('ana@example.com');
        $eventoId = $this->crearEvento();

        // Crear un asiento disponible con precio oficial.
        $inventarioId = $this->crearItemInventario(
            $eventoId,
            250.75
        );

        $response = $this->withToken(
            $this->tokenDe($usuario)
        )->postJson('/api/reservas', [
            'evento_id' => $eventoId,
            'inventario_ids' => [$inventarioId],
        ]);

        $response->assertCreated()
            ->assertJsonStructure([
                'id',
                'estado',
                'expira_en',
                'total',
            ])
            ->assertJson([
                'estado' => 'activa',
                'total' => 250.75,
            ]);

        $this->assertDatabaseHas('reservas', [
            'id' => $response->json('id'),
            'usuario_id' => $usuario->id,
            'evento_id' => $eventoId,
            'estado' => 'activa',
            'total' => 250.75,
        ]);

        // Comprobar que el asiento quedo bloqueado.
        $this->assertDatabaseHas('inventario', [
            'id' => $inventarioId,
            'reserva_id' => $response->json('id'),
            'estado' => 'bloqueado',
        ]);

        // Comprobar que existe una fecha de vencimiento.
        $this->assertNotNull(
            DB::table('inventario')
                ->where('id', $inventarioId)
                ->value('bloqueado_hasta')
        );
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

        $eventoId = $this->crearEvento();
        $inventarioId = $this->crearItemInventario($eventoId, 100);

        // Ana intenta reservar "a nombre" de otro usuario
        $this->withToken($this->tokenDe($ana))->postJson('/api/reservas', [
            'usuario_id' => $otro->id,
            'evento_id' => $eventoId,
            'inventario_ids' => [$inventarioId],

        ])->assertCreated();

        $this->assertDatabaseHas('reservas', ['usuario_id' => $ana->id]);
        $this->assertDatabaseMissing('reservas', ['usuario_id' => $otro->id]);
    }

    public function test_valida_los_datos_de_entrada(): void
    {
        $usuario = $this->crearUsuario('ana@example.com');

        $this->withToken($this->tokenDe($usuario))->postJson('/api/reservas', [
            'evento_id' => 0,
            'inventario_ids' => [],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'evento_id',
                'inventario_ids',
            ]);
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

    private function crearItemInventario(
        int $eventoId,
        float $precio
    ): int {
        $recintoId = DB::table('eventos')
            ->where('id', $eventoId)
            ->value('recinto_id');

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
            'precio' => $precio,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('inventario')->insertGetId([
            'evento_id' => $eventoId,
            'localidad_id' => $localidadId,
            'asiento_id' => $asientoId,
            'estado' => 'disponible',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
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

    public function test_impide_reservar_un_asiento_ya_bloqueado(): void
    {
        // Crear dos compradores.
        $ana = $this->crearUsuario('ana@example.com');
        $carlos = $this->crearUsuario('carlos@example.com');

        // Crear un evento con un asiento disponible.
        $eventoId = $this->crearEvento();

        $inventarioId = $this->crearItemInventario(
            $eventoId,
            250.75
        );

        $datos = [
            'evento_id' => $eventoId,
            'inventario_ids' => [$inventarioId],
        ];

        // Ana reserva el asiento primero.
        $primera = $this->withToken(
            $this->tokenDe($ana)
        )->postJson('/api/reservas', $datos);

        $primera->assertCreated();

        // Carlos intenta reservar exactamente el mismo asiento.
        $segunda = $this->withToken(
            $this->tokenDe($carlos)
        )->postJson('/api/reservas', $datos);

        // El sistema debe rechazar la segunda solicitud.
        $segunda->assertUnprocessable()
            ->assertJsonValidationErrors('inventario_ids');

        // Solo debe existir la reserva de Ana.
        $this->assertDatabaseCount('reservas', 1);

        $this->assertDatabaseHas('inventario', [
            'id' => $inventarioId,
            'estado' => 'bloqueado',
            'reserva_id' => $primera->json('id'),
        ]);

        $this->assertDatabaseMissing('reservas', [
            'usuario_id' => $carlos->id,
            'evento_id' => $eventoId,
        ]);
    }

    public function test_cancela_reserva_y_libera_asiento(): void
    {
        // 1. Preparar comprador, evento y asiento.
        $usuario = $this->crearUsuario('ana@example.com');
        $eventoId = $this->crearEvento();
        $inventarioId = $this->crearItemInventario(
            $eventoId,
            250.75
        );

        // 2. Crear la reserva utilizando nuestra API.
        $response = $this->withToken(
            $this->tokenDe($usuario)
        )->postJson('/api/reservas', [
            'evento_id' => $eventoId,
            'inventario_ids' => [$inventarioId],
        ]);

        $response->assertCreated();

        $reservaId = $response->json('id');

        // 3. Comprobar que el asiento esta bloqueado.
        $this->assertDatabaseHas('inventario', [
            'id' => $inventarioId,
            'estado' => 'bloqueado',
            'reserva_id' => $reservaId,
        ]);

        // 4. Ejecutar la cancelacion manual.
        $reserva = app(
            CancelarReservaUseCase::class
        )->execute(
            reservaId: $reservaId,
            usuarioId: $usuario->id
        );

        // 5. Verificar el estado de la reserva.
        $this->assertSame(
            'cancelada',
            $reserva->getEstado()->value
        );

        $this->assertDatabaseHas('reservas', [
            'id' => $reservaId,
            'estado' => 'cancelada',
        ]);

        // 6. El asiento debe estar disponible nuevamente.
        $this->assertDatabaseHas('inventario', [
            'id' => $inventarioId,
            'estado' => 'disponible',
            'reserva_id' => null,
            'bloqueado_hasta' => null,
        ]);

        // 7. No debe eliminarse el historial de la reserva.
        $this->assertDatabaseCount('reservas', 1);
    }


    public function test_impide_cancelar_reserva_de_otro_usuario(): void
    {
        $ana = $this->crearUsuario('ana@example.com');
        $carlos = $this->crearUsuario('carlos@example.com');

        $eventoId = $this->crearEvento();
        $inventarioId = $this->crearItemInventario(
            $eventoId,
            250.75
        );

        // Ana crea su reserva.
        $response = $this->withToken(
            $this->tokenDe($ana)
        )->postJson('/api/reservas', [
            'evento_id' => $eventoId,
            'inventario_ids' => [$inventarioId],
        ]);

        $response->assertCreated();
        $reservaId = $response->json('id');

        // Carlos intenta cancelar la reserva de Ana.
        try {
            app(CancelarReservaUseCase::class)->execute(
                reservaId: $reservaId,
                usuarioId: $carlos->id
            );

            $this->fail(
                'No se debe permitir cancelar una reserva ajena.'
            );
        } catch (\DomainException $e) {
            $this->assertSame(
                'No tienes permiso para cancelar esta reserva.',
                $e->getMessage()
            );
        }

        // La reserva de Ana debe continuar activa.
        $this->assertDatabaseHas('reservas', [
            'id' => $reservaId,
            'usuario_id' => $ana->id,
            'estado' => 'activa',
        ]);

        // El asiento debe continuar bloqueado para Ana.
        $this->assertDatabaseHas('inventario', [
            'id' => $inventarioId,
            'estado' => 'bloqueado',
            'reserva_id' => $reservaId,
        ]);
    }

    public function test_cliente_cancela_su_reserva_desde_api(): void
    {
        $ana = $this->crearUsuario('ana@example.com');
        $eventoId = $this->crearEvento();
        $inventarioId = $this->crearItemInventario($eventoId, 250.75);

        $token = $this->tokenDe($ana);

        // Crear reserva.
        $respuesta = $this->withToken($token)->postJson(
            '/api/reservas',
            [
                'evento_id' => $eventoId,
                'inventario_ids' => [$inventarioId],
            ]
        );

        $respuesta->assertCreated();

        $reservaId = $respuesta->json('id');

        // Cancelar mediante DELETE.
        $this->withToken($token)
            ->deleteJson("/api/reservas/{$reservaId}")
            ->assertOk()
            ->assertJson([
                'id' => $reservaId,
                'estado' => 'cancelada',
            ]);

        $this->assertDatabaseHas('reservas', [
            'id' => $reservaId,
            'estado' => 'cancelada',
        ]);

        $this->assertDatabaseHas('inventario', [
            'id' => $inventarioId,
            'estado' => 'disponible',
            'reserva_id' => null,
            'bloqueado_hasta' => null,
        ]);
    }

    public function test_otro_cliente_no_puede_cancelar_reserva_desde_api(): void
    {
        $ana = $this->crearUsuario('ana@example.com');
        $carlos = $this->crearUsuario('carlos@example.com');

        $eventoId = $this->crearEvento();
        $inventarioId = $this->crearItemInventario($eventoId, 250.75);

        $respuesta = $this->withToken(
            $this->tokenDe($ana)
        )->postJson('/api/reservas', [
            'evento_id' => $eventoId,
            'inventario_ids' => [$inventarioId],
        ]);


        $respuesta->assertCreated();

        $reservaId = $respuesta->json('id');

        // Limpiar el usuario autenticado de la solicitud anterior.
        $this->app['auth']->forgetGuards();

        // Carlos intenta cancelar la reserva de Ana.

        $this->withToken($this->tokenDe($carlos))
            ->deleteJson("/api/reservas/{$reservaId}")
            ->assertForbidden();

        $this->assertDatabaseHas('reservas', [
            'id' => $reservaId,
            'estado' => 'activa',
        ]);

        $this->assertDatabaseHas('inventario', [
            'id' => $inventarioId,
            'estado' => 'bloqueado',
        ]);
    }

    public function test_cancelar_reserva_sin_token_responde_401(): void
    {
        $this->deleteJson('/api/reservas/1')
            ->assertUnauthorized();
    }


    public function test_expira_reserva_vencida_y_libera_asiento(): void
    {
        $usuario = $this->crearUsuario('ana@example.com');
        $eventoId = $this->crearEvento();
        $inventarioId = $this->crearItemInventario($eventoId, 250.75);

        // Crear una reserva normalmente.
        $respuesta = $this->withToken(
            $this->tokenDe($usuario)
        )->postJson('/api/reservas', [
            'evento_id' => $eventoId,
            'inventario_ids' => [$inventarioId],
        ]);

        $respuesta->assertCreated();
        $reservaId = $respuesta->json('id');

        // Simular que transcurrieron los cinco minutos.
        DB::table('reservas')
            ->where('id', $reservaId)
            ->update([
                'expira_en' => now()->subMinute(),
            ]);

        DB::table('inventario')
            ->where('id', $inventarioId)
            ->update([
                'bloqueado_hasta' => now()->subMinute(),
            ]);

        // Ejecutar el proceso de expiracion.
        $cantidad = app(
            ExpirarReservasVencidasUseCase::class
        )->execute();

        $this->assertSame(1, $cantidad);

        // La reserva debe quedar vencida.
        $this->assertDatabaseHas('reservas', [
            'id' => $reservaId,
            'estado' => 'vencida',
        ]);

        // El asiento debe quedar disponible.
        $this->assertDatabaseHas('inventario', [
            'id' => $inventarioId,
            'estado' => 'disponible',
            'reserva_id' => null,
            'bloqueado_hasta' => null,
        ]);

        // Ejecutarlo nuevamente no debe procesar otra vez
        // la misma reserva.
        $segundaEjecucion = app(
            ExpirarReservasVencidasUseCase::class
        )->execute();

        $this->assertSame(0, $segundaEjecucion);
    }

    public function test_no_expira_reservas_confirmadas(): void
    {
        $usuario = $this->crearUsuario('ana@example.com');
        $eventoId = $this->crearEvento();
        $inventarioId = $this->crearItemInventario($eventoId, 250.75);

        $respuesta = $this->withToken(
            $this->tokenDe($usuario)
        )->postJson('/api/reservas', [
            'evento_id' => $eventoId,
            'inventario_ids' => [$inventarioId],
        ]);

        $respuesta->assertCreated();
        $reservaId = $respuesta->json('id');

        // Simular que el pago fue confirmado
        // y que el asiento ya fue vendido.
        DB::table('reservas')
            ->where('id', $reservaId)
            ->update([
                'estado' => 'confirmada',
                'expira_en' => now()->subMinute(),
            ]);

        DB::table('inventario')
            ->where('id', $inventarioId)
            ->update([
                'estado' => 'vendido',
                'bloqueado_hasta' => null,
            ]);

        // Ejecutar el proceso de expiracion.
        $cantidad = app(
            ExpirarReservasVencidasUseCase::class
        )->execute();

        $this->assertSame(0, $cantidad);

        // No debe modificar una reserva pagada.
        $this->assertDatabaseHas('reservas', [
            'id' => $reservaId,
            'estado' => 'confirmada',
        ]);

        // Tampoco debe liberar un asiento vendido.
        $this->assertDatabaseHas('inventario', [
            'id' => $inventarioId,
            'estado' => 'vendido',
            'reserva_id' => $reservaId,
        ]);
    }


}
