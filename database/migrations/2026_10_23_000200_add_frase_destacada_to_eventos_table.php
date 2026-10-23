<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Frase corta que encabeza la primera escena de la experiencia del evento (opcional;
// si falta se usa el nombre del evento)
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->string('frase_destacada', 90)->nullable()->after('nombre');
        });
    }

    public function down(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->dropColumn('frase_destacada');
        });
    }
};
