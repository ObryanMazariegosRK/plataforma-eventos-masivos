<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Costos de producción de cada evento (alquiler, artistas, producción...). Con ellos y los
// pagos aprobados el panel calcula la ganancia de cada evento y su punto de equilibrio.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('costos_evento', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evento_id')->constrained('eventos')->restrictOnDelete();
            $table->string('categoria', 20);          // recinto | artistas | produccion | publicidad | personal | otros
            $table->string('concepto', 150);
            $table->decimal('monto', 12, 2);

            // --- auditoría ---
            $table->foreignId('created_by')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['evento_id', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('costos_evento');
    }
};
