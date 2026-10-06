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
        Schema::create('eventos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recinto_id')->constrained('recintos')->restrictOnDelete();
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->dateTime('fecha_evento');
            $table->string('estado')->default('borrador');
            $table->dateTime('fecha_publicacion')->nullable();

            // --- auditoría ---
            $table->foreignId('created_by')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('eventos');
    }
};
