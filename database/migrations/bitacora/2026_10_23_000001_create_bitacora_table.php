<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// BITÁCORA (base eventos_bitacora): quién hizo qué y cuándo. Solo se agregan filas;
// los triggers de la migración siguiente rechazan cualquier UPDATE o DELETE.
// Sin llaves foráneas: es otra base y el registro debe sobrevivir aunque se borre el usuario.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bitacora', function (Blueprint $table) {
            $table->id();
            $table->dateTime('ocurrido_en');                        // UTC
            $table->unsignedBigInteger('usuario_id')->nullable();
            $table->string('usuario_nombre', 150)->nullable();      // copia: se conserva aunque cambie
            $table->string('usuario_correo', 150)->nullable();
            $table->string('usuario_rol', 20)->nullable();          // admin | organizador | cliente
            $table->string('categoria', 20);                        // sesion, compra, evento, usuario...
            $table->string('accion', 60);                           // ej. evento.editado
            $table->string('resultado', 10);                        // exito | fallido
            $table->string('entidad', 30)->nullable();              // evento, reserva, usuario...
            $table->string('entidad_id', 40)->nullable();
            $table->string('descripcion', 500);
            $table->json('datos_antes')->nullable();
            $table->json('datos_despues')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 300)->nullable();

            $table->index('ocurrido_en');
            $table->index(['categoria', 'ocurrido_en']);
            $table->index(['usuario_id', 'ocurrido_en']);
            $table->index(['resultado', 'ocurrido_en']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bitacora');
    }
};
