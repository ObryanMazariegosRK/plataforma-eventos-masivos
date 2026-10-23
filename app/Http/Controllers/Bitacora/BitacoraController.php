<?php

namespace App\Http\Controllers\Bitacora;

use App\Application\Abstractions\Bitacora\IBuscarEnBitacoraUseCase;
use App\Application\DTOs\Bitacora\BuscarEnBitacoraDTO;
use App\Domain\Enums\Bitacora\CategoriaBitacora;
use App\Domain\Enums\Bitacora\ResultadoBitacora;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

// Panel → "Bitácora" (solo administrador): consulta de solo lectura con filtros
class BitacoraController extends Controller
{
    public function index(Request $request, IBuscarEnBitacoraUseCase $buscar): JsonResponse
    {
        $zona = config('bitacora.zona_horaria');
        $hoy = now($zona)->format('Y-m-d');

        $datos = $request->validate([
            'categoria' => ['sometimes', 'nullable', Rule::enum(CategoriaBitacora::class)],
            'resultado' => ['sometimes', 'nullable', Rule::enum(ResultadoBitacora::class)],
            'rol' => ['sometimes', 'nullable', Rule::in(['admin', 'organizador', 'cliente', 'sin_sesion'])],
            'usuario' => ['sometimes', 'nullable', 'string', 'max:150'],
            'desde' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'before_or_equal:'.$hoy],
            'hasta' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
            'pagina' => ['sometimes', 'integer', 'min:1'],
            'por_pagina' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ], [
            'desde.before_or_equal' => 'La fecha "Desde" no puede ser futura.',
            'hasta.after_or_equal' => 'La fecha "Hasta" no puede ser anterior a "Desde".',
        ]);

        $pagina = (int) ($datos['pagina'] ?? 1);
        $porPagina = (int) ($datos['por_pagina'] ?? config('bitacora.por_pagina'));

        $resultado = $buscar->execute(new BuscarEnBitacoraDTO(
            pagina: $pagina,
            porPagina: $porPagina,
            zonaHoraria: $zona,
            categoria: $datos['categoria'] ?? null,
            resultado: $datos['resultado'] ?? null,
            rol: $datos['rol'] ?? null,
            usuario: $datos['usuario'] ?? null,
            desde: $datos['desde'] ?? null,
            hasta: $datos['hasta'] ?? null,
        ));

        return response()->json([
            'data' => $resultado['registros'],
            'meta' => [
                'pagina' => $pagina,
                'por_pagina' => $porPagina,
                'total' => $resultado['total'],
                'hay_mas' => $pagina * $porPagina < $resultado['total'],
                'zona_horaria' => $zona,
                // Para el selector de categorías del panel
                'categorias' => array_map(
                    fn (CategoriaBitacora $c) => ['valor' => $c->value, 'etiqueta' => $c->etiqueta()],
                    CategoriaBitacora::cases(),
                ),
            ],
        ]);
    }
}
