<?php

namespace App\Http\Controllers\Catalogo;

use App\Application\UseCases\Catalogo\ObtenerDetalleEventoUseCase;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class DetalleEventoController extends Controller
{
    public function __construct(
        private readonly ObtenerDetalleEventoUseCase $obtenerDetalle
    ) {
    }

    public function show(int $id): JsonResponse
    {
        $evento = $this->obtenerDetalle->execute($id);

        if ($evento === null) {
            return response()->json([
                'success' => false,
                'message' => 'El evento solicitado no está disponible.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Detalle del evento consultado correctamente.',
            'data' => $evento,
        ]);
    }
}
