<?php

namespace App\Application\UseCases\Transacciones;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ConsultarOperacionesHistorialUseCase
{
    public function execute(array $filtros = []): LengthAwarePaginator
    {
        $consulta = DB::table('historial_operaciones')
            ->select([
                'id',
                'tipo',
                'usuario_id',
                'evento_id',
                'reserva_id',
                'inventario_id',
                'pago_id',
                'registrado_por',
                'descripcion',
                'datos',
                'created_at',
            ]);

        if (isset($filtros['usuario_id'])) {
            $consulta->where(
                'usuario_id',
                $filtros['usuario_id']
            );
        }

        if (isset($filtros['evento_id'])) {
            $consulta->where(
                'evento_id',
                $filtros['evento_id']
            );
        }

        if (isset($filtros['reserva_id'])) {
            $consulta->where(
                'reserva_id',
                $filtros['reserva_id']
            );
        }

        if (isset($filtros['tipo'])) {
            $consulta->where('tipo', $filtros['tipo']);
        }

        if (isset($filtros['fecha_desde'])) {
            $consulta->whereDate(
                'created_at',
                '>=',
                $filtros['fecha_desde']
            );
        }

        if (isset($filtros['fecha_hasta'])) {
            $consulta->whereDate(
                'created_at',
                '<=',
                $filtros['fecha_hasta']
            );
        }

        return $consulta
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($filtros['per_page'] ?? 15);
    }
}
