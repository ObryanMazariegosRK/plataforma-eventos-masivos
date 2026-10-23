
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agregar dos imágenes adicionales a los eventos.
     */
    public function up(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->string('imagen_url_2', 2048)
                ->nullable()
                ->after('imagen_url');

            $table->string('imagen_url_3', 2048)
                ->nullable()
                ->after('imagen_url_2');
        });
    }

    /**
     * Revertir únicamente las columnas nuevas.
     */
    public function down(): void
    {
        Schema::table('eventos', function (Blueprint $table) {
            $table->dropColumn([
                'imagen_url_2',
                'imagen_url_3',
            ]);
        });
    }
};
