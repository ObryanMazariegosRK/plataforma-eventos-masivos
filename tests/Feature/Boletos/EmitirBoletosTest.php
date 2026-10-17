<?php

namespace Tests\Feature\Boletos;

use App\Application\UseCases\Boletos\EmitirBoletosUseCase;
use App\Domain\Enums\Auth\EstadoUsuario;
use App\Domain\Enums\Auth\RolUsuario;
use App\Domain\Enums\Inventario\EstadoAsiento;
use App\Domain\Enums\Pagos\EstadoPago;
use App\Domain\Enums\Reservas\EstadoReserva;
use App\Models\Auth\Usuario;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Illuminate\Database\QueryException;

class EmitirBoletosTest extends TestCase
{
    use RefreshDatabase;

    public function test_emite_un_boleto_con_pago_aprobado(): void
    {
        $datos = $this->crearEscenario(
            EstadoPago::APROBADO->value,
            EstadoAsiento::VENDIDO->value
        );

        $boletos = app(EmitirBoletosUseCase::class)->execute($datos['pagoId']);

        $this->assertCount(1, $boletos);
        $this->assertDatabaseHas('boletos', [
            'reserva_id' => $datos['reservaId'],
            'inventario_id' => $datos['inventarioId'],
            'usuario_id' => $datos['usuarioId'],
            'estado' => 'emitido',
        ]);
        $this->assertDatabaseCount('boletos', 1);

        $codigoQr = $boletos[0]->getCodigoQr();
        $this->assertNotEmpty($codigoQr);
        $this->assertSame(64, strlen($codigoQr));
    }

    public function test_rechaza_un_pago_inexistente(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('El pago no existe o no está aprobado.');

        app(EmitirBoletosUseCase::class)->execute(999999);
    }

    public function test_rechaza_un_pago_pendiente(): void
    {
        $datos = $this->crearEscenario(
            'pendiente',
            EstadoAsiento::VENDIDO->value
        );

        try {
            app(EmitirBoletosUseCase::class)->execute($datos['pagoId']);
            $this->fail('Un pago pendiente no debe permitir emitir boletos.');
        } catch (DomainException $e) {
            $this->assertSame(
                'El pago no existe o no está aprobado.',
                $e->getMessage()
            );
        }

        $this->assertDatabaseCount('boletos', 0);
    }

    public function test_rechaza_inventario_no_vendido(): void
    {
        $datos = $this->crearEscenario(
            EstadoPago::APROBADO->value,
            'disponible'
        );

        // Ejecutar una sola vez, dentro del try, para capturar la excepción esperada.
        try {
            app(EmitirBoletosUseCase::class)->execute($datos['pagoId']);
            $this->fail('Un inventario disponible no debe permitir emitir boletos.');
        } catch (DomainException $e) {
            $this->assertSame(
                'El inventario no está listo para emitir boletos.',
                $e->getMessage()
            );
        }

        $this->assertDatabaseCount('boletos', 0);
    }

    /**
     * Prepara un usuario, recinto, evento, localidad, reserva, inventario y pago
     * exclusivamente en la base de datos temporal de las pruebas.
     *
     * @return array{usuarioId: int, reservaId: int, inventarioId: int, pagoId: int}
     */


