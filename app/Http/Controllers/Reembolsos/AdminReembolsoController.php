<?php

namespace App\Http\Controllers\Reembolsos;

use App\Application\Abstractions\Reembolsos\IListarReembolsosAdminUseCase;
use App\Application\Abstractions\Reembolsos\IProcesarReembolsoUseCase;
use App\Domain\Enums\Reembolsos\EstadoReembolso;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

// Panel → "Reembolsos" (solo administrador). Aprobar/rechazar: PATCH /api/reembolsos/{id}/resolver
class AdminReembolsoController extends Controller
{
    public function index(Request $request, IListarReembolsosAdminUseCase $listar): JsonResponse
    {
        $datos = $request->validate([
            'estado' => ['sometimes', 'nullable', Rule::enum(EstadoReembolso::class)],
            'pagina' => ['sometimes', 'integer', 'min:1'],
            'por_pagina' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        return response()->json(['data' => $listar->execute(
            $datos['estado'] ?? null,
            (int) ($datos['pagina'] ?? 1),
            (int) ($datos['por_pagina'] ?? config('reembolsos.admin_por_pagina')),
        )]);
    }

    // Reintenta la devolución de un reembolso aprobado cuya pasarela falló
    public function procesar(Request $request, int $id, IProcesarReembolsoUseCase $procesar): JsonResponse
    {
        $reembolso = $procesar->execute($id, (int) $request->user()->id);

        return response()->json([
            'message' => 'Devolución realizada.',
            'data' => [
                'id' => $reembolso->getId(),
                'estado' => $reembolso->getEstado()->value,
                'referencia' => $reembolso->getReferenciaExterna(),
            ],
        ]);
    }
}
