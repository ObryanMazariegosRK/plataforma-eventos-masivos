<?php

namespace App\Application\UseCases\Transacciones;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ConsultarHistorialUseCase
{
    public function execute(array $filtros = []): LengthAwarePaginator
    {
        $consulta = DB::table('transacciones as t')
            ->leftJoin(
                'reservas as r',
                't.reserva_id',
                '=',
                'r.id'
            )
            ->select(
                't.id',
                't.tipo',
                't.monto',
                't.usuario_id',
                't.reserva_id',
                't.pago_id',
                't.referencia',
                't.descripcion',
                't.created_by',
                't.created_at',
                'r.evento_id'
            );

        // Filtrar por usuario.
        if (!empty($filtros['usuario_id'])) {
            $consulta->where(
                't.usuario_id',
                $filtros['usuario_id']
            );
        }

        // Filtrar por evento asociado a la reserva.
        if (!empty($filtros['evento_id'])) {
            $consulta->where(
                'r.evento_id',
                $filtros['evento_id']
            );
        }

        // Fecha inicial.
        if (!empty($filtros['fecha_desde'])) {
            $consulta->whereDate(
                't.created_at',
                '>=',
                $filtros['fecha_desde']
            );
        }

        // Fecha final.
        if (!empty($filtros['fecha_hasta'])) {
            $consulta->whereDate(
                't.created_at',
                '<=',
                $filtros['fecha_hasta']
            );
        }

        // Filtrar por tipo de movimiento.
        if (!empty($filtros['tipo'])) {
            $consulta->where(
                't.tipo',
                $filtros['tipo']
            );
        }

        return $consulta
            ->orderByDesc('t.created_at')
            ->orderByDesc('t.id')
            ->paginate($filtros['per_page'] ?? 15);
    }
}
