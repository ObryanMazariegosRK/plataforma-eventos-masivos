<?php

namespace Tests\Feature\ColaVirtual;

use App\Data\ColaVirtual\TurnoRepository;
use App\Domain\Enums\Auth\EstadoUsuario;
use App\Domain\Enums\Auth\RolUsuario;
use App\Domain\Enums\ColaVirtual\EstadoTurno;
use App\Models\Auth\Usuario as UsuarioModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use App\Application\UseCases\ColaVirtual\IngresarColaVirtualUseCase;
use App\Domain\Abstractions\ColaVirtual\ITurnoRepository;

class TurnoRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_asigna_primera_posicion_y_token_unico(): void
    {
        // Crear usuario de prueba.
        $usuario = $this->crearUsuario();

        // Crear evento de prueba.
        $eventoId = $this->crearEvento();

        // Ejecutar nuestro repositorio.
        $repositorio = new TurnoRepository();

        $turno = $repositorio->obtenerOCrearTurno(
            $eventoId,
            $usuario->id
        );

        // Verificar posición y estado.
        $this->assertEquals(1, $turno->getPosicion());

        $this->assertEquals(
            EstadoTurno::ESPERANDO,
            $turno->getEstado()
        );

        // Verificar que el token tenga 64 caracteres.
        $this->assertEquals(64, strlen($turno->getToken()));

        // Comprobar que el turno existe en la BD.
        $this->assertDatabaseHas('cola_virtual', [
            'evento_id' => $eventoId,
            'usuario_id' => $usuario->id,
            'posicion' => 1,
            'estado' => 'esperando',
            'token' => $turno->getToken(),
        ]);

        $this->assertDatabaseCount('cola_virtual', 1);
    }

    public function test_no_duplica_turno_si_usuario_ingresa_dos_veces(): void
    {
        // Crear usuario y evento de prueba.
        $usuario = $this->crearUsuario();
        $eventoId = $this->crearEvento();

        $repositorio = new TurnoRepository();

        // Primer ingreso a la cola.
        $primerTurno = $repositorio->obtenerOCrearTurno(
            $eventoId,
            $usuario->id
        );

        // El mismo usuario intenta ingresar nuevamente.
        $segundoTurno = $repositorio->obtenerOCrearTurno(
            $eventoId,
            $usuario->id
        );

        // Debe conservar el mismo identificador.
        $this->assertEquals(
            $primerTurno->getId(),
            $segundoTurno->getId()
        );

        // Debe conservar su posición original.
        $this->assertEquals(
            $primerTurno->getPosicion(),
            $segundoTurno->getPosicion()
        );

        // Debe conservar el mismo token.
        $this->assertEquals(
            $primerTurno->getToken(),
            $segundoTurno->getToken()
        );

        // Solo debe existir un turno en la base de datos.
        $this->assertDatabaseCount('cola_virtual', 1);
    }

    public function test_rechaza_ingreso_a_evento_inexistente(): void
    {
        // Crear un usuario válido.
        $usuario = $this->crearUsuario();

        // Utilizar un evento que no existe.
        $eventoId = 999999;

        $repositorio = new TurnoRepository();

        // El sistema debe rechazar la solicitud.
        $this->expectException(\DomainException::class);

        $this->expectExceptionMessage(
            'El evento no existe.'
        );

        $repositorio->obtenerOCrearTurno(
            $eventoId,
            $usuario->id
        );
    }

    public function test_asigna_posiciones_y_tokens_diferentes_a_dos_usuarios(): void
    {
        // Crear dos usuarios diferentes.
        $ana = $this->crearUsuario('ana-cola@example.com');
        $carlos = $this->crearUsuario('carlos-cola@example.com');

        // Ambos ingresarán al mismo evento.
        $eventoId = $this->crearEvento();

        $repositorio = new TurnoRepository();

        // Ana entra primero.
        $turnoAna = $repositorio->obtenerOCrearTurno(
            $eventoId,
            $ana->id
        );

        // Carlos entra después.
        $turnoCarlos = $repositorio->obtenerOCrearTurno(
            $eventoId,
            $carlos->id
        );

        // Comprobar las posiciones.
        $this->assertEquals(1, $turnoAna->getPosicion());
        $this->assertEquals(2, $turnoCarlos->getPosicion());

        // Sus tokens deben ser diferentes.
        $this->assertNotEquals(
            $turnoAna->getToken(),
            $turnoCarlos->getToken()
        );

        // Ambos deben comenzar esperando.
        $this->assertEquals(
            EstadoTurno::ESPERANDO,
            $turnoAna->getEstado()
        );

        $this->assertEquals(
            EstadoTurno::ESPERANDO,
            $turnoCarlos->getEstado()
        );

        // Deben existir exactamente dos turnos.
        $this->assertDatabaseCount('cola_virtual', 2);
    }


    private function crearUsuario(string $email = 'ana-cola@example.com'): UsuarioModel
    {
        return UsuarioModel::create([
            'nombre' => 'Ana',
            'apellido' => 'López',
            'email' => $email,
            'password' => Hash::make('Secreto#123'),
            'rol' => RolUsuario::CLIENTE,
            'estado' => EstadoUsuario::ACTIVO,
            'email_verified_at' => now(),
        ]);
    }

    private function crearEvento(): int
    {
        $recintoId = DB::table('recintos')->insertGetId([
            'nombre' => 'Estadio de Pruebas',
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
            'cola_virtual_activa' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }


    private function crearInventarioDisponible(
        int $eventoId,
        int $cantidad = 10
    ): void {
        // Obtener el recinto asociado al evento.
        $recintoId = DB::table('eventos')
            ->where('id', $eventoId)
            ->value('recinto_id');

        // Crear una localidad de pruebas.
        $localidadId = DB::table('localidades')->insertGetId([
            'recinto_id' => $recintoId,
            'nombre' => 'General de Pruebas',
            'tipo' => 'numerada',
            'capacidad' => $cantidad,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Registrar el precio para el evento.
        DB::table('precios')->insert([
            'evento_id' => $eventoId,
            'localidad_id' => $localidadId,
            'precio' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Crear los asientos y sus boletos disponibles.
        for ($numero = 1; $numero <= $cantidad; $numero++) {

            $asientoId = DB::table('asientos')->insertGetId([
                'localidad_id' => $localidadId,
                'fila' => 'A',
                'numero' => $numero,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('inventario')->insert([
                'evento_id' => $eventoId,
                'localidad_id' => $localidadId,
                'asiento_id' => $asientoId,
                'estado' => 'disponible',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function test_permite_ingresar_a_evento_con_cola_virtual_activa(): void
    {
        // Crear usuario y evento de prueba.
        $usuario = $this->crearUsuario();
        $eventoId = $this->crearEvento();

        // Nuestro método auxiliar crea un evento publicado
        // con cola_virtual_activa = true.

        $repositorio = new TurnoRepository();

        $casoDeUso = new IngresarColaVirtualUseCase(
            $repositorio
        );

        // Intentar ingresar a la cola.
        $turno = $casoDeUso->execute(
            $eventoId,
            $usuario->id
        );

        // Verificar que recibió su primera posición.
        $this->assertEquals(1, $turno->getPosicion());

        // Verificar que está esperando.
        $this->assertEquals(
            EstadoTurno::ESPERANDO,
            $turno->getEstado()
        );

        // Verificar que el turno se guardó correctamente.
        $this->assertDatabaseHas('cola_virtual', [
            'evento_id' => $eventoId,
            'usuario_id' => $usuario->id,
            'estado' => 'esperando',
            'posicion' => 1,
        ]);

        $this->assertDatabaseCount('cola_virtual', 1);
    }

    public function test_rechaza_ingreso_si_cola_virtual_esta_desactivada(): void
    {
        // Crear usuario y evento.
        $usuario = $this->crearUsuario();
        $eventoId = $this->crearEvento();

        // Desactivar la Cola Virtual de este evento.
        DB::table('eventos')
            ->where('id', $eventoId)
            ->update([
                'cola_virtual_activa' => false,
            ]);

        $repositorio = new TurnoRepository();

        $casoDeUso = new IngresarColaVirtualUseCase(
            $repositorio
        );

        // Intentar ingresar a una cola desactivada.
        try {
            $casoDeUso->execute(
                $eventoId,
                $usuario->id
            );

            $this->fail('El sistema permitió ingresar a una cola desactivada.');

        } catch (\DomainException $e) {
            $this->assertEquals(
                'La Cola Virtual no está activada para este evento.',
                $e->getMessage()
            );
        }

        // No debe haberse creado ningún turno.
        $this->assertDatabaseCount('cola_virtual', 0);
    }

    public function test_rechaza_ingreso_a_evento_en_borrador(): void
    {
        // Crear usuario y evento de prueba.
        $usuario = $this->crearUsuario();
        $eventoId = $this->crearEvento();

        // Cambiar el estado del evento a borrador.
        DB::table('eventos')
            ->where('id', $eventoId)
            ->update([
                'estado' => 'borrador',
            ]);

        $repositorio = new TurnoRepository();

        $casoDeUso = new IngresarColaVirtualUseCase(
            $repositorio
        );

        // Comprobar que el sistema rechaza el ingreso.
        try {
            $casoDeUso->execute(
                $eventoId,
                $usuario->id
            );

            $this->fail(
                'El sistema permitió ingresar a un evento en borrador.'
            );

        } catch (\DomainException $e) {
            $this->assertEquals(
                'El evento no está disponible para ingresar a la cola.',
                $e->getMessage()
            );
        }

        // No debe haberse creado ningún turno.
        $this->assertDatabaseCount('cola_virtual', 0);
    }

    public function test_rechaza_ingreso_a_cola_virtual_sin_autenticacion(): void
    {
        // Crear un evento de prueba con Cola Virtual activada.
        $eventoId = $this->crearEvento();

        // Intentar ingresar sin proporcionar un token de autenticación.
        $respuesta = $this->postJson('/api/cola-virtual/ingresar', [
            'evento_id' => $eventoId,
        ]);

        // Laravel debe responder HTTP 401.
        $respuesta->assertUnauthorized();

        // No debe registrarse ningún turno.
        $this->assertDatabaseCount('cola_virtual', 0);
    }

    
    public function test_usuario_autenticado_puede_ingresar_a_cola_virtual(): void
    {
        // Crear un usuario y un evento con Cola Virtual activada.
        $usuario = $this->crearUsuario();
        $eventoId = $this->crearEvento();

        // Generar un token de autenticación de Sanctum.
        $token = $usuario->createToken('prueba-cola')->plainTextToken;

        // Solicitar ingreso a la Cola Virtual mediante la API.
        $respuesta = $this->withToken($token)
            ->postJson('/api/cola-virtual/ingresar', [
                'evento_id' => $eventoId,
            ]);

        // Comprobar que la API responde correctamente.
        $respuesta->assertOk()
            ->assertJson([
                'evento_id' => $eventoId,
                'posicion' => 1,
                'estado' => 'esperando',
                'habilitado_hasta' => null,
            ])
            ->assertJsonStructure([
                'id',
                'evento_id',
                'posicion',
                'token',
                'estado',
                'habilitado_hasta',
            ]);

        // Comprobar que el token generado tiene 64 caracteres.
        $this->assertEquals(
            64,
            strlen($respuesta->json('token'))
        );

        // El turno debe pertenecer al usuario autenticado.
        $this->assertDatabaseHas('cola_virtual', [
            'id' => $respuesta->json('id'),
            'evento_id' => $eventoId,
            'usuario_id' => $usuario->id,
            'posicion' => 1,
            'estado' => 'esperando',
            'token' => $respuesta->json('token'),
        ]);

        // Solamente debe existir un turno.
        $this->assertDatabaseCount('cola_virtual', 1);
    }

    public function test_api_no_duplica_turno_al_ingresar_dos_veces(): void
    {
        // Crear usuario y evento de prueba.
        $usuario = $this->crearUsuario();
        $eventoId = $this->crearEvento();

        // Generar token de autenticación.
        $token = $usuario->createToken('prueba-cola')->plainTextToken;

        // Primera solicitud de ingreso.
        $primera = $this->withToken($token)
            ->postJson('/api/cola-virtual/ingresar', [
                'evento_id' => $eventoId,
            ]);

        $primera->assertOk();

        // El mismo usuario vuelve a ingresar.
        $segunda = $this->withToken($token)
            ->postJson('/api/cola-virtual/ingresar', [
                'evento_id' => $eventoId,
            ]);

        $segunda->assertOk();

        // Debe conservar el mismo identificador.
        $this->assertSame(
            $primera->json('id'),
            $segunda->json('id')
        );

        // Debe conservar la misma posición.
        $this->assertSame(
            $primera->json('posicion'),
            $segunda->json('posicion')
        );

        // Debe conservar el mismo token de Cola Virtual.
        $this->assertSame(
            $primera->json('token'),
            $segunda->json('token')
        );

        // La base de datos debe contener solamente un turno.
        $this->assertDatabaseCount('cola_virtual', 1);
    }
    
    public function test_api_rechaza_evento_id_invalido(): void
    {
        // Crear un usuario válido.
        $usuario = $this->crearUsuario();

        // Generar su token de autenticación.
        $token = $usuario->createToken('prueba-cola')->plainTextToken;

        // Intentar ingresar utilizando un ID inválido.
        $respuesta = $this->withToken($token)
            ->postJson('/api/cola-virtual/ingresar', [
                'evento_id' => 0,
            ]);

        // Laravel debe responder HTTP 422.
        $respuesta->assertUnprocessable()
            ->assertJsonValidationErrors(['evento_id']);

        // No debe crearse ningún turno.
        $this->assertDatabaseCount('cola_virtual', 0);
    }

    public function test_procesador_habilita_solo_cinco_turnos_respetando_posiciones(): void
    {
        // Configurar el máximo de compradores simultáneos.
        config()->set('cola_virtual.max_turnos_simultaneos', 5);
        config()->set('cola_virtual.ventana_compra_minutos', 5);

        // Crear un evento publicado con Cola Virtual activa.
        $eventoId = $this->crearEvento();

        // Preparar boletos suficientes para habilitar compradores.
        $this->crearInventarioDisponible($eventoId, 10);

        // Utilizar nuestro caso de uso de ingreso.
        $ingresar = app(
            \App\Application\Abstractions\ColaVirtual\IIngresarColaVirtualUseCase::class
        );

        // Crear seis usuarios e ingresarlos a la cola.
        for ($numero = 1; $numero <= 6; $numero++) {
            $usuario = $this->crearUsuario(
                "usuario-procesador-{$numero}@example.com"
            );

            $ingresar->execute($eventoId, $usuario->id);
        }

        // Todos deben comenzar esperando.
        $this->assertSame(
            6,
            DB::table('cola_virtual')
                ->where('estado', 'esperando')
                ->count()
        );

        // Ejecutar nuestro procesador automático.
        $procesador = app(
            \App\Application\Abstractions\ColaVirtual\IProcesarColaVirtualUseCase::class
        );

        $resultado = $procesador->execute();

        // Debe habilitar cinco usuarios y no expirar ninguno.
        $this->assertSame(5, $resultado['habilitados']);
        $this->assertSame(0, $resultado['expirados']);

        // Deben existir exactamente cinco turnos habilitados.
        $this->assertSame(
            5,
            DB::table('cola_virtual')
                ->where('evento_id', $eventoId)
                ->where('estado', 'habilitado')
                ->count()
        );

        // Las posiciones habilitadas deben ser las primeras cinco.
        $posiciones = DB::table('cola_virtual')
            ->where('evento_id', $eventoId)
            ->where('estado', 'habilitado')
            ->orderBy('posicion')
            ->pluck('posicion')
            ->map(fn ($posicion) => (int) $posicion)
            ->all();

        $this->assertSame([1, 2, 3, 4, 5], $posiciones);

        // El sexto usuario debe continuar esperando.
        $this->assertDatabaseHas('cola_virtual', [
            'evento_id' => $eventoId,
            'posicion' => 6,
            'estado' => 'esperando',
        ]);

        // Los turnos habilitados deben tener fecha de vencimiento.
        $this->assertSame(
            5,
            DB::table('cola_virtual')
                ->where('evento_id', $eventoId)
                ->where('estado', 'habilitado')
                ->whereNotNull('habilitado_hasta')
                ->count()
        );
    }
    
    public function test_procesador_expira_turno_y_habilita_al_siguiente(): void
    {
        config()->set('cola_virtual.max_turnos_simultaneos', 5);
        config()->set('cola_virtual.ventana_compra_minutos', 5);

        // Crear evento con Cola Virtual activa.
        $eventoId = $this->crearEvento();

        // Preparar boletos para los compradores de esta prueba.
        $this->crearInventarioDisponible($eventoId, 10);

        $ingresar = app(
            \App\Application\Abstractions\ColaVirtual\IIngresarColaVirtualUseCase::class
        );

        // Ingresar seis usuarios.
        for ($numero = 1; $numero <= 6; $numero++) {
            $usuario = $this->crearUsuario(
                "usuario-expiracion-{$numero}@example.com"
            );

            $ingresar->execute($eventoId, $usuario->id);
        }

        $procesador = app(
            \App\Application\Abstractions\ColaVirtual\IProcesarColaVirtualUseCase::class
        );

        // Primera ejecución: habilitar posiciones 1 a 5.
        $primerResultado = $procesador->execute();

        $this->assertSame(5, $primerResultado['habilitados']);

        // Simular que venció el tiempo de compra
        // del usuario en la posición 1.
        DB::table('cola_virtual')
            ->where('evento_id', $eventoId)
            ->where('posicion', 1)
            ->update([
                'habilitado_hasta' => now()->subMinute(),
                'updated_at' => now(),
            ]);

        // Segunda ejecución: expirar y avanzar la cola.
        $segundoResultado = $procesador->execute();

        $this->assertSame(1, $segundoResultado['expirados']);
        $this->assertSame(1, $segundoResultado['habilitados']);

        // La posición 1 debe quedar expirada.
        $this->assertDatabaseHas('cola_virtual', [
            'evento_id' => $eventoId,
            'posicion' => 1,
            'estado' => 'expirado',
        ]);

        // La posición 6 debe quedar habilitada.
        $this->assertDatabaseHas('cola_virtual', [
            'evento_id' => $eventoId,
            'posicion' => 6,
            'estado' => 'habilitado',
        ]);

        // Deben seguir existiendo exactamente cinco
        // usuarios habilitados.
        $this->assertSame(
            5,
            DB::table('cola_virtual')
                ->where('evento_id', $eventoId)
                ->where('estado', 'habilitado')
                ->count()
        );

        // El nuevo turno debe tener tiempo de compra.
        $this->assertNotNull(
            DB::table('cola_virtual')
                ->where('evento_id', $eventoId)
                ->where('posicion', 6)
                ->value('habilitado_hasta')
        );
    }

    public function test_procesador_no_supera_limite_al_ejecutarse_varias_veces(): void
    {
        config()->set('cola_virtual.max_turnos_simultaneos', 5);
        config()->set('cola_virtual.ventana_compra_minutos', 5);

        // Crear un evento con Cola Virtual activa.
        $eventoId = $this->crearEvento();

        // Preparar inventario suficiente para probar
        // el límite de compradores simultáneos.
        $this->crearInventarioDisponible($eventoId, 10);

        $ingresar = app(
            \App\Application\Abstractions\ColaVirtual\IIngresarColaVirtualUseCase::class
        );

        // Ingresar ocho usuarios.
        for ($numero = 1; $numero <= 8; $numero++) {
            $usuario = $this->crearUsuario(
                "usuario-limite-{$numero}@example.com"
            );

            $ingresar->execute($eventoId, $usuario->id);
        }

        $procesador = app(
            \App\Application\Abstractions\ColaVirtual\IProcesarColaVirtualUseCase::class
        );

        // Primera ejecución: habilitar cinco turnos.
        $primera = $procesador->execute();

        $this->assertSame(5, $primera['habilitados']);

        // Ejecutar nuevamente sin que expire ningún turno.
        $segunda = $procesador->execute();

        // No debe habilitar usuarios adicionales.
        $this->assertSame(0, $segunda['habilitados']);
        $this->assertSame(0, $segunda['expirados']);

        // Deben existir exactamente cinco turnos habilitados.
        $this->assertSame(
            5,
            DB::table('cola_virtual')
                ->where('evento_id', $eventoId)
                ->where('estado', 'habilitado')
                ->count()
        );

        // Los otros tres usuarios deben continuar esperando.
        $this->assertSame(
            3,
            DB::table('cola_virtual')
                ->where('evento_id', $eventoId)
                ->where('estado', 'esperando')
                ->count()
        );

        // Las posiciones 6, 7 y 8 siguen esperando.
        $posiciones = DB::table('cola_virtual')
            ->where('evento_id', $eventoId)
            ->where('estado', 'esperando')
            ->orderBy('posicion')
            ->pluck('posicion')
            ->map(fn ($posicion) => (int) $posicion)
            ->all();

        $this->assertSame([6, 7, 8], $posiciones);
    }
    
    public function test_consulta_personas_delante_sin_contar_atendidos_ni_expirados(): void
    {
        // Crear un evento con Cola Virtual activa.
        $eventoId = $this->crearEvento();

        $ingresar = app(
            \App\Application\Abstractions\ColaVirtual\IIngresarColaVirtualUseCase::class
        );

        $usuarios = [];

        // Ingresar cuatro usuarios a la cola.
        for ($numero = 1; $numero <= 4; $numero++) {
            $usuario = $this->crearUsuario(
                "usuario-consulta-{$numero}@example.com"
            );

            $usuarios[] = $usuario;

            $ingresar->execute($eventoId, $usuario->id);
        }

        // La posición 2 ya fue atendida.
        DB::table('cola_virtual')
            ->where('evento_id', $eventoId)
            ->where('posicion', 2)
            ->update([
                'estado' => 'atendido',
                'updated_at' => now(),
            ]);

        // La posición 3 ya expiró.
        DB::table('cola_virtual')
            ->where('evento_id', $eventoId)
            ->where('posicion', 3)
            ->update([
                'estado' => 'expirado',
                'updated_at' => now(),
            ]);

        // Consultar el turno del cuarto usuario.
        $consultar = new \App\Application\UseCases\ColaVirtual\ConsultarMiTurnoUseCase();

        $resultado = $consultar->execute(
            $eventoId,
            $usuarios[3]->id
        );

        // Debe encontrar el turno.
        $this->assertNotNull($resultado);

        // Conserva su posición original.
        $this->assertSame(4, $resultado['posicion']);

        // Solamente la posición 1 sigue delante.
        $this->assertSame(1, $resultado['personas_delante']);

        // Continúa esperando.
        $this->assertSame('esperando', $resultado['estado']);

        // Todavía no tiene una ventana de compra.
        $this->assertNull($resultado['habilitado_hasta']);
    }

    public function test_consulta_detecta_turno_vencido_sin_ejecutar_scheduler(): void
    {
        // Crear evento y usuario.
        $eventoId = $this->crearEvento();

        $usuario = $this->crearUsuario(
            'usuario-turno-vencido@example.com'
        );

        // Ingresar normalmente a la Cola Virtual.
        $ingresar = app(
            \App\Application\Abstractions\ColaVirtual\IIngresarColaVirtualUseCase::class
        );

        $ingresar->execute($eventoId, $usuario->id);

        // Simular que el turno fue habilitado,
        // pero su tiempo de compra ya terminó.
        DB::table('cola_virtual')
            ->where('evento_id', $eventoId)
            ->where('usuario_id', $usuario->id)
            ->update([
                'estado' => 'habilitado',
                'habilitado_hasta' => now()->subMinute(),
                'updated_at' => now(),
            ]);

        // Consultar el turno sin ejecutar el procesador.
        $consultar = new \App\Application\UseCases\ColaVirtual\ConsultarMiTurnoUseCase();

        $resultado = $consultar->execute(
            $eventoId,
            $usuario->id
        );

        // El resultado debe existir.
        $this->assertNotNull($resultado);

        // La API debe informar que el turno expiró.
        $this->assertSame('expirado', $resultado['estado']);

        // La posición original debe conservarse.
        $this->assertSame(1, $resultado['posicion']);

        // La consulta no debe modificar la base de datos.
        $this->assertDatabaseHas('cola_virtual', [
            'evento_id' => $eventoId,
            'usuario_id' => $usuario->id,
            'estado' => 'habilitado',
        ]);
    }
    
    public function test_usuario_autenticado_puede_consultar_su_turno_desde_api(): void
    {
        // Crear un evento con Cola Virtual activa.
        $eventoId = $this->crearEvento();

        // Crear dos usuarios diferentes.
        $usuario1 = $this->crearUsuario(
            'consulta-api-1@example.com'
        );

        $usuario2 = $this->crearUsuario(
            'consulta-api-2@example.com'
        );

        // Ingresarlos a la cola en orden.
        $ingresar = app(
            \App\Application\Abstractions\ColaVirtual\IIngresarColaVirtualUseCase::class
        );

        $ingresar->execute($eventoId, $usuario1->id);
        $ingresar->execute($eventoId, $usuario2->id);

        // Autenticar al segundo usuario mediante Sanctum.
        $token = $usuario2->createToken('consulta-cola')->plainTextToken;

        // Consultar su turno mediante nuestra nueva API.
        $respuesta = $this->withToken($token)
            ->getJson("/api/cola-virtual/mi-turno/{$eventoId}");

        // Comprobar los datos devueltos.
        $respuesta->assertOk()
            ->assertJson([
                'evento_id' => $eventoId,
                'posicion' => 2,
                'personas_delante' => 1,
                'estado' => 'esperando',
                'habilitado_hasta' => null,
            ]);

        // La consulta no debe modificar los turnos.
        $this->assertDatabaseCount('cola_virtual', 2);
    }

    public function test_consulta_turno_sin_autenticacion_responde_401(): void
    {
        // Crear un evento con Cola Virtual activa.
        $eventoId = $this->crearEvento();

        // Intentar consultar un turno sin autenticación.
        $respuesta = $this->getJson(
            "/api/cola-virtual/mi-turno/{$eventoId}"
        );

        // Laravel debe rechazar la solicitud.
        $respuesta->assertUnauthorized();

        // La consulta no debe crear ningún turno.
        $this->assertDatabaseCount('cola_virtual', 0);
    }

    public function test_usuario_no_puede_consultar_turno_de_otra_persona(): void
    {
        // Crear un evento con Cola Virtual activa.
        $eventoId = $this->crearEvento();

        // Crear dos usuarios diferentes.
        $ana = $this->crearUsuario(
            'ana-privacidad-cola@example.com'
        );

        $carlos = $this->crearUsuario(
            'carlos-privacidad-cola@example.com'
        );

        // Solamente Ana ingresa a la cola.
        $ingresar = app(
            \App\Application\Abstractions\ColaVirtual\IIngresarColaVirtualUseCase::class
        );

        $turnoAna = $ingresar->execute(
            $eventoId,
            $ana->id
        );

        // Carlos inicia sesión mediante Sanctum.
        $tokenCarlos = $carlos->createToken(
            'prueba-privacidad-cola'
        )->plainTextToken;

        // Carlos consulta el evento donde Ana tiene un turno.
        $respuesta = $this->withToken($tokenCarlos)
            ->getJson("/api/cola-virtual/mi-turno/{$eventoId}");

        // Carlos no tiene turno propio: debe recibir HTTP 404.
        $respuesta->assertNotFound()
            ->assertJson([
                'message' => 'No tienes un turno registrado para este evento.',
            ]);

        // No debe recibir información del turno de Ana.
        $this->assertStringNotContainsString(
            $turnoAna->getToken(),
            $respuesta->getContent()
        );

        // El turno de Ana debe permanecer intacto.
        $this->assertDatabaseHas('cola_virtual', [
            'evento_id' => $eventoId,
            'usuario_id' => $ana->id,
            'posicion' => 1,
            'estado' => 'esperando',
        ]);

        // No debe crearse otro turno.
        $this->assertDatabaseCount('cola_virtual', 1);
    }

    public function test_api_informa_turno_vencido_sin_esperar_scheduler(): void
    {
        // Crear evento y usuario.
        $eventoId = $this->crearEvento();

        $usuario = $this->crearUsuario(
            'consulta-api-vencido@example.com'
        );

        // Ingresar al usuario a la Cola Virtual.
        $ingresar = app(
            \App\Application\Abstractions\ColaVirtual\IIngresarColaVirtualUseCase::class
        );

        $ingresar->execute($eventoId, $usuario->id);

        // Simular que su ventana de compra ya venció.
        DB::table('cola_virtual')
            ->where('evento_id', $eventoId)
            ->where('usuario_id', $usuario->id)
            ->update([
                'estado' => 'habilitado',
                'habilitado_hasta' => now()->subMinute(),
                'updated_at' => now(),
            ]);

        // Autenticar al usuario mediante Sanctum.
        $token = $usuario->createToken(
            'consulta-turno-vencido'
        )->plainTextToken;

        // Consultar su turno desde la API.
        $respuesta = $this->withToken($token)
            ->getJson("/api/cola-virtual/mi-turno/{$eventoId}");

        // La API debe informar que el turno expiró.
        $respuesta->assertOk()
            ->assertJson([
                'evento_id' => $eventoId,
                'posicion' => 1,
                'personas_delante' => 0,
                'estado' => 'expirado',
            ]);

        // Avance inmediato: consultar el turno también hace avanzar la fila
        // (antes solo lo hacía el Scheduler cada minuto), así que la ventana
        // vencida ya queda marcada como expirada en la base de datos.
        $this->assertDatabaseHas('cola_virtual', [
            'evento_id' => $eventoId,
            'usuario_id' => $usuario->id,
            'estado' => 'expirado',
        ]);
    }

    public function test_procesador_registra_notificacion_al_habilitar_turno(): void
    {
        // Evitar que se envíen correos reales durante la prueba.
        \Illuminate\Support\Facades\Bus::fake([
            \App\Jobs\Notificaciones\EnviarNotificacionCorreoJob::class,
        ]);

        config()->set('cola_virtual.max_turnos_simultaneos', 5);
        config()->set('cola_virtual.ventana_compra_minutos', 5);

        // Crear evento y usuario de prueba.
        $eventoId = $this->crearEvento();

        // Preparar boletos disponibles para habilitar al usuario.
        $this->crearInventarioDisponible($eventoId, 10);

        $usuario = $this->crearUsuario(
            'notificacion-cola@example.com'
        );

        // Ingresar el usuario a la Cola Virtual.
        $ingresar = app(
            \App\Application\Abstractions\ColaVirtual\IIngresarColaVirtualUseCase::class
        );

        $turno = $ingresar->execute(
            $eventoId,
            $usuario->id
        );

        // Inicialmente no existen notificaciones.
        $this->assertDatabaseCount('notificaciones', 0);

        // Ejecutar el procesador de Cola Virtual.
        $procesador = app(
            \App\Application\Abstractions\ColaVirtual\IProcesarColaVirtualUseCase::class
        );

        $resultado = $procesador->execute();

        // El turno debe quedar habilitado.
        $this->assertSame(1, $resultado['habilitados']);

        $this->assertDatabaseHas('cola_virtual', [
            'id' => $turno->getId(),
            'estado' => 'habilitado',
        ]);

        // Debe existir una notificación para el usuario.
        $this->assertDatabaseHas('notificaciones', [
            'usuario_id' => $usuario->id,
            'canal' => 'email',
            'estado' => 'pendiente',
            'clave_idempotencia' =>
                'cola_virtual:habilitado:turno:' . $turno->getId(),
        ]);

        // Una segunda ejecución no debe crear otro aviso.
        $procesador->execute();

        $this->assertDatabaseCount('notificaciones', 1);
        
        // Debe programarse exactamente un trabajo de correo.
        // Bus::fake() impide que el correo se envíe realmente.
        \Illuminate\Support\Facades\Bus::assertDispatched(
            \App\Jobs\Notificaciones\EnviarNotificacionCorreoJob::class,
            1
        );
    }

    public function test_notifica_a_usuarios_cuyo_turno_esta_proximo(): void
    {
        // No ejecutar envíos reales durante la prueba.
        \Illuminate\Support\Facades\Bus::fake([
            \App\Jobs\Notificaciones\EnviarNotificacionCorreoJob::class,
        ]);

        // Solo un usuario puede comprar simultáneamente.
        config()->set('cola_virtual.max_turnos_simultaneos', 1);
        config()->set('cola_virtual.ventana_compra_minutos', 5);
        config()->set('cola_virtual.aviso_proximo_personas', 2);

        $eventoId = $this->crearEvento();

        // Preparar inventario para habilitar compradores
        // y comprobar los avisos de proximidad.
        $this->crearInventarioDisponible($eventoId, 10);

        $ingresar = app(
            \App\Application\Abstractions\ColaVirtual\IIngresarColaVirtualUseCase::class
        );

        $turnos = [];

        // Crear cuatro usuarios e ingresarlos a la cola.
        for ($numero = 1; $numero <= 4; $numero++) {
            $usuario = $this->crearUsuario(
                "usuario-aviso-proximo-{$numero}@example.com"
            );

            $turnos[$numero] = $ingresar->execute(
                $eventoId,
                $usuario->id
            );
        }

        // Procesar la cola.
        $procesador = app(
            \App\Application\Abstractions\ColaVirtual\IProcesarColaVirtualUseCase::class
        );

        $procesador->execute();

        // El primero debe quedar habilitado.
        $this->assertDatabaseHas('cola_virtual', [
            'id' => $turnos[1]->getId(),
            'estado' => 'habilitado',
        ]);

        // Las posiciones 2 y 3 deben recibir aviso próximo.
        foreach ([2, 3] as $numero) {
            $this->assertDatabaseHas('notificaciones', [
                'usuario_id' => $turnos[$numero]->getUsuarioId(),
                'clave_idempotencia' =>
                    'cola_virtual:proximo:turno:'
                    . $turnos[$numero]->getId(),
            ]);
        }

        // La posición 4 todavía no debe recibir el aviso.
        $this->assertDatabaseMissing('notificaciones', [
            'clave_idempotencia' =>
                'cola_virtual:proximo:turno:'
                . $turnos[4]->getId(),
        ]);

        // Una segunda ejecución no debe duplicar notificaciones.
        $procesador->execute();

        // Una notificación de habilitación y dos de proximidad.
        $this->assertDatabaseCount('notificaciones', 3);
    }

    public function test_notifica_turnos_proximos_aunque_cola_este_llena(): void
    {
        // Simular los trabajos de correo.
        \Illuminate\Support\Facades\Bus::fake([
            \App\Jobs\Notificaciones\EnviarNotificacionCorreoJob::class,
        ]);

        config()->set('cola_virtual.max_turnos_simultaneos', 1);
        config()->set('cola_virtual.aviso_proximo_personas', 2);

        $eventoId = $this->crearEvento();

        // Preparar inventario suficiente para que la cola
        // se llene por el límite de compradores, no por falta de boletos.
        $this->crearInventarioDisponible($eventoId, 10);

        $ingresar = app(
            \App\Application\Abstractions\ColaVirtual\IIngresarColaVirtualUseCase::class
        );

        $procesador = app(
            \App\Application\Abstractions\ColaVirtual\IProcesarColaVirtualUseCase::class
        );

        // El primer usuario ingresa y ocupa el único espacio.
        $usuario1 = $this->crearUsuario(
            'cola-llena-1@example.com'
        );

        $ingresar->execute($eventoId, $usuario1->id);
        $procesador->execute();

        // Otros dos usuarios ingresan posteriormente.
        $usuario2 = $this->crearUsuario(
            'cola-llena-2@example.com'
        );

        $usuario3 = $this->crearUsuario(
            'cola-llena-3@example.com'
        );

        $turno2 = $ingresar->execute($eventoId, $usuario2->id);
        $turno3 = $ingresar->execute($eventoId, $usuario3->id);

        // La cola está llena, pero el procesador debe
        // seguir revisando los avisos de proximidad.
        $resultado = $procesador->execute();

        $this->assertSame(0, $resultado['habilitados']);

        // Ambos usuarios deben recibir su aviso próximo.
        foreach ([$turno2, $turno3] as $turno) {
            $this->assertDatabaseHas('notificaciones', [
                'usuario_id' => $turno->getUsuarioId(),
                'clave_idempotencia' =>
                    'cola_virtual:proximo:turno:' . $turno->getId(),
            ]);
        }

        // Un aviso de habilitación y dos de proximidad.
        $this->assertDatabaseCount('notificaciones', 3);
    }

    public function test_procesador_notifica_cuando_turno_expira(): void
    {
        // Simular los trabajos de correo.
        \Illuminate\Support\Facades\Bus::fake([
            \App\Jobs\Notificaciones\EnviarNotificacionCorreoJob::class,
        ]);

        config()->set('cola_virtual.max_turnos_simultaneos', 5);
        config()->set('cola_virtual.ventana_compra_minutos', 5);

        // Crear evento y usuario.
        $eventoId = $this->crearEvento();

        // Preparar boletos disponibles para habilitar el turno
        // antes de comprobar su expiración.
        $this->crearInventarioDisponible($eventoId, 10);

        $usuario = $this->crearUsuario(
            'usuario-aviso-expirado@example.com'
        );

        // Ingresar a la Cola Virtual.
        $ingresar = app(
            \App\Application\Abstractions\ColaVirtual\IIngresarColaVirtualUseCase::class
        );

        $turno = $ingresar->execute(
            $eventoId,
            $usuario->id
        );

        $procesador = app(
            \App\Application\Abstractions\ColaVirtual\IProcesarColaVirtualUseCase::class
        );

        // Primera ejecución: habilitar el turno.
        $procesador->execute();

        $this->assertDatabaseHas('cola_virtual', [
            'id' => $turno->getId(),
            'estado' => 'habilitado',
        ]);

        // Simular que vencieron los cinco minutos.
        DB::table('cola_virtual')
            ->where('id', $turno->getId())
            ->update([
                'habilitado_hasta' => now()->subMinute(),
                'updated_at' => now(),
            ]);

        // Segunda ejecución: procesar el vencimiento.
        $resultado = $procesador->execute();

        $this->assertSame(1, $resultado['expirados']);

        // El turno debe cambiar a expirado.
        $this->assertDatabaseHas('cola_virtual', [
            'id' => $turno->getId(),
            'estado' => 'expirado',
        ]);

        // Debe registrarse una notificación de expiración.
        $this->assertDatabaseHas('notificaciones', [
            'usuario_id' => $usuario->id,
            'canal' => 'email',
            'estado' => 'pendiente',
            'clave_idempotencia' =>
                'cola_virtual:expirado:turno:' . $turno->getId(),
        ]);

        // Una tercera ejecución no debe duplicar el aviso.
        $procesador->execute();

        // Una notificación de habilitación y otra de expiración.
        $this->assertDatabaseCount('notificaciones', 2);
    }

    public function test_notificacion_de_cola_virtual_puede_enviarse(): void
    {
        // Simular el envío de correos y el despacho de trabajos.
        \Illuminate\Support\Facades\Mail::fake();

        \Illuminate\Support\Facades\Bus::fake([
            \App\Jobs\Notificaciones\EnviarNotificacionCorreoJob::class,
        ]);

        config()->set('cola_virtual.max_turnos_simultaneos', 1);

        $eventoId = $this->crearEvento();

        // Preparar boletos disponibles para permitir la habilitación
        // y generar la notificación que después procesará el Job.

        $this->crearInventarioDisponible($eventoId, 10);
        $usuario = $this->crearUsuario(
            'integracion-cola-notificaciones@example.com'
        );

        // 1. Ingresar a la Cola Virtual.
        $ingresar = app(
            \App\Application\Abstractions\ColaVirtual\IIngresarColaVirtualUseCase::class
        );

        $turno = $ingresar->execute($eventoId, $usuario->id);

        // 2. Habilitar el turno y generar su notificación.
        $procesador = app(
            \App\Application\Abstractions\ColaVirtual\IProcesarColaVirtualUseCase::class
        );

        $procesador->execute();

        $clave = 'cola_virtual:habilitado:turno:' . $turno->getId();

        $registro = \Illuminate\Support\Facades\DB::table('notificaciones')
            ->where('clave_idempotencia', $clave)
            ->first();

        $this->assertNotNull($registro);

        $this->assertSame('pendiente', $registro->estado);

        // 3. Ejecutar el Job sobre la notificación generada.
        // No se ejecuta Redis ni se envía un correo real.
        $job = new \App\Jobs\Notificaciones\EnviarNotificacionCorreoJob(
            (int) $registro->id
        );

        $job->handle(
            app(\App\Domain\Abstractions\Notificaciones\INotificacionRepository::class)
        );

        // 4. Comprobar que se procesó correctamente.
        $this->assertDatabaseHas('notificaciones', [
            'id' => $registro->id,
            'estado' => 'enviada',
        ]);

        \Illuminate\Support\Facades\Mail::assertSent(
            \App\Infrastructure\Notificaciones\Mail\NotificacionCorreo::class,
            1
        );
    }

    public function test_no_habilita_turnos_si_todos_los_boletos_estan_vendidos(): void
    {
        // No ejecutar trabajos de correo reales.
        \Illuminate\Support\Facades\Bus::fake([
            \App\Jobs\Notificaciones\EnviarNotificacionCorreoJob::class,
        ]);

        config()->set('cola_virtual.max_turnos_simultaneos', 5);

        $eventoId = $this->crearEvento();

        // Obtener el recinto del evento.
        $recintoId = DB::table('eventos')
            ->where('id', $eventoId)
            ->value('recinto_id');

        // Crear una localidad.
        $localidadId = DB::table('localidades')->insertGetId([
            'recinto_id' => $recintoId,
            'nombre' => 'General',
            'tipo' => 'numerada',
            'capacidad' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Crear un asiento.
        $asientoId = DB::table('asientos')->insertGetId([
            'localidad_id' => $localidadId,
            'fila' => 'A',
            'numero' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Registrar su precio.
        DB::table('precios')->insert([
            'evento_id' => $eventoId,
            'localidad_id' => $localidadId,
            'precio' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // El único boleto del evento ya está vendido.
        DB::table('inventario')->insert([
            'evento_id' => $eventoId,
            'localidad_id' => $localidadId,
            'asiento_id' => $asientoId,
            'estado' => 'vendido',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Crear un usuario que ingresa a la cola.
        $usuario = $this->crearUsuario(
            'agotado-cola@example.com'
        );

        $ingresar = app(
            \App\Application\Abstractions\ColaVirtual\IIngresarColaVirtualUseCase::class
        );

        $turno = $ingresar->execute($eventoId, $usuario->id);

        // Ejecutar el procesador de Cola Virtual.
        $procesador = app(
            \App\Application\Abstractions\ColaVirtual\IProcesarColaVirtualUseCase::class
        );

        $resultado = $procesador->execute();

        // No debe habilitar compradores si todos los boletos están vendidos.
        $this->assertSame(0, $resultado['habilitados']);

        // El turno no debe recibir autorización de compra.
        $this->assertDatabaseHas('cola_virtual', [
            'id' => $turno->getId(),
            'estado' => 'esperando',
        ]);
    }

    public function test_reanuda_cola_cuando_se_liberan_boletos(): void
    {
        // No ejecutar trabajos de correo reales.
        \Illuminate\Support\Facades\Bus::fake([
            \App\Jobs\Notificaciones\EnviarNotificacionCorreoJob::class,
        ]);

        config()->set('cola_virtual.max_turnos_simultaneos', 5);

        $eventoId = $this->crearEvento();

        // Obtener el recinto del evento.
        $recintoId = DB::table('eventos')
            ->where('id', $eventoId)
            ->value('recinto_id');

        // Crear una localidad.
        $localidadId = DB::table('localidades')->insertGetId([
            'recinto_id' => $recintoId,
            'nombre' => 'General',
            'tipo' => 'numerada',
            'capacidad' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Crear un asiento.
        $asientoId = DB::table('asientos')->insertGetId([
            'localidad_id' => $localidadId,
            'fila' => 'A',
            'numero' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Registrar su precio.
        DB::table('precios')->insert([
            'evento_id' => $eventoId,
            'localidad_id' => $localidadId,
            'precio' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // El único boleto del evento ya está vendido.
        DB::table('inventario')->insert([
            'evento_id' => $eventoId,
            'localidad_id' => $localidadId,
            'asiento_id' => $asientoId,
            'estado' => 'bloqueado',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Crear un usuario que ingresa a la cola.
       $usuario = $this->crearUsuario(
            'reanudar-cola@example.com'
        );

        $ingresar = app(
            \App\Application\Abstractions\ColaVirtual\IIngresarColaVirtualUseCase::class
        );

        $turno = $ingresar->execute($eventoId, $usuario->id);

        // Ejecutar el procesador de Cola Virtual.
        $procesador = app(
            \App\Application\Abstractions\ColaVirtual\IProcesarColaVirtualUseCase::class
        );

        $resultado = $procesador->execute();

        // No debe habilitar compradores si todos los boletos están vendidos.
        $this->assertSame(0, $resultado['habilitados']);

        // El turno no debe recibir autorización de compra.
        $this->assertDatabaseHas('cola_virtual', [
            'id' => $turno->getId(),
            'estado' => 'esperando',
        ]);

        // Agregar otros dos usuarios a la fila.
        // Ahora habrá tres esperando por un único boleto.
        $usuario2 = $this->crearUsuario(
            'reanudar-cola-2@example.com'
        );

        $usuario3 = $this->crearUsuario(
            'reanudar-cola-3@example.com'
        );

        $turno2 = $ingresar->execute($eventoId, $usuario2->id);
        $turno3 = $ingresar->execute($eventoId, $usuario3->id);


            // Simular que una reserva venció y liberó el boleto.
        DB::table('inventario')
            ->where('evento_id', $eventoId)
            ->where('estado', 'bloqueado')
            ->update([
                'estado' => 'disponible',
                'bloqueado_hasta' => null,
                'updated_at' => now(),
            ]);

        // Ejecutar nuevamente el procesador.
        $resultadoReanudacion = $procesador->execute();

        // Ahora debe habilitar al usuario que estaba esperando.
        $this->assertSame(
            1,
            $resultadoReanudacion['habilitados']
        );

        $this->assertDatabaseHas('cola_virtual', [
            'id' => $turno->getId(),
            'estado' => 'habilitado',
        ]);

        // El segundo usuario debe continuar esperando.
        $this->assertDatabaseHas('cola_virtual', [
            'id' => $turno2->getId(),
            'estado' => 'esperando',
        ]);

        // El tercer usuario también debe continuar esperando.
        $this->assertDatabaseHas('cola_virtual', [
            'id' => $turno3->getId(),
            'estado' => 'esperando',
        ]);

    }

    public function test_usuario_expirado_reingresa_al_final_de_la_cola(): void
    {
        $eventoId = $this->crearEvento();

        $usuario1 = $this->crearUsuario(
            'reingreso-usuario-1@example.com'
        );

        $usuario2 = $this->crearUsuario(
            'reingreso-usuario-2@example.com'
        );

        $ingresar = app(
            \App\Application\Abstractions\ColaVirtual\IIngresarColaVirtualUseCase::class
        );

        // Primer usuario recibe la posición 1.
        $turnoInicial = $ingresar->execute(
            $eventoId,
            $usuario1->id
        );

        $tokenOriginal = DB::table('cola_virtual')
            ->where('id', $turnoInicial->getId())
            ->value('token');

        // Otro usuario ingresa y recibe la posición 2.
        $turnoOtroUsuario = $ingresar->execute(
            $eventoId,
            $usuario2->id
        );

        $this->assertSame(1, $turnoInicial->getPosicion());
        $this->assertSame(2, $turnoOtroUsuario->getPosicion());

        // Simular la expiración del primer turno.
        DB::table('cola_virtual')
            ->where('id', $turnoInicial->getId())
            ->update([
                'estado' => 'expirado',
                'habilitado_hasta' => now()->subMinute(),
                'updated_at' => now(),
            ]);

        // El usuario solicita su segunda oportunidad.
        $turnoReingreso = $ingresar->execute(
            $eventoId,
            $usuario1->id
        );

        $tokenNuevo = DB::table('cola_virtual')
            ->where('id', $turnoInicial->getId())
            ->value('token');

        // Conserva el registro, pero recibe una nueva posición.
        $this->assertSame(
            $turnoInicial->getId(),
            $turnoReingreso->getId()
        );

        $this->assertSame(3, $turnoReingreso->getPosicion());

        $this->assertNotSame($tokenOriginal, $tokenNuevo);

        $this->assertDatabaseHas('cola_virtual', [
            'id' => $turnoInicial->getId(),
            'usuario_id' => $usuario1->id,
            'posicion' => 3,
            'estado' => 'esperando',
        ]);

        // No se crean dos turnos simultáneos para el usuario.
        $this->assertDatabaseCount('cola_virtual', 2);
    }

    public function test_rechaza_tercer_ingreso_despues_de_dos_expiraciones(): void
    {
        $eventoId = $this->crearEvento();

        $usuario = $this->crearUsuario(
            'limite-reingreso-cola@example.com'
        );

        $ingresar = app(
            \App\Application\Abstractions\ColaVirtual\IIngresarColaVirtualUseCase::class
        );

        // Primera oportunidad.
        $primerTurno = $ingresar->execute(
            $eventoId,
            $usuario->id
        );

        // Simular la primera expiración.
        DB::table('cola_virtual')
            ->where('id', $primerTurno->getId())
            ->update([
                'estado' => 'expirado',
                'habilitado_hasta' => now()->subMinute(),
                'updated_at' => now(),
            ]);

        // Solicitar la segunda oportunidad.
        $segundoTurno = $ingresar->execute(
            $eventoId,
            $usuario->id
        );

        // Confirmar que está en el segundo intento.
        $this->assertDatabaseHas('cola_virtual', [
            'id' => $segundoTurno->getId(),
            'usuario_id' => $usuario->id,
            'intento' => 2,
            'estado' => 'esperando',
        ]);

        // El primer intento debe conservarse en el historial.
        $this->assertDatabaseHas('cola_virtual_intentos', [
            'turno_id' => $primerTurno->getId(),
            'intento' => 1,
            'estado' => 'expirado',
            'posicion' => 1,
        ]);

        $this->assertDatabaseCount('cola_virtual_intentos', 1);

        // Simular que también perdió su segunda oportunidad.

        // El segundo turno venció, pero el Scheduler todavía
        // no lo ha cambiado a expirado.
        DB::table('cola_virtual')
            ->where('id', $segundoTurno->getId())
            ->update([
                'estado' => 'habilitado',
                'habilitado_hasta' => now()->subMinute(),
                'updated_at' => now(),
            ]);

        // No debe permitir un tercer ingreso.
        $this->expectException(\DomainException::class);

        $this->expectExceptionMessage(
            'Ya utilizaste las dos oportunidades para este evento.'
        );

        $ingresar->execute($eventoId, $usuario->id);
    }

    public function test_permite_reingreso_si_turno_vencio_sin_scheduler(): void
    {
        $eventoId = $this->crearEvento();

        $usuario1 = $this->crearUsuario(
            'vencimiento-sin-scheduler-1@example.com'
        );

        $usuario2 = $this->crearUsuario(
            'vencimiento-sin-scheduler-2@example.com'
        );

        $ingresar = app(
            \App\Application\Abstractions\ColaVirtual\IIngresarColaVirtualUseCase::class
        );

        // Primer usuario ingresa en posición 1.
        $primerTurno = $ingresar->execute(
            $eventoId,
            $usuario1->id
        );

        // Simular que fue habilitado, pero su tiempo ya terminó.
        // No ejecutaremos el Scheduler.
        DB::table('cola_virtual')
            ->where('id', $primerTurno->getId())
            ->update([
                'estado' => 'habilitado',
                'habilitado_hasta' => now()->subMinute(),
                'updated_at' => now(),
            ]);

        // Otro usuario ocupa la posición 2.
        $ingresar->execute($eventoId, $usuario2->id);

        // El primer usuario solicita otra oportunidad.
        $nuevoTurno = $ingresar->execute(
            $eventoId,
            $usuario1->id
        );

        // Debe volver al final de la cola.
        $this->assertSame(3, $nuevoTurno->getPosicion());

        // Debe quedar esperando en su segundo intento.
        $this->assertDatabaseHas('cola_virtual', [
            'id' => $primerTurno->getId(),
            'estado' => 'esperando',
            'intento' => 2,
            'posicion' => 3,
            'habilitado_hasta' => null,
        ]);

        // El intento anterior debe conservarse como expirado.
        $this->assertDatabaseHas('cola_virtual_intentos', [
            'turno_id' => $primerTurno->getId(),
            'intento' => 1,
            'estado' => 'expirado',
            'posicion' => 1,
        ]);
    }

    public function test_segundo_intento_genera_nueva_notificacion_de_habilitacion(): void
    {
        // Simular trabajos de correo.
        \Illuminate\Support\Facades\Bus::fake([
            \App\Jobs\Notificaciones\EnviarNotificacionCorreoJob::class,
        ]);

        config()->set('cola_virtual.max_turnos_simultaneos', 1);
        config()->set('cola_virtual.aviso_proximo_personas', 0);

        $eventoId = $this->crearEvento();

        // Preparar inventario suficiente para las dos oportunidades.
        $this->crearInventarioDisponible($eventoId, 10);

        $usuario = $this->crearUsuario(
            'segundo-intento-notificacion@example.com'
        );

        $ingresar = app(
            \App\Application\Abstractions\ColaVirtual\IIngresarColaVirtualUseCase::class
        );

        $procesador = app(
            \App\Application\Abstractions\ColaVirtual\IProcesarColaVirtualUseCase::class
        );

        // Primera oportunidad.
        $primerTurno = $ingresar->execute($eventoId, $usuario->id);
        $procesador->execute();

        // Comprobar el primer aviso.
        $this->assertDatabaseHas('notificaciones', [
            'clave_idempotencia' =>
                'cola_virtual:habilitado:turno:' . $primerTurno->getId(),
        ]);

        // Simular la primera expiración.
        DB::table('cola_virtual')
            ->where('id', $primerTurno->getId())
            ->update([
                'estado' => 'expirado',
                'habilitado_hasta' => now()->subMinute(),
                'updated_at' => now(),
            ]);

        // Obtener la segunda oportunidad.
        $segundoTurno = $ingresar->execute(
            $eventoId,
            $usuario->id
        );

        $this->assertSame(
            $primerTurno->getId(),
            $segundoTurno->getId()
        );

        // Habilitar el segundo intento.
        $procesador->execute();

        // Debe existir una notificación propia del intento 2.
        $this->assertDatabaseHas('notificaciones', [
            'usuario_id' => $usuario->id,
            'clave_idempotencia' =>
                'cola_virtual:habilitado:turno:'
                . $segundoTurno->getId()
                . ':intento:2',
        ]);

        // Primera habilitación, primera expiración
        // y segunda habilitación.
        $this->assertDatabaseCount('notificaciones', 3);

        // Ejecutar nuevamente no debe duplicarlas.
        $procesador->execute();

        $this->assertDatabaseCount('notificaciones', 3);
    }

    public function test_segundo_intento_genera_nueva_notificacion_de_expiracion(): void
    {
        // Simular los trabajos de correo.
        \Illuminate\Support\Facades\Bus::fake([
            \App\Jobs\Notificaciones\EnviarNotificacionCorreoJob::class,
        ]);

        config()->set('cola_virtual.max_turnos_simultaneos', 1);
        config()->set('cola_virtual.aviso_proximo_personas', 0);

        $eventoId = $this->crearEvento();

        // Preparar inventario suficiente para las dos oportunidades.
        $this->crearInventarioDisponible($eventoId, 10);

        $usuario = $this->crearUsuario(
            'segundo-intento-expiracion@example.com'
        );

        $ingresar = app(
            \App\Application\Abstractions\ColaVirtual\IIngresarColaVirtualUseCase::class
        );

        $procesador = app(
            \App\Application\Abstractions\ColaVirtual\IProcesarColaVirtualUseCase::class
        );

        // Primera oportunidad.
        $primerTurno = $ingresar->execute($eventoId, $usuario->id);
        $procesador->execute();

        // Simular el vencimiento.
        DB::table('cola_virtual')
            ->where('id', $primerTurno->getId())
            ->update([
                'habilitado_hasta' => now()->subMinute(),
                'updated_at' => now(),
            ]);

        // Expirar el primer intento y registrar su aviso.
        $procesador->execute();

        $clavePrimerIntento = 'cola_virtual:expirado:turno:'
            . $primerTurno->getId();

        $this->assertDatabaseHas('notificaciones', [
            'clave_idempotencia' => $clavePrimerIntento,
        ]);

        // Solicitar y habilitar la segunda oportunidad.
        $segundoTurno = $ingresar->execute(
            $eventoId,
            $usuario->id
        );

        $procesador->execute();

        $this->assertDatabaseHas('cola_virtual', [
            'id' => $segundoTurno->getId(),
            'intento' => 2,
            'estado' => 'habilitado',
        ]);

        // Simular que también vence su segunda oportunidad.
        DB::table('cola_virtual')
            ->where('id', $segundoTurno->getId())
            ->update([
                'habilitado_hasta' => now()->subMinute(),
                'updated_at' => now(),
            ]);

        $procesador->execute();

        // La segunda expiración debe tener su propio aviso.
        $claveSegundoIntento = 'cola_virtual:expirado:turno:'
            . $segundoTurno->getId()
            . ':intento:2';

        $this->assertDatabaseHas('notificaciones', [
            'usuario_id' => $usuario->id,
            'clave_idempotencia' => $claveSegundoIntento,
        ]);

        // Dos habilitaciones y dos expiraciones.
        $this->assertDatabaseCount('notificaciones', 4);

        // Ejecutar nuevamente no debe duplicar los avisos.
        $procesador->execute();

        $this->assertDatabaseCount('notificaciones', 4);
    }

    public function test_segundo_intento_recibe_nuevo_aviso_de_proximidad(): void
    {
        // Simular los trabajos de correo.
        \Illuminate\Support\Facades\Bus::fake([
            \App\Jobs\Notificaciones\EnviarNotificacionCorreoJob::class,
        ]);

        config()->set('cola_virtual.max_turnos_simultaneos', 1);
        config()->set('cola_virtual.aviso_proximo_personas', 1);

        $eventoId = $this->crearEvento();

        // Preparar inventario suficiente para las dos oportunidades.
        $this->crearInventarioDisponible($eventoId, 10);

        $usuarioA = $this->crearUsuario('proximidad-a@example.com');
        $usuarioB = $this->crearUsuario('proximidad-b@example.com');

        $ingresar = app(
            \App\Application\Abstractions\ColaVirtual\IIngresarColaVirtualUseCase::class
        );

        $procesador = app(
            \App\Application\Abstractions\ColaVirtual\IProcesarColaVirtualUseCase::class
        );

        // A recibe posición 1 y B recibe posición 2.
        $turnoA = $ingresar->execute($eventoId, $usuarioA->id);
        $turnoB = $ingresar->execute($eventoId, $usuarioB->id);

        // Se habilita A y B recibe su primer aviso de proximidad.
        $procesador->execute();

        $claveOriginal = 'cola_virtual:proximo:turno:'
            . $turnoB->getId();

        $this->assertDatabaseHas('notificaciones', [
            'usuario_id' => $usuarioB->id,
            'clave_idempotencia' => $claveOriginal,
        ]);

        // Expirar el turno A para que avance B.
        DB::table('cola_virtual')
            ->where('id', $turnoA->getId())
            ->update([
                'habilitado_hasta' => now()->subMinute(),
                'updated_at' => now(),
            ]);

        $procesador->execute();

        // B ya puede comprar.
        $this->assertDatabaseHas('cola_virtual', [
            'id' => $turnoB->getId(),
            'estado' => 'habilitado',
        ]);

        // C ingresa después y queda esperando.
        $usuarioC = $this->crearUsuario('proximidad-c@example.com');

        $ingresar->execute($eventoId, $usuarioC->id);

        // Expirar a B para habilitar a C.
        DB::table('cola_virtual')
            ->where('id', $turnoB->getId())
            ->update([
                'habilitado_hasta' => now()->subMinute(),
                'updated_at' => now(),
            ]);

        $procesador->execute();

        // B solicita su segunda oportunidad al final de la fila.
        $reingreso = $ingresar->execute($eventoId, $usuarioB->id);

        $this->assertSame(4, $reingreso->getPosicion());

        // C sigue habilitado; B tiene una persona delante.
        $procesador->execute();

        // B debe recibir un aviso nuevo por su segundo intento.
        $claveSegundaOportunidad = $claveOriginal . ':intento:2';

        $this->assertDatabaseHas('notificaciones', [
            'usuario_id' => $usuarioB->id,
            'clave_idempotencia' => $claveSegundaOportunidad,
        ]);

        // Ejecutar nuevamente no debe duplicar los dos avisos.
        $procesador->execute();

        $this->assertSame(
            2,
            DB::table('notificaciones')
                ->where('usuario_id', $usuarioB->id)
                ->where(
                    'clave_idempotencia',
                    'like',
                    $claveOriginal . '%'
                )
                ->count()
        );
    }

    public function test_reingreso_sin_scheduler_registra_aviso_de_expiracion(): void
    {
        // Simular los trabajos de correo.
        \Illuminate\Support\Facades\Bus::fake([
            \App\Jobs\Notificaciones\EnviarNotificacionCorreoJob::class,
        ]);

        config()->set('cola_virtual.max_turnos_simultaneos', 1);
        config()->set('cola_virtual.aviso_proximo_personas', 0);

        $eventoId = $this->crearEvento();

        $usuario = $this->crearUsuario(
            'reingreso-sin-aviso@example.com'
        );

        $ingresar = app(
            \App\Application\Abstractions\ColaVirtual\IIngresarColaVirtualUseCase::class
        );

        $procesador = app(
            \App\Application\Abstractions\ColaVirtual\IProcesarColaVirtualUseCase::class
        );

        // Crear y habilitar el primer turno.
        $turno = $ingresar->execute($eventoId, $usuario->id);

        $procesador->execute();

        // Simular que venció sin ejecutar nuevamente el procesador.
        DB::table('cola_virtual')
            ->where('id', $turno->getId())
            ->update([
                'estado' => 'habilitado',
                'habilitado_hasta' => now()->subMinute(),
                'updated_at' => now(),
            ]);

        // Solicitar la segunda oportunidad directamente.
        $ingresar->execute($eventoId, $usuario->id);

        // Debe quedar registrado el segundo intento.
        $this->assertDatabaseHas('cola_virtual', [
            'id' => $turno->getId(),
            'intento' => 2,
            'estado' => 'esperando',
        ]);

        // El intento anterior debe conservarse como expirado.
        $this->assertDatabaseHas('cola_virtual_intentos', [
            'turno_id' => $turno->getId(),
            'intento' => 1,
            'estado' => 'expirado',
        ]);

        // Debe existir el aviso de expiración del primer intento.
        $this->assertDatabaseHas('notificaciones', [
            'usuario_id' => $usuario->id,
            'clave_idempotencia' =>
                'cola_virtual:expirado:turno:' . $turno->getId(),
        ]);
    }

    public function test_segunda_expiracion_sin_scheduler_registra_notificacion(): void
    {
        // Simular los trabajos de correo.
        \Illuminate\Support\Facades\Bus::fake([
            \App\Jobs\Notificaciones\EnviarNotificacionCorreoJob::class,
        ]);

        config()->set('cola_virtual.max_turnos_simultaneos', 1);
        config()->set('cola_virtual.aviso_proximo_personas', 0);

        $eventoId = $this->crearEvento();
        // Preparar inventario suficiente para las dos oportunidades.
        $this->crearInventarioDisponible($eventoId, 10);

        $usuario = $this->crearUsuario(
            'segunda-expiracion-sin-scheduler@example.com'
        );

        $ingresar = app(
            \App\Application\Abstractions\ColaVirtual\IIngresarColaVirtualUseCase::class
        );

        $procesador = app(
            \App\Application\Abstractions\ColaVirtual\IProcesarColaVirtualUseCase::class
        );

        // Primera oportunidad: ingresar y habilitar.
        $primerTurno = $ingresar->execute($eventoId, $usuario->id);
        $procesador->execute();

        // Expirar la primera oportunidad.
        DB::table('cola_virtual')
            ->where('id', $primerTurno->getId())
            ->update([
                'habilitado_hasta' => now()->subMinute(),
                'updated_at' => now(),
            ]);

        $procesador->execute();

        // Solicitar y habilitar la segunda oportunidad.
        $segundoTurno = $ingresar->execute(
            $eventoId,
            $usuario->id
        );

        $procesador->execute();

        $this->assertDatabaseHas('cola_virtual', [
            'id' => $segundoTurno->getId(),
            'intento' => 2,
            'estado' => 'habilitado',
        ]);

        // La segunda oportunidad vence, pero NO ejecutamos
        // nuevamente el procesador.
        DB::table('cola_virtual')
            ->where('id', $segundoTurno->getId())
            ->update([
                'habilitado_hasta' => now()->subMinute(),
                'updated_at' => now(),
            ]);

        // Intentar ingresar por tercera vez debe rechazarse.
        try {
            $ingresar->execute($eventoId, $usuario->id);

            $this->fail('No debe permitir una tercera oportunidad.');
        } catch (\DomainException $e) {
            $this->assertSame(
                'Ya utilizaste las dos oportunidades para este evento.',
                $e->getMessage()
            );
        }

        // Además, debe existir el aviso de la segunda expiración.
        $this->assertDatabaseHas('notificaciones', [
            'usuario_id' => $usuario->id,
            'clave_idempotencia' =>
                'cola_virtual:expirado:turno:'
                . $segundoTurno->getId()
                . ':intento:2',
        ]);

        // El usuario insiste en solicitar una tercera oportunidad.
        try {
            $ingresar->execute($eventoId, $usuario->id);

            $this->fail('No debe permitir una tercera oportunidad.');
        } catch (\DomainException $e) {
            $this->assertSame(
                'Ya utilizaste las dos oportunidades para este evento.',
                $e->getMessage()
            );
        }

        // El segundo turno debe permanecer expirado.
        $this->assertDatabaseHas('cola_virtual', [
            'id' => $segundoTurno->getId(),
            'intento' => 2,
            'estado' => 'expirado',
        ]);

        // Debe existir un único aviso de la segunda expiración.
        $this->assertSame(
            1,
            DB::table('notificaciones')
                ->where(
                    'clave_idempotencia',
                    'cola_virtual:expirado:turno:'
                    . $segundoTurno->getId()
                    . ':intento:2'
                )
                ->count()
        );

        // Dos habilitaciones y dos expiraciones.
        $this->assertDatabaseCount('notificaciones', 4);

    }

    public function test_no_habilita_turnos_si_evento_no_tiene_inventario(): void
    {
        // Evitar la ejecución de trabajos de correo.
        \Illuminate\Support\Facades\Bus::fake([
            \App\Jobs\Notificaciones\EnviarNotificacionCorreoJob::class,
        ]);

        config()->set('cola_virtual.max_turnos_simultaneos', 5);

        // Crear evento sin localidades, asientos ni inventario.
        $eventoId = $this->crearEvento();

        $usuario = $this->crearUsuario(
            'sin-inventario-cola@example.com'
        );

        // El usuario puede registrarse en la cola.
        $ingresar = app(
            \App\Application\Abstractions\ColaVirtual\IIngresarColaVirtualUseCase::class
        );

        $turno = $ingresar->execute(
            $eventoId,
            $usuario->id
        );

        // Confirmar que no existen boletos para este evento.
        $this->assertSame(
            0,
            DB::table('inventario')
                ->where('evento_id', $eventoId)
                ->count()
        );

        // Ejecutar el procesador.
        $procesador = app(
            \App\Application\Abstractions\ColaVirtual\IProcesarColaVirtualUseCase::class
        );

        $resultado = $procesador->execute();

        // No debe habilitar compradores sin inventario.
        $this->assertSame(0, $resultado['habilitados']);

        // El usuario debe permanecer esperando.
        $this->assertDatabaseHas('cola_virtual', [
            'id' => $turno->getId(),
            'estado' => 'esperando',
        ]);
    }

    public function test_procesador_habilita_maximo_10_compradores(): void
    {
        // No ejecutar trabajos reales de correo.
        \Illuminate\Support\Facades\Bus::fake([
            \App\Jobs\Notificaciones\EnviarNotificacionCorreoJob::class,
        ]);

        // Límite predeterminado: 10 compradores a la vez (recintos de ≤ 100 lugares).
        $this->assertSame(
            10,
            (int) config('cola_virtual.max_turnos_simultaneos')
        );

        $eventoId = $this->crearEvento();

        // Preparar 15 boletos disponibles.
        $this->crearInventarioDisponible($eventoId, 15);

        $ingresar = app(
            \App\Application\Abstractions\ColaVirtual\IIngresarColaVirtualUseCase::class
        );

        // Registrar 15 usuarios en la cola.
        for ($numero = 1; $numero <= 15; $numero++) {
            $usuario = $this->crearUsuario(
                "usuario-limite10-{$numero}@example.com"
            );

            $ingresar->execute($eventoId, $usuario->id);
        }

        $procesador = app(
            \App\Application\Abstractions\ColaVirtual\IProcesarColaVirtualUseCase::class
        );

        $resultado = $procesador->execute();

        // Debe habilitar solamente a 10 usuarios.
        $this->assertSame(10, $resultado['habilitados']);

        // Los primeros 10 quedan habilitados.
        $this->assertSame(
            10,
            DB::table('cola_virtual')
                ->where('evento_id', $eventoId)
                ->where('estado', 'habilitado')
                ->count()
        );

        // Los otros 5 permanecen esperando.
        $this->assertSame(
            5,
            DB::table('cola_virtual')
                ->where('evento_id', $eventoId)
                ->where('estado', 'esperando')
                ->count()
        );

        // Ejecutar otra vez no debe superar el límite.
        $segundoResultado = $procesador->execute();

        $this->assertSame(0, $segundoResultado['habilitados']);

        $this->assertSame(
            10,
            DB::table('cola_virtual')
                ->where('evento_id', $eventoId)
                ->where('estado', 'habilitado')
                ->count()
        );
    }


}
