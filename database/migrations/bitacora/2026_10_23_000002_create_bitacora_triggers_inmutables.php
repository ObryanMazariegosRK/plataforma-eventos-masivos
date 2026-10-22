<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// La bitácora no se puede alterar: cualquier UPDATE o DELETE falla en la propia base,
// aunque alguien tuviera permisos. (Además, el usuario que usa la app solo tiene SELECT e INSERT.)
return new class extends Migration
{
    public function up(): void
    {
        $sqlite = DB::connection($this->getConnection())->getDriverName() === 'sqlite';

        foreach (['UPDATE' => 'modificar', 'DELETE' => 'borrar'] as $operacion => $verbo) {
            $nombre = 'bitacora_no_'.strtolower($operacion);
            $mensaje = "La bitácora es inmutable: no se puede {$verbo} un registro";

            DB::connection($this->getConnection())->unprepared($sqlite
                ? "CREATE TRIGGER {$nombre} BEFORE {$operacion} ON bitacora BEGIN SELECT RAISE(ABORT, '{$mensaje}'); END"
                : "CREATE TRIGGER {$nombre} BEFORE {$operacion} ON bitacora FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$mensaje}'");
        }
    }

    public function down(): void
    {
        DB::connection($this->getConnection())->unprepared('DROP TRIGGER IF EXISTS bitacora_no_update');
        DB::connection($this->getConnection())->unprepared('DROP TRIGGER IF EXISTS bitacora_no_delete');
    }
};
