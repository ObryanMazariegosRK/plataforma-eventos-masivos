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
        
        Schema::create('transacciones', function (Blueprint $table) {
            $table->id();
            $table->string('tipo');                 // venta / reembolso
            $table->decimal('monto', 10, 2);
            $table->foreignId('usuario_id')->constrained('usuarios')->restrictOnDelete();
            $table->foreignId('reserva_id')->nullable()->constrained('reservas')->nullOnDelete();
            $table->foreignId('pago_id')->nullable()->constrained('pagos')->nullOnDelete();
            $table->string('referencia')->nullable();
            $table->string('descripcion')->nullable();

            // Auditoría MÍNIMA: solo quién lo registró y cuándo.
            // Nada de updated_by / updated_at / deleted_at — un libro contable no se edita ni se borra.
            $table->foreignId('created_by')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['tipo', 'created_at']);
        });



    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transacciones');
    }
};
