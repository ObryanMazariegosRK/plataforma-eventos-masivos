<?php

namespace App\Application\UseCases\Reservas;

use App\Domain\Enums\Inventario\EstadoAsiento;
use App\Domain\Enums\Reservas\EstadoReserva;
use Illuminate\Support\Facades\DB;

class ExpirarReservasVencidasUseCase
{
    public function execute(): int
    {
        $ahora = now();
        $reservasExpiradas = 0;

        // 1. Buscar reservas activas cuyo TTL ya vencio.
        DB::table('reservas')
            ->where('estado', EstadoReserva::ACTIVA->value)
            ->where('expira_en', '<=', $ahora)
            ->whereNull('deleted_at')
            ->select('id')
            ->chunkById(100, function ($reservas) use (
                $ahora,
                &$reservasExpiradas
            ) {
                foreach ($reservas as $reserva) {

                    $procesada = DB::transaction(function () use (
                        $reserva,
                        $ahora
                    ): bool {

                        // 2. Bloquear la reserva durante la operacion.
                        $registro = DB::table('reservas')
                            ->where('id', $reserva->id)
                            ->whereNull('deleted_at')
                            ->lockForUpdate()
                            ->first();

                        if ($registro === null) {
                            return false;
                        }

                        // 3. Volver a comprobar su estado y vencimiento.
                        // Evita expirar reservas confirmadas o canceladas
                        // mientras se estaba ejecutando el proceso.
                        if (
                            $registro->estado !== EstadoReserva::ACTIVA->value ||
                            $registro->expira_en > $ahora
                        ) {
                            return false;
                        }

                        // 4. Liberar los asientos de esta reserva.
                        // Nunca liberar elementos que ya fueron vendidos.
                        DB::table('inventario')
                            ->where('reserva_id', $registro->id)
                            ->where('estado', EstadoAsiento::BLOQUEADO->value)
                            ->whereNull('deleted_at')
                            ->update([
                                'estado' => EstadoAsiento::DISPONIBLE->value,
                                'reserva_id' => null,
                                'bloqueado_hasta' => null,
                                'updated_at' => now(),
                            ]);

                        // 5. Marcar la reserva como vencida.
                        DB::table('reservas')
                            ->where('id', $registro->id)
                            ->update([
                                'estado' => EstadoReserva::VENCIDA->value,
                                'updated_at' => now(),
                            ]);

                        return true;

                    }, 3);

                    if ($procesada) {
                        $reservasExpiradas++;
                    }
                }
            });

        return $reservasExpiradas;
    }
}