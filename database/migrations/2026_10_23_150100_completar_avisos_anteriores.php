<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Los correos enviados antes de la bandeja: se les pone tipo y enlace según su asunto, y los
     * ya enviados cuentan como leídos (llegaron por correo; no deben aparecer todos como nuevos).
     */
    public function up(): void
    {
        $tipos = [
            'Confirmación de compra' => ['compra_confirmada', '/mis-compras'],
            'Recordatorio de evento:%' => ['recordatorio', '/mis-compras'],
            'Cambio de fecha:%' => ['cambio_fecha', '/mis-compras'],
            'Evento cancelado:%' => ['evento_cancelado', '/mis-compras'],
            'Reembolso realizado' => ['reembolso_procesado', '/mis-compras'],
            'Solicitud de reembolso rechazada' => ['reembolso_rechazado', '/mis-compras'],
        ];
        foreach ($tipos as $asunto => [$tipo, $url]) {
            DB::table('notificaciones')->whereNull('tipo')->where('asunto', 'like', $asunto)->update(['tipo' => $tipo, 'url' => $url]);
        }

        DB::table('notificaciones')->whereNull('leida_en')->whereNotNull('enviada_en')->update(['leida_en' => DB::raw('enviada_en')]);
    }

    public function down(): void
    {
        // Solo completa datos: no hay nada que deshacer
    }
};
