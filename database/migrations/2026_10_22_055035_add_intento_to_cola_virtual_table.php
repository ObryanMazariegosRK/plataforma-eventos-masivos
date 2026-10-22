<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cola_virtual', function (Blueprint $table) {
            $table->unsignedTinyInteger('intento')
                ->default(1);
        });
    }

    public function down(): void
    {
        Schema::table('cola_virtual', function (Blueprint $table) {
            $table->dropColumn('intento');
        });
    }
};
