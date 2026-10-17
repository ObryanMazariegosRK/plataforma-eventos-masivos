<?php

namespace Tests\Feature\Boletos;

use Tests\TestCase;

class EntornoPruebasTest extends TestCase
{
    public function test_utiliza_sqlite_en_memoria(): void
    {
        // Verificar que Laravel esté en modo de pruebas.
        $this->assertSame(
            'testing',
            app()->environment()
        );

        // Comprobar que la conexión sea SQLite.
        $this->assertSame(
            'sqlite',
            config('database.default')
        );

        // Comprobar que SQLite utilice memoria.
        $this->assertSame(
            ':memory:',
            config('database.connections.sqlite.database')
        );

        // Verificar que no exista una URL que
        // redirija la conexión hacia otra base de datos.
        $this->assertEmpty(
            config('database.connections.sqlite.url')
        );
    }
    
    public function test_conexion_real_utiliza_sqlite_en_memoria(): void
    {
        // Obtener la conexión real de Laravel.
        $conexion = \Illuminate\Support\Facades\DB::connection();

        // Comprobar el motor de base de datos utilizado.
        $motor = $conexion->getPdo()
            ->getAttribute(\PDO::ATTR_DRIVER_NAME);

        $this->assertSame('sqlite', $motor);

        // Consultar dónde está almacenada la base SQLite.
        $bases = $conexion->select('PRAGMA database_list');

        $principal = collect($bases)->firstWhere('name', 'main');

        $this->assertNotNull($principal);

        // Una base SQLite en memoria no tiene archivo físico.
        $this->assertSame('', $principal->file);
    }

}
