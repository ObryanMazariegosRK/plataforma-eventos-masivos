<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El pago tiene dos identificadores de la pasarela:
     * - orden_externa: la orden que se crea ANTES de que la persona apruebe (paso 1).
     * - referencia_externa: el cobro ya realizado (paso 3, al capturar).
     */
    public function up(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->string('orden_externa')->nullable()->after('pasarela_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('pagos', function (Blueprint $table) {
            $table->dropIndex(['orden_externa']);
            $table->dropColumn('orden_externa');
        });
    }
};
