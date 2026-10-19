<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('eventos', 'imagen_url')) {
            Schema::table('eventos', function (Blueprint $table) {
                $table->string('imagen_url', 2048)->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('eventos', 'imagen_url')) {
            Schema::table('eventos', function (Blueprint $table) {
                $table->dropColumn('imagen_url');
            });
        }
    }
};
