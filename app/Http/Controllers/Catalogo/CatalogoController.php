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
            // Paginación opcional (la cartelera pide de 6 en 6); sin "pagina" se devuelven todos
            'pagina'     => 'sometimes|integer|min:1',
            'por_pagina' => 'sometimes|integer|min:1|max:48',
        ]);

        $pagina = isset($filtros['pagina']) ? (int) $filtros['pagina'] : null;
        $porPagina = $pagina === null ? null : (int) ($filtros['por_pagina'] ?? config('catalogo.eventos_por_pagina'));
        unset($filtros['pagina'], $filtros['por_pagina']);

        // Ejecutar el caso de uso
        $resultado = $this->listarEventos->execute($filtros, $pagina, $porPagina);

        // Devolver los eventos en formato JSON (meta solo si se pidió una página)
        return response()->json([
            'success' => true,
            'message' => 'Eventos consultados correctamente',
            'data' => $resultado['eventos'],
        ] + ($pagina === null ? [] : [
            'meta' => [
                'pagina'     => $pagina,
                'por_pagina' => $porPagina,
                'total'      => $resultado['total'],
                'hay_mas'    => $pagina * $porPagina < $resultado['total'],
            ],
        ]));
    }
}
