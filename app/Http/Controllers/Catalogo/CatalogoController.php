<?php

namespace App\Http\Controllers\Catalogo;

use App\Http\Controllers\Controller;
use App\Application\Abstractions\Catalogo\IListarEventosUseCase;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class CatalogoController extends Controller
{
    public function __construct(
        private readonly IListarEventosUseCase $listarEventos
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        // Validar los filtros recibidos
        $filtros = $request->validate([
            'nombre' => 'sometimes|nullable|string|max:255',
            'ciudad' => 'sometimes|nullable|string|max:255',
            'fecha'  => 'sometimes|nullable|date_format:Y-m-d',
        ]);

        // Ejecutar el caso de uso
        $eventos = $this->listarEventos->execute($filtros);

        // Devolver los eventos en formato JSON
        return response()->json([
            'success' => true,
            'message' => 'Eventos consultados correctamente',
            'data' => $eventos,
        ]);
    }
}
