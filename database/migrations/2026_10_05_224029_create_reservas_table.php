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
        Schema::create('reservas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('usuarios')->restrictOnDelete();
            $table->foreignId('evento_id')->constrained('eventos')->restrictOnDelete();
            $table->string('estado')->default('activa');
            $table->dateTime('expira_en');
            $table->decimal('total', 10, 2)->default(0);

            // --- auditoría ---
            $table->foreignId('created_by')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['estado', 'expira_en']); // barrer reservas vencidas (TTL)
        });

        // completar la FK que dejamos pendiente en inventario
        Schema::table('inventario', function (Blueprint $table) {
            $table->foreign('reserva_id')->references('id')->on('reservas')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventario', function (Blueprint $table) {
            $table->dropForeign(['reserva_id']);
        });

        Schema::dropIfExists('reservas');
    }
};
