<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('inventario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->constrained('eventos')->cascadeOnDelete();
            $table->foreignId('localidad_id')->constrained('localidades')->restrictOnDelete();
            $table->foreignId('asiento_id')->nullable()->constrained('asientos')->restrictOnDelete();
            $table->string('estado')->default('disponible');
            $table->unsignedBigInteger('reserva_id')->nullable(); // FK a 'reservas' se añade cuando exista esa tabla
            $table->dateTime('bloqueado_hasta')->nullable();

            // --- auditoría ---
            $table->foreignId('created_by')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // un asiento numerado solo puede existir una vez por evento
            $table->unique(['evento_id', 'asiento_id']);
            // índice para que el job de TTL barra rápido los bloqueos vencidos
            $table->index(['estado', 'bloqueado_hasta']);
        });


    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventario');
    }
};
