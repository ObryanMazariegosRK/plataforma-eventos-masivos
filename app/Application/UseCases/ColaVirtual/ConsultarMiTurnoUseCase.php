<?php

namespace App\Application\UseCases\ColaVirtual;

use App\Application\Abstractions\ColaVirtual\IConsultarMiTurnoUseCase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ConsultarMiTurnoUseCase implements IConsultarMiTurnoUseCase
{
    public function execute(int $eventoId, int $usuarioId): ?array
    {
        // 1. Buscar el turno correspondiente al usuario.
        $turno = DB::table('cola_virtual')
            ->where('evento_id', $eventoId)
            ->where('usuario_id', $usuarioId)
            ->whereNull('deleted_at')
            ->first();

        // El usuario todavía no tiene turno.
        if (!$turno) {
            return null;
        }

        $ahora = now();

        // 2. Calcular el estado efectivo del turno.
        // Si venció, no debe mostrarse como disponible
        // aunque el scheduler todavía no lo haya actualizado.
        $estado = $turno->estado;

        if (
            $estado === 'habilitado' &&
            (
                $turno->habilitado_hasta === null ||
                Carbon::parse($turno->habilitado_hasta)->lessThanOrEqualTo($ahora)
            )
        ) {
            $estado = 'expirado';
        }

        // 3. Contar únicamente los turnos activos
        // que tienen una posición anterior a la del usuario.
        $personasDelante = DB::table('cola_virtual')
            ->where('evento_id', $eventoId)
            ->where('posicion', '<', $turno->posicion)
            ->whereNull('deleted_at')
            ->where(function ($consulta) use ($ahora) {
                $consulta
                    ->where('estado', 'esperando')
                    ->orWhere(function ($subconsulta) use ($ahora) {
                        $subconsulta
                            ->where('estado', 'habilitado')
                            ->where('habilitado_hasta', '>', $ahora);
                    });
            })
            ->count();

        // 4. Devolver la información del turno.
        return [
            'evento_id' => (int) $turno->evento_id,
            'posicion' => (int) $turno->posicion,
            'personas_delante' => $personasDelante,
            'estado' => $estado,
            'habilitado_hasta' => $turno->habilitado_hasta
                ? Carbon::parse($turno->habilitado_hasta)->toAtomString()
                : null,
        ];
    }
}
