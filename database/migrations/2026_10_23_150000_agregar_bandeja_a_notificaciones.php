<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La tabla deja de ser solo la bandeja de salida de correos: cada aviso también se ve
     * dentro de Soirée (campana del cliente, sección Notificaciones del panel) y llega por push.
     *  - tipo: qué pasó (compra_confirmada, reembolso_solicitado...), para el ícono y los filtros
     *  - url: a dónde lleva al tocarlo
     *  - leida_en: cuándo la vio su destinatario (en el servidor: igual en todos sus dispositivos)
     *  - push_en: cuándo se envió el push (una sola vez aunque el correo se reintente)
     */
    public function up(): void
    {
        Schema::table('notificaciones', function (Blueprint $table) {
            $table->string('tipo', 40)->nullable()->after('canal');
            $table->string('url', 255)->nullable()->after('mensaje');
            $table->dateTime('leida_en')->nullable()->after('enviada_en');
            $table->dateTime('push_en')->nullable()->after('leida_en');
            $table->index(['usuario_id', 'leida_en']);
        });
    }

    public function down(): void
    {
        Schema::table('notificaciones', function (Blueprint $table) {
            $table->dropIndex(['usuario_id', 'leida_en']);
            $table->dropColumn(['tipo', 'url', 'leida_en', 'push_en']);
        });
    }
};
