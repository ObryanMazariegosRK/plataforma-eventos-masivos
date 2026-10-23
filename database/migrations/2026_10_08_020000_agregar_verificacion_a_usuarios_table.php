<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Código de 6 dígitos que se envía por correo para verificar la cuenta.
     */
    public function up(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->string('codigo_verificacion', 6)->nullable()->after('email_verified_at');
            $table->timestamp('codigo_expira_en')->nullable()->after('codigo_verificacion');
        });
    }

    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropColumn(['codigo_verificacion', 'codigo_expira_en']);
        });
    }
};
