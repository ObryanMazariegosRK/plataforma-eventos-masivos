<?php

namespace Tests\Feature\Reembolsos;

use App\Application\UseCases\Reembolsos\CancelarEventoConReembolsosUseCase;
use App\Domain\Enums\Auth\EstadoUsuario;
use App\Domain\Enums\Auth\RolUsuario;
use App\Models\Auth\Usuario;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use App\Application\UseCases\Reembolsos\ResolverSolicitudReembolsoUseCase;

class CancelarEventoConReembolsosTest extends TestCase
{
    use RefreshDatabase;

    private function crearUsuario(
        string $email,
        RolUsuario $rol = RolUsuario::CLIENTE
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

    private function crearEvento(
        string $estado = 'publicado'
    ): int {
        $recintoId = DB::table('recintos')->insertGetId([
            'nombre' => 'Recinto Cancelaciones',
            'direccion' => 'Zona 1',
            'ciudad' => 'Guatemala',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('eventos')->insertGetId([
            'recinto_id' => $recintoId,
            'nombre' => 'Concierto de Prueba',
            'fecha_evento' => now()->addDays(10),
            'estado' => $estado,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function crearPago(
        int $eventoId,
        int $usuarioId,
        float $monto,
        string $estado = 'aprobado'
    ): int {
        $reservaId = DB::table('reservas')->insertGetId([
            'usuario_id' => $usuarioId,
            'evento_id' => $eventoId,
            'estado' => 'confirmada',
            'expira_en' => now()->addMinutes(5),
            'total' => $monto,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('pagos')->insertGetId([
            'reserva_id' => $reservaId,
            'monto' => $monto,
            'estado' => $estado,
            'intento' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function crearReembolsoPrevio(
        int $pagoId,
        float $monto,
        string $estado = 'solicitado'
    ): void {
        DB::table('reembolsos')->insert([
            'pago_id' => $pagoId,
            'monto' => $monto,
            'estado' => $estado,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_cancela_evento_con_dos_compras(): void
    {
        $admin = $this->crearUsuario(
            'admin-masivo@test.com',
            RolUsuario::ADMIN
        );

        $cliente1 = $this->crearUsuario(
            'cliente1-masivo@test.com'
        );

        $cliente2 = $this->crearUsuario(
            'cliente2-masivo@test.com'
        );

        $eventoId = $this->crearEvento();

        $pago1 = $this->crearPago(
            $eventoId,
            $cliente1->id,
            500
        );

        $pago2 = $this->crearPago(
            $eventoId,
            $cliente2->id,
            250
        );

        $resultado = app(
            CancelarEventoConReembolsosUseCase::class
        )->execute($eventoId, $admin->id);

        $this->assertSame(
            'cancelado',
            $resultado['estado']
        );

        $this->assertSame(
            2,
            $resultado['pagos_aprobados']
        );

        $this->assertSame(
            2,
            $resultado['reembolsos_creados']
        );

        $this->assertEquals(
            750,
            $resultado['monto_preparado']
        );

        $this->assertDatabaseHas('eventos', [
            'id' => $eventoId,
            'estado' => 'cancelado',
        ]);

        $this->assertDatabaseHas('reembolsos', [
            'pago_id' => $pago1,
            'monto' => 500,
            'estado' => 'solicitado',
        ]);

        $this->assertDatabaseHas('reembolsos', [
            'pago_id' => $pago2,
            'monto' => 250,
            'estado' => 'solicitado',
        ]);

        $this->assertDatabaseCount('reembolsos', 2);
    }

    public function test_completa_reembolso_parcial_previo(): void
    {
        $admin = $this->crearUsuario(
            'admin-parcial@test.com',
            RolUsuario::ADMIN
        );

        $cliente = $this->crearUsuario(
            'cliente-parcial@test.com'
        );

        $eventoId = $this->crearEvento();

        $pagoId = $this->crearPago(
            $eventoId,
            $cliente->id,
            500
        );

        // El cliente ya habia solicitado Q400.
        $this->crearReembolsoPrevio(
            $pagoId,
            400
        );

        $resultado = app(
            CancelarEventoConReembolsosUseCase::class
        )->execute($eventoId, $admin->id);

        // Solo deben prepararse los Q100 restantes.
        $this->assertSame(
            1,
            $resultado['reembolsos_creados']
        );

        $this->assertEquals(
            100,
            $resultado['monto_preparado']
        );

        $this->assertDatabaseHas('reembolsos', [
            'pago_id' => $pagoId,
            'monto' => 100,
            'estado' => 'solicitado',
        ]);

        $this->assertEquals(
            500,
            DB::table('reembolsos')
                ->where('pago_id', $pagoId)
                ->sum('monto')
        );

        $this->assertDatabaseCount('reembolsos', 2);
    }

    public function test_no_duplica_reembolso_completo_previo(): void
    {
        $admin = $this->crearUsuario(
            'admin-completo@test.com',
            RolUsuario::ADMIN
        );

        $cliente = $this->crearUsuario(
            'cliente-completo@test.com'
        );

        $eventoId = $this->crearEvento();

        $pagoId = $this->crearPago(
            $eventoId,
            $cliente->id,
            500
        );

        $this->crearReembolsoPrevio(
            $pagoId,
            500,
            'aprobado'
        );

        $resultado = app(
            CancelarEventoConReembolsosUseCase::class
        )->execute($eventoId, $admin->id);

        $this->assertSame(
            0,
            $resultado['reembolsos_creados']
        );

        $this->assertEquals(
            0,
            $resultado['monto_preparado']
        );

        $this->assertDatabaseCount('reembolsos', 1);
    }

    public function test_rechaza_evento_ya_cancelado(): void
    {
        $admin = $this->crearUsuario(
            'admin-duplicado@test.com',
            RolUsuario::ADMIN
        );

        $eventoId = $this->crearEvento('cancelado');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'El evento ya está cancelado.'
        );

        app(CancelarEventoConReembolsosUseCase::class)
            ->execute($eventoId, $admin->id);
    }

    public function test_rechaza_evento_en_borrador(): void
    {
        $admin = $this->crearUsuario(
            'admin-borrador@test.com',
            RolUsuario::ADMIN
        );

        $eventoId = $this->crearEvento('borrador');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'El estado del evento no permite su cancelación.'
        );

        app(CancelarEventoConReembolsosUseCase::class)
            ->execute($eventoId, $admin->id);
    }

    public function test_revierte_si_reembolsos_superan_pago(): void
    {
        $admin = $this->crearUsuario(
            'admin-exceso@test.com',
            RolUsuario::ADMIN
        );

        $cliente = $this->crearUsuario(
            'cliente-exceso@test.com'
        );

        $eventoId = $this->crearEvento();

        $pagoId = $this->crearPago(
            $eventoId,
            $cliente->id,
            500
        );

        // Caso inconsistente: existe una devolucion
        // pendiente por mas dinero del pagado.
        $this->crearReembolsoPrevio(
            $pagoId,
            600
        );

        try {
            app(CancelarEventoConReembolsosUseCase::class)
                ->execute($eventoId, $admin->id);

            $this->fail(
                'Se permitio una cancelacion con importes excesivos.'
            );

        } catch (DomainException $e) {
            $this->assertSame(
                'Existen reembolsos que superan el monto de un pago.',
                $e->getMessage()
            );
        }

        // La cancelacion no debe completarse.
        $this->assertDatabaseHas('eventos', [
            'id' => $eventoId,
            'estado' => 'publicado',
        ]);

        $this->assertDatabaseCount('reembolsos', 1);
    }

    private function crearBoletoDePrueba(
        int $pagoId,
        int $usuarioId,
        string $estado
    ): int {
        $reservaId = DB::table('pagos')
            ->where('id', $pagoId)
            ->value('reserva_id');

        $eventoId = DB::table('reservas')
            ->where('id', $reservaId)
            ->value('evento_id');

        $recintoId = DB::table('eventos')
            ->where('id', $eventoId)
            ->value('recinto_id');

        $localidadId = DB::table('localidades')->insertGetId([
            'recinto_id' => $recintoId,
            'nombre' => 'Localidad ' . bin2hex(random_bytes(4)),
            'tipo' => 'general',
            'capacidad' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $inventarioId = DB::table('inventario')->insertGetId([
            'evento_id' => $eventoId,
            'localidad_id' => $localidadId,
            'reserva_id' => $reservaId,
            'estado' => 'vendido',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return DB::table('boletos')->insertGetId([
            'reserva_id' => $reservaId,
            'inventario_id' => $inventarioId,
            'usuario_id' => $usuarioId,
            'codigo_qr' => bin2hex(random_bytes(32)),
            'estado' => $estado,
            'usado_en' => $estado === 'usado' ? now() : null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_anula_boletos_emitidos_y_conserva_usados(): void
    {
        $admin = $this->crearUsuario(
            'admin-boletos@test.com',
            RolUsuario::ADMIN
        );

        $cliente = $this->crearUsuario(
            'cliente-boletos@test.com'
        );

        $eventoId = $this->crearEvento();

        $pagoId = $this->crearPago(
            $eventoId,
            $cliente->id,
            500
        );

        $boletoEmitido = $this->crearBoletoDePrueba(
            $pagoId,
            $cliente->id,
            'emitido'
        );

        $boletoUsado = $this->crearBoletoDePrueba(
            $pagoId,
            $cliente->id,
            'usado'
        );

        $resultado = app(
            CancelarEventoConReembolsosUseCase::class
        )->execute($eventoId, $admin->id);

        $this->assertSame(
            1,
            $resultado['boletos_anulados']
        );

        $this->assertDatabaseHas('boletos', [
            'id' => $boletoEmitido,
            'estado' => 'anulado',
            'updated_by' => $admin->id,
        ]);

        $this->assertDatabaseHas('boletos', [
            'id' => $boletoUsado,
            'estado' => 'usado',
        ]);

        $this->assertDatabaseHas('eventos', [
            'id' => $eventoId,
            'estado' => 'cancelado',
        ]);
    }

    public function test_fallo_revierte_cancelacion_y_anulacion(): void
    {
        $admin = $this->crearUsuario(
            'admin-rollback@test.com',
            RolUsuario::ADMIN
        );

        $cliente = $this->crearUsuario(
            'cliente-rollback@test.com'
        );

        $eventoId = $this->crearEvento();

        $pagoId = $this->crearPago(
            $eventoId,
            $cliente->id,
            500
        );

        $boletoId = $this->crearBoletoDePrueba(
            $pagoId,
            $cliente->id,
            'emitido'
        );

        // Reembolso inconsistente: Q600 sobre un pago de Q500.
        $this->crearReembolsoPrevio(
            $pagoId,
            600
        );

        try {
            app(CancelarEventoConReembolsosUseCase::class)
                ->execute($eventoId, $admin->id);

            $this->fail(
                'La cancelacion debio fallar.'
            );
        } catch (DomainException $e) {
            $this->assertSame(
                'Existen reembolsos que superan el monto de un pago.',
                $e->getMessage()
            );
        }

        // El evento debe seguir publicado.
        $this->assertDatabaseHas('eventos', [
            'id' => $eventoId,
            'estado' => 'publicado',
        ]);

        // El boleto debe conservar su estado original.
        $this->assertDatabaseHas('boletos', [
            'id' => $boletoId,
            'estado' => 'emitido',
        ]);

        // No debe haberse creado otro reembolso.
        $this->assertDatabaseCount('reembolsos', 1);
    }
    
    public function test_cancelacion_masiva_permite_aprobar_reembolsos_parciales(): void
    {
        $admin = $this->crearUsuario(
            'admin-integracion@test.com',
            RolUsuario::ADMIN
        );

        $cliente = $this->crearUsuario(
            'cliente-integracion@test.com'
        );

        $eventoId = $this->crearEvento();

        $pagoId = $this->crearPago(
            $eventoId,
            $cliente->id,
            500
        );

        // El cliente solicito Q400 antes de la cancelacion.
        $this->crearReembolsoPrevio($pagoId, 400);

        // El organizador cancela el evento.
        $resultado = app(
            CancelarEventoConReembolsosUseCase::class
        )->execute($eventoId, $admin->id);

        $this->assertSame(1, $resultado['reembolsos_creados']);
        $this->assertEquals(100, $resultado['monto_preparado']);

        $solicitudes = DB::table('reembolsos')
            ->where('pago_id', $pagoId)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $solicitudes);

        // Ambas solicitudes suman exactamente Q500.
        $this->assertEquals(
            500,
            $solicitudes->sum('monto')
        );

        $resolver = app(
            ResolverSolicitudReembolsoUseCase::class
        );

        // Ni siquiera la solicitud original puede rechazarse.
        try {
            $resolver->execute(
                $solicitudes[0]->id,
                'rechazar',
                $admin->id
            );

            $this->fail(
                'Se permitio rechazar una devolucion de evento cancelado.'
            );

        } catch (DomainException $e) {
            $this->assertSame(
                'No se puede rechazar un reembolso de un evento cancelado.',
                $e->getMessage()
            );
        }

        // Aprobar los Q400 originales.
        $resolver->execute(
            $solicitudes[0]->id,
            'aprobar',
            $admin->id
        );

        // Aprobar los Q100 adicionales.
        $resolver->execute(
            $solicitudes[1]->id,
            'aprobar',
            $admin->id
        );

        $this->assertSame(
            2,
            DB::table('reembolsos')
                ->where('pago_id', $pagoId)
                ->where('estado', 'aprobado')
                ->count()
        );

        $this->assertEquals(
            500,
            DB::table('reembolsos')
                ->where('pago_id', $pagoId)
                ->where('estado', 'aprobado')
                ->sum('monto')
        );

        // Todavia no existe confirmacion de devolucion real.
        $this->assertSame(
            0,
            DB::table('reembolsos')
                ->where('pago_id', $pagoId)
                ->where('estado', 'procesado')
                ->count()
        );
    }

    public function test_reembolso_rechazado_no_reduce_devolucion_masiva(): void
    {
        $admin = $this->crearUsuario(
            'admin-rechazado-masivo@test.com',
            RolUsuario::ADMIN
        );

        $cliente = $this->crearUsuario(
            'cliente-rechazado-masivo@test.com'
        );

        $eventoId = $this->crearEvento();

        $pagoId = $this->crearPago(
            $eventoId,
            $cliente->id,
            500
        );

        // Solicitud anterior de Q400 ya rechazada.
        $this->crearReembolsoPrevio(
            $pagoId,
            400,
            'rechazado'
        );

        $resultado = app(
            CancelarEventoConReembolsosUseCase::class
        )->execute($eventoId, $admin->id);

        // La solicitud rechazada no representa dinero
        // devuelto ni comprometido.
        $this->assertSame(
            1,
            $resultado['reembolsos_creados']
        );

        $this->assertEquals(
            500,
            $resultado['monto_preparado']
        );

        $this->assertDatabaseHas('reembolsos', [
            'pago_id' => $pagoId,
            'monto' => 500,
            'estado' => 'solicitado',
        ]);

        $this->assertDatabaseCount('reembolsos', 2);

        $this->assertDatabaseHas('eventos', [
            'id' => $eventoId,
            'estado' => 'cancelado',
        ]);
    }

    public function test_registra_cancelacion_masiva_en_historial(): void
    {
        $admin = $this->crearUsuario(
            'admin-historial@test.com',
            RolUsuario::ADMIN
        );

        $cliente = $this->crearUsuario(
            'cliente-historial@test.com'
        );

        $eventoId = $this->crearEvento();

        $this->crearPago(
            $eventoId,
            $cliente->id,
            500
        );

        // Ejecutar la cancelacion masiva.
        app(CancelarEventoConReembolsosUseCase::class)
            ->execute($eventoId, $admin->id);

        // Comprobar que se registro una sola operacion.
        $this->assertDatabaseHas('historial_operaciones', [
            'tipo' => 'evento_cancelado',
            'evento_id' => $eventoId,
            'registrado_por' => $admin->id,
            'clave_idempotencia' => 'evento_cancelado:' . $eventoId,
        ]);

        $this->assertDatabaseCount(
            'historial_operaciones',
            1
        );

        // Recuperar los detalles del registro.
        $historial = DB::table('historial_operaciones')
            ->where('evento_id', $eventoId)
            ->first();

        $datos = json_decode(
            $historial->datos,
            true
        );

        $this->assertSame(
            1,
            $datos['pagos_aprobados']
        );

        $this->assertSame(
            1,
            $datos['reembolsos_creados']
        );

        $this->assertEquals(
            500,
            $datos['monto_preparado']
        );

        $this->assertSame(
            0,
            $datos['boletos_anulados']
        );

        // Preparar un reembolso no significa
        // que el dinero ya fue devuelto.
        $this->assertDatabaseMissing('historial_operaciones', [
            'tipo' => 'reembolso_confirmado',
        ]);
    }

    public function test_no_registra_historial_si_falla_cancelacion(): void
    {
        $admin = $this->crearUsuario(
            'admin-error-historial@test.com',
            RolUsuario::ADMIN
        );

        $cliente = $this->crearUsuario(
            'cliente-error-historial@test.com'
        );

        $eventoId = $this->crearEvento();

        $pagoId = $this->crearPago(
            $eventoId,
            $cliente->id,
            500
        );

        // Introducir una solicitud inconsistente:
        // Q600 sobre un pago de Q500.
        $this->crearReembolsoPrevio(
            $pagoId,
            600
        );

        try {
            app(CancelarEventoConReembolsosUseCase::class)
                ->execute($eventoId, $admin->id);

            $this->fail(
                'La cancelacion debio ser rechazada.'
            );

        } catch (DomainException $e) {
            $this->assertSame(
                'Existen reembolsos que superan el monto de un pago.',
                $e->getMessage()
            );
        }

        // El evento no debe quedar cancelado.
        $this->assertDatabaseHas('eventos', [
            'id' => $eventoId,
            'estado' => 'publicado',
        ]);

        // No debe existir un historial de cancelacion.
        $this->assertDatabaseMissing('historial_operaciones', [
            'tipo' => 'evento_cancelado',
            'evento_id' => $eventoId,
        ]);

        $this->assertDatabaseCount(
            'historial_operaciones',
            0
        );

        // Solo permanece la solicitud inconsistente
        // que creamos inicialmente para la prueba.
        $this->assertDatabaseCount('reembolsos', 1);
    }
}
