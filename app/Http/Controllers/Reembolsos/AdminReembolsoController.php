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
            'periodo' => ['sometimes', 'nullable', Rule::in(array_keys(config('reembolsos.periodos')))],
            'pagina' => ['sometimes', 'integer', 'min:1'],
            'por_pagina' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        // "Hoy" = desde las 00:00 de Guatemala; "7 días" = hoy y los 6 anteriores, etc.
        $desde = empty($datos['periodo']) ? null : now(config('reembolsos.zona_horaria'))
            ->startOfDay()
            ->subDays(config('reembolsos.periodos')[$datos['periodo']] - 1)
            ->utc()
            ->toDateTimeImmutable();

        return response()->json(['data' => $listar->execute(
            $datos['estado'] ?? null,
            $desde,
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
