<?php

namespace App\Http\Controllers\Transacciones;

use App\Application\UseCases\Transacciones\ConsultarHistorialUseCase;
use App\Domain\Enums\Auth\RolUsuario;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransaccionController extends Controller
{
    public function index(
        Request $request,
        ConsultarHistorialUseCase $consultarHistorial
    ): JsonResponse {

        // Solo el administrador puede consultar
        // el historial financiero completo.
        if ($request->user()?->rol !== RolUsuario::ADMIN) {
            return response()->json([
                'mensaje' => 'No tienes permisos para consultar el historial.'
            ], 403);
        }

        $filtros = $request->validate([
            'usuario_id' => 'sometimes|integer|min:1',
            'evento_id' => 'sometimes|integer|min:1',
            'fecha_desde' => 'sometimes|date_format:Y-m-d',
            'fecha_hasta' => 'sometimes|date_format:Y-m-d|after_or_equal:fecha_desde',
            'tipo' => 'sometimes|in:venta,reembolso',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $historial = $consultarHistorial->execute($filtros);

        return response()->json([
            'mensaje' => 'Historial consultado correctamente.',
            'historial' => $historial,
        ]);
    }


    public function operaciones(
        \Illuminate\Http\Request $request,
        \App\Application\UseCases\Transacciones\ConsultarOperacionesHistorialUseCase $consultar
    ): \Illuminate\Http\JsonResponse {

        if (
            $request->user()?->rol !==
            \App\Domain\Enums\Auth\RolUsuario::ADMIN
        ) {
            return response()->json([
                'mensaje' => 'No tienes permisos para consultar este historial.'
            ], 403);
        }

        $tipos = array_column(
            \App\Domain\Enums\Transacciones\TipoOperacionHistorial::cases(),
            'value'
        );

        $filtros = $request->validate([
            'usuario_id' => 'sometimes|integer|min:1',
            'evento_id' => 'sometimes|integer|min:1',
            'reserva_id' => 'sometimes|integer|min:1',
            'tipo' => [
                'sometimes',
                \Illuminate\Validation\Rule::in($tipos)
            ],
            'fecha_desde' => 'sometimes|date_format:Y-m-d',
            'fecha_hasta' => 'sometimes|date_format:Y-m-d|after_or_equal:fecha_desde',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        return response()->json([
            'mensaje' => 'Historial de operaciones consultado correctamente.',
            'historial' => $consultar->execute($filtros),
        ]);
    }

}
