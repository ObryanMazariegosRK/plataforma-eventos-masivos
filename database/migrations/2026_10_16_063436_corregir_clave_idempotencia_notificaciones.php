<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn(
            'notificaciones',
            'clave_idempotencia'
        )) {
            Schema::table('notificaciones', function (Blueprint $table) {
                $table->string('clave_idempotencia', 191)
                    ->nullable()
                    ->unique();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn(
            'notificaciones',
            'clave_idempotencia'
        )) {
            Schema::table('notificaciones', function (Blueprint $table) {
                $table->dropUnique(['clave_idempotencia']);
                $table->dropColumn('clave_idempotencia');
            });
        }
    }
};
