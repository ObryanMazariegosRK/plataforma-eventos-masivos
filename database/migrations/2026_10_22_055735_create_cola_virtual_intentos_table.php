<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cola_virtual_intentos', function (Blueprint $table) {
            $table->id();

            // Turno principal al que pertenece el intento.
            $table->foreignId('turno_id')
                ->constrained('cola_virtual')
                ->cascadeOnDelete();

            // Número de oportunidad utilizada.
            $table->unsignedTinyInteger('intento');

            // Información histórica del turno.
            $table->unsignedInteger('posicion');
            $table->string('estado');
            $table->dateTime('habilitado_hasta')->nullable();

            // Momento en que terminó esta oportunidad.
            $table->dateTime('finalizado_en');

            $table->timestamps();

            // Impedir guardar dos veces el mismo intento.
            $table->unique(['turno_id', 'intento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cola_virtual_intentos');
    }
};
