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
        Schema::create('asientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('localidad_id')->constrained('localidades')->cascadeOnDelete();
            $table->string('fila', 10);
            $table->unsignedInteger('numero');
            $table->decimal('pos_x', 8, 2)->nullable();
            $table->decimal('pos_y', 8, 2)->nullable();

            // --- auditoría ---
            $table->foreignId('created_by')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // no puede haber dos asientos con misma fila y número en la misma localidad
            $table->unique(['localidad_id', 'fila', 'numero']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asientos');
    }
};
