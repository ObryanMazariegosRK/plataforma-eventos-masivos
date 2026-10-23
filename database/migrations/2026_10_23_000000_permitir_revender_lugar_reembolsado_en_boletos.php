<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un reembolso anula los boletos y devuelve sus lugares a la venta. Si otra persona compra
     * ese lugar, recibe un boleto nuevo para el mismo inventario_id: la regla pasa a ser
     * "un boleto por lugar en cada compra" (el anulado conserva la historia de la compra anterior).
     */
    public function up(): void
    {
        Schema::table('boletos', function (Blueprint $table) {
            // Primero el índice nuevo (también sirve a la llave foránea de inventario_id)
            $table->unique(['inventario_id', 'reserva_id']);
        });
        Schema::table('boletos', function (Blueprint $table) {
            $table->dropUnique(['inventario_id']);
        });
    }

    public function down(): void
    {
        Schema::table('boletos', function (Blueprint $table) {
            $table->unique('inventario_id');
        });
        Schema::table('boletos', function (Blueprint $table) {
            $table->dropUnique(['inventario_id', 'reserva_id']);
        });
    }
};
