<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transacciones', function (Blueprint $table) {
            $table->unique(
                ['tipo', 'referencia'],
                'transacciones_tipo_referencia_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('transacciones', function (Blueprint $table) {
            $table->dropUnique(
                'transacciones_tipo_referencia_unique'
            );
        });
    }
};
