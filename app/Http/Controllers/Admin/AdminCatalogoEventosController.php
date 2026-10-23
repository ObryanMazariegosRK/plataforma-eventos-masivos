<?php

namespace App\Http\Controllers\Admin;

use App\Application\Abstractions\Admin\IBuscarEventosAdminUseCase;
use App\Domain\Enums\Recintos\EstadoEvento;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

// Panel → "Catálogo de eventos": búsqueda con filtros sobre todos los eventos (cualquier estado)
class AdminCatalogoEventosController extends Controller
{
    public function index(Request $request, IBuscarEventosAdminUseCase $buscar): JsonResponse
    {
        $datos = $request->validate([
            'nombre'     => ['sometimes', 'nullable', 'string', 'max:255'],
            'estado'     => ['sometimes', 'nullable', Rule::enum(EstadoEvento::class)],
            'recinto_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'desde'      => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'hasta'      => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'pagina'     => ['sometimes', 'integer', 'min:1'],
            'por_pagina' => ['sometimes', 'integer', 'min:1', 'max:48'],
        ]);

        $pagina = (int) ($datos['pagina'] ?? 1);
        $porPagina = (int) ($datos['por_pagina'] ?? config('catalogo.admin_eventos_por_pagina'));
        $filtros = array_intersect_key($datos, array_flip(['nombre', 'estado', 'recinto_id', 'desde', 'hasta']));

        $resultado = $buscar->execute($filtros, $pagina, $porPagina);

        return response()->json([
            'data' => $resultado['eventos'],
            'meta' => [
                'pagina'     => $pagina,
                'por_pagina' => $porPagina,
                'total'      => $resultado['total'],
                'hay_mas'    => $pagina * $porPagina < $resultado['total'],
                'por_estado' => $resultado['por_estado'],
            ],
        ]);
    }
}