    public function test_emision_es_idempotente(): void
    {
        // Preparar escenario válido.
        $datos = $this->crearEscenario(
            EstadoPago::APROBADO->value,
            EstadoAsiento::VENDIDO->value
        );

        $casoDeUso = app(EmitirBoletosUseCase::class);

        // Primera emisión.
        $primeraEmision = $casoDeUso->execute($datos['pagoId']);

        $this->assertCount(1, $primeraEmision);

        // Segunda emisión con el mismo pago.
        $segundaEmision = $casoDeUso->execute($datos['pagoId']);

        $this->assertCount(1, $segundaEmision);

        // Debe devolver exactamente el mismo boleto.
        $this->assertSame(
            $primeraEmision[0]->getId(),
            $segundaEmision[0]->getId()
        );

        // El código QR debe mantenerse.
        $this->assertSame(
            $primeraEmision[0]->getCodigoQr(),
            $segundaEmision[0]->getCodigoQr()
        );

        // No deben existir duplicados.
        $this->assertDatabaseCount('boletos', 1);
    }


 
    public function test_rechaza_emision_parcial(): void
    {
        // Crear escenario válido.
        $datos = $this->crearEscenario(
            EstadoPago::APROBADO->value,
            EstadoAsiento::VENDIDO->value
        );

        // Consultar el primer inventario.
        $inventario = DB::table('inventario')
            ->where('id', $datos['inventarioId'])
            ->first();

        // Agregar un segundo inventario a la misma reserva.
        DB::table('inventario')->insert([
            'evento_id' => $inventario->evento_id,
            'localidad_id' => $inventario->localidad_id,
            'reserva_id' => $datos['reservaId'],
            'estado' => EstadoAsiento::VENDIDO->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Simular que solamente se emitió el primer boleto.
        $codigoOriginal = bin2hex(random_bytes(32));

        DB::table('boletos')->insert([
            'reserva_id' => $datos['reservaId'],
            'inventario_id' => $datos['inventarioId'],
            'usuario_id' => $datos['usuarioId'],
            'codigo_qr' => $codigoOriginal,
            'estado' => 'emitido',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Intentar emitir nuevamente.
        try {
            app(EmitirBoletosUseCase::class)
                ->execute($datos['pagoId']);

            $this->fail(
                'La emisión parcial debió ser rechazada.'
            );
        } catch (DomainException $e) {
            $this->assertSame(
                'La reserva tiene una emisión de boletos incompleta.',
                $e->getMessage()
            );
        }

        // Debe permanecer únicamente el boleto original.
        $this->assertDatabaseCount('boletos', 1);

        $this->assertDatabaseHas('boletos', [
            'inventario_id' => $datos['inventarioId'],
            'codigo_qr' => $codigoOriginal,
        ]);
    }
    
    public function test_rechaza_boleto_eliminado_logicamente(): void
    {
        // 1. Preparar un escenario válido.
        $datos = $this->crearEscenario(
            EstadoPago::APROBADO->value,
            EstadoAsiento::VENDIDO->value
        );

        // 2. Simular un boleto eliminado lógicamente.
        $codigoQr = bin2hex(random_bytes(32));

        DB::table('boletos')->insert([
            'reserva_id' => $datos['reservaId'],
            'inventario_id' => $datos['inventarioId'],
            'usuario_id' => $datos['usuarioId'],
            'codigo_qr' => $codigoQr,
            'estado' => 'emitido',
            'deleted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 3. Intentar emitir nuevamente.
        try {
            app(EmitirBoletosUseCase::class)
                ->execute($datos['pagoId']);

            $this->fail(
                'El boleto eliminado debió impedir la emisión.'
            );
        } catch (\DomainException $e) {
            $this->assertSame(
                'Existe un boleto eliminado para este inventario.',
                $e->getMessage()
            );
        }

        // 4. Verificar que no se crearon duplicados.
        $this->assertDatabaseCount('boletos', 1);

        $this->assertDatabaseHas('boletos', [
            'inventario_id' => $datos['inventarioId'],
            'codigo_qr' => $codigoQr,
        ]);

        // 5. Confirmar que continúa eliminado lógicamente.
        $fechaEliminacion = DB::table('boletos')
            ->where('inventario_id', $datos['inventarioId'])
            ->value('deleted_at');

        $this->assertNotNull($fechaEliminacion);
    }
 
    public function test_rechaza_pago_insuficiente(): void
    {
        // 1. Crear un escenario válido.
        $datos = $this->crearEscenario(
            EstadoPago::APROBADO->value,
            EstadoAsiento::VENDIDO->value
        );

        // 2. Simular que el cliente pagó menos
        // del total de la reserva.
        DB::table('pagos')
            ->where('id', $datos['pagoId'])
            ->update([
                'monto' => 100.00
            ]);

        // La reserva tiene un total de Q150.00.

        // 3. Intentar emitir el boleto.
        try {
            app(EmitirBoletosUseCase::class)
                ->execute($datos['pagoId']);

            $this->fail(
                'No se debe emitir un boleto con pago insuficiente.'
            );

        } catch (DomainException $e) {

            // 4. Verificar el rechazo esperado.
            $this->assertSame(
                'El pago no cubre el total de la reserva.',
                $e->getMessage()
            );
        }

        // 5. Comprobar que no se generaron boletos.
        $this->assertDatabaseCount('boletos', 0);
    }

    public function test_rechaza_reserva_no_confirmada(): void
    {
        // 1. Crear un escenario con pago aprobado
        // e inventario vendido.
        $datos = $this->crearEscenario(
            EstadoPago::APROBADO->value,
            EstadoAsiento::VENDIDO->value
        );

        // 2. Cambiar la reserva al estado ACTIVA.
        DB::table('reservas')
            ->where('id', $datos['reservaId'])
            ->update([
                'estado' => EstadoReserva::ACTIVA->value
            ]);

        // 3. Intentar emitir el boleto.
        try {
            app(EmitirBoletosUseCase::class)
                ->execute($datos['pagoId']);

            $this->fail(
                'No se deben emitir boletos de reservas no confirmadas.'
            );

        } catch (DomainException $e) {

            // 4. Verificar el mensaje de rechazo.
            $this->assertSame(
                'La reserva no está confirmada.',
                $e->getMessage()
            );
        }

        // 5. Comprobar que no se generaron boletos.
        $this->assertDatabaseCount('boletos', 0);
    }

    
    public function test_emite_varios_boletos_en_una_reserva(): void
    {
        // 1. Crear un escenario válido.
        $datos = $this->crearEscenario(
            EstadoPago::APROBADO->value,
            EstadoAsiento::VENDIDO->value
        );

        // 2. Obtener el inventario existente.
        $inventario = DB::table('inventario')
            ->where('id', $datos['inventarioId'])
            ->first();

        // 3. Agregar un segundo inventario a la reserva.
        $segundoInventarioId = DB::table('inventario')
            ->insertGetId([
                'evento_id' => $inventario->evento_id,
                'localidad_id' => $inventario->localidad_id,
                'reserva_id' => $datos['reservaId'],
                'estado' => EstadoAsiento::VENDIDO->value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        // 4. Ejecutar la emisión de boletos.
        $boletos = app(EmitirBoletosUseCase::class)
            ->execute($datos['pagoId']);

        // 5. Verificar que se emitieron dos boletos.
        $this->assertCount(2, $boletos);

        // 6. Verificar que ambos inventarios tienen boleto.
        $inventariosEmitidos = array_map(
            fn ($boleto) => $boleto->getInventarioId(),
            $boletos
        );

        $this->assertEqualsCanonicalizing(
            [$datos['inventarioId'], $segundoInventarioId],
            $inventariosEmitidos
        );

        // 7. Comprobar que cada boleto tiene un QR único.
        $codigosQr = array_map(
            fn ($boleto) => $boleto->getCodigoQr(),
            $boletos
        );

        $this->assertCount(2, array_unique($codigosQr));

        // 8. Verificar el tamaño de los identificadores.
        foreach ($codigosQr as $codigo) {
            $this->assertSame(64, strlen($codigo));
        }

        // 9. Verificar que solo existen dos boletos.
        $this->assertDatabaseCount('boletos', 2);
    }

    public function test_emision_multiple_es_idempotente(): void
    {
        // 1. Crear un escenario válido.
        $datos = $this->crearEscenario(
            EstadoPago::APROBADO->value,
            EstadoAsiento::VENDIDO->value
        );

        // 2. Obtener el inventario existente.
        $inventario = DB::table('inventario')
            ->where('id', $datos['inventarioId'])
            ->first();

        // 3. Agregar una segunda entrada a la reserva.
        DB::table('inventario')->insert([
            'evento_id' => $inventario->evento_id,
            'localidad_id' => $inventario->localidad_id,
            'reserva_id' => $datos['reservaId'],
            'estado' => EstadoAsiento::VENDIDO->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 4. Obtener nuestro caso de uso.
        $caso = app(EmitirBoletosUseCase::class);

        // 5. Primera emisión.
        $primeraEmision = $caso->execute(
            $datos['pagoId']
        );

        $this->assertCount(2, $primeraEmision);

        // 6. Segunda emisión utilizando el mismo pago.
        $segundaEmision = $caso->execute(
            $datos['pagoId']
        );

        $this->assertCount(2, $segundaEmision);

        // 7. Organizar los resultados por inventario.
        $organizar = function (array $boletos): array {
            $resultado = [];

            foreach ($boletos as $boleto) {
                $resultado[$boleto->getInventarioId()] = [
                    'id' => $boleto->getId(),
                    'codigoQr' => $boleto->getCodigoQr(),
                ];
            }

            ksort($resultado);

            return $resultado;
        };

        // 8. Comprobar que los boletos son los mismos.
        $this->assertSame(
            $organizar($primeraEmision),
            $organizar($segundaEmision)
        );

        // 9. Comprobar que solo existen dos boletos.
        $this->assertDatabaseCount('boletos', 2);

        // 10. Verificar que los códigos siguen siendo únicos.
        $codigos = array_map(
            fn ($boleto) => $boleto->getCodigoQr(),
            $segundaEmision
        );

        $this->assertCount(2, array_unique($codigos));
    }

    public function test_impide_boletos_duplicados_por_inventario(): void
    {
        // 1. Crear un escenario válido.
        $datos = $this->crearEscenario(
            EstadoPago::APROBADO->value,
            EstadoAsiento::VENDIDO->value
        );

        // 2. Preparar el primer boleto.
        $boleto = [
            'reserva_id' => $datos['reservaId'],
            'inventario_id' => $datos['inventarioId'],
            'usuario_id' => $datos['usuarioId'],
            'codigo_qr' => bin2hex(random_bytes(32)),
            'estado' => 'emitido',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // 3. Guardar el primer boleto.
        DB::table('boletos')->insert($boleto);

        $this->assertDatabaseCount('boletos', 1);

        // 4. Intentar crear otro boleto para
        // el mismo inventario, con un QR diferente.
        try {
            DB::table('boletos')->insert([
                ...$boleto,
                'codigo_qr' => bin2hex(random_bytes(32)),
            ]);

        } catch (QueryException $e) {

            // 5. Verificar que la restricción
            // UNIQUE de inventario rechazó la operación.
            $this->assertStringContainsString(
                'boletos.inventario_id',
                $e->getMessage()
            );

            // 6. Confirmar que sigue existiendo
            // solamente un boleto.
            $this->assertDatabaseCount('boletos', 1);

            return;
        }

        $this->fail(
            'La base de datos permitió duplicar un boleto.'
        );
    }

    public function test_impide_codigos_qr_duplicados(): void
    {
        // 1. Crear un escenario válido.
        $datos = $this->crearEscenario(
            EstadoPago::APROBADO->value,
            EstadoAsiento::VENDIDO->value
        );

        // 2. Obtener el primer inventario.
        $inventario = DB::table('inventario')
            ->where('id', $datos['inventarioId'])
            ->first();

        // 3. Crear un segundo inventario.
        $segundoInventarioId = DB::table('inventario')
            ->insertGetId([
                'evento_id' => $inventario->evento_id,
                'localidad_id' => $inventario->localidad_id,
                'reserva_id' => $datos['reservaId'],
                'estado' => EstadoAsiento::VENDIDO->value,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

        // 4. Generar un código QR único de prueba.
        $codigoQr = bin2hex(random_bytes(32));

        // 5. Crear el primer boleto.
        DB::table('boletos')->insert([
            'reserva_id' => $datos['reservaId'],
            'inventario_id' => $datos['inventarioId'],
            'usuario_id' => $datos['usuarioId'],
            'codigo_qr' => $codigoQr,
            'estado' => 'emitido',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // 6. Intentar crear un segundo boleto
        // con diferente inventario, pero el mismo QR.
        try {
            DB::table('boletos')->insert([
                'reserva_id' => $datos['reservaId'],
                'inventario_id' => $segundoInventarioId,
                'usuario_id' => $datos['usuarioId'],
                'codigo_qr' => $codigoQr,
                'estado' => 'emitido',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->fail(
                'La base de datos permitió duplicar un código QR.'
            );

        } catch (QueryException $e) {

            // 7. Verificar que se rechazó
            // por duplicar el código QR.
            $this->assertStringContainsString(
                'boletos.codigo_qr',
                $e->getMessage()
            );
        }

        // 8. Confirmar que solo existe un boleto.
        $this->assertDatabaseCount('boletos', 1);

        // 9. Verificar que el boleto original permanece.
        $this->assertDatabaseHas('boletos', [
            'inventario_id' => $datos['inventarioId'],
            'codigo_qr' => $codigoQr,
        ]);
    }

    private function crearEscenario(string $estadoPago, string $estadoInventario): array
    {
        $usuario = Usuario::create([
            'nombre' => 'Juan',
            'apellido' => 'Prueba',
            'email' => 'juan@example.com',
            'password' => Hash::make('Secreto#123'),
            'rol' => RolUsuario::CLIENTE,
            'estado' => EstadoUsuario::ACTIVO,
            'email_verified_at' => now(),
        ]);

        $recintoId = DB::table('recintos')->insertGetId([
            'nombre' => 'Estadio de Prueba',
            'direccion' => 'Zona 1',
            'ciudad' => 'Guatemala',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $eventoId = DB::table('eventos')->insertGetId([
            'recinto_id' => $recintoId,
            'nombre' => 'Concierto de Prueba',
            'fecha_evento' => now()->addMonth(),
            'estado' => 'publicado',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $localidadId = DB::table('localidades')->insertGetId([
            'recinto_id' => $recintoId,
            'nombre' => 'General',
            'tipo' => 'general',
            'capacidad' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $reservaId = DB::table('reservas')->insertGetId([
            'usuario_id' => $usuario->id,
            'evento_id' => $eventoId,
            'estado' => EstadoReserva::CONFIRMADA->value,
            'expira_en' => now()->addMinutes(5),
            'total' => 150.00,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $inventarioId = DB::table('inventario')->insertGetId([
            'evento_id' => $eventoId,
            'localidad_id' => $localidadId,
            'reserva_id' => $reservaId,
            'estado' => $estadoInventario,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $pagoId = DB::table('pagos')->insertGetId([
            'reserva_id' => $reservaId,
            'monto' => 150.00,
            'estado' => $estadoPago,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [
            'usuarioId' => $usuario->id,
            'reservaId' => $reservaId,
            'inventarioId' => $inventarioId,
            'pagoId' => $pagoId,
        ];
    }
}
