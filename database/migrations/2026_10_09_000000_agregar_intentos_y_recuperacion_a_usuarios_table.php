<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * - Contador de intentos fallidos del código de verificación (frena fuerza bruta).
     * - Código de recuperación de contraseña, separado del de verificación
     *   para que uno no sirva para lo otro.
     */
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->unsignedTinyInteger('intentos_verificacion')->default(0)->after('codigo_expira_en');
            $table->string('codigo_recuperacion', 6)->nullable()->after('intentos_verificacion');
            $table->timestamp('recuperacion_expira_en')->nullable()->after('codigo_recuperacion');
            $table->unsignedTinyInteger('intentos_recuperacion')->default(0)->after('recuperacion_expira_en');
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropColumn(['intentos_verificacion', 'codigo_recuperacion', 'recuperacion_expira_en', 'intentos_recuperacion']);
        });
    }
};
