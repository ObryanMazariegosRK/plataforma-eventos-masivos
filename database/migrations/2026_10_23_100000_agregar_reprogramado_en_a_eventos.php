<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cuándo se aplazó la función por última vez (null = nunca). Si se aplazó, quien compró
     * puede pedir el reembolso del 100 % hasta que empiece, sin importar los plazos de la política.
     */
    public function up(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->dateTime('reprogramado_en')->nullable()->after('fecha_evento');
        });
    }

    public function down(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->dropColumn('reprogramado_en');
        });
    }
};
