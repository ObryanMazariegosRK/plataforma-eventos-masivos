<?php

namespace Tests\Feature\Transacciones;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\QueryException;
use Tests\TestCase;

class HistorialOperacionesTest extends TestCase
{
    use RefreshDatabase;

    public function test_tabla_historial_existe_con_sus_columnas(): void
    {
        // Confirmar que estamos usando SQLite en memoria.
        $this->assertEquals(
            'sqlite',
            DB::connection()->getDriverName()
        );

        $this->assertEquals(
            ':memory:',
            DB::connection()->getDatabaseName()
        );

        $this->assertTrue(
            Schema::hasTable('historial_operaciones')
        );

        $this->assertTrue(
            Schema::hasColumns('historial_operaciones', [
                'id',
                'tipo',
                'usuario_id',
                'evento_id',
                'reserva_id',
                'inventario_id',
                'pago_id',
                'registrado_por',
                'descripcion',
                'datos',
                'clave_idempotencia',
                'created_at',
            ])
        );
    }

    public function test_no_permite_duplicar_una_operacion(): void
    {
        $operacion = [
            'tipo' => 'reserva_creada',
            'descripcion' => 'Reserva registrada',
            'clave_idempotencia' => 'reserva-100-creada',
            'created_at' => now(),
        ];

        DB::table('historial_operaciones')->insert($operacion);

        $this->assertDatabaseCount('historial_operaciones', 1);

        try {
            DB::table('historial_operaciones')->insert($operacion);

            $this->fail(
                'La base de datos permitió duplicar la operación.'
            );
        } catch (QueryException $e) {
            // La restricción UNIQUE debe impedir el duplicado.
            $this->assertDatabaseCount('historial_operaciones', 1);
        }
    }
}
