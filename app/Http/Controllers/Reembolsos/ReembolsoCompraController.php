<?php

namespace App\Http\Controllers\Reembolsos;

use App\Application\Abstractions\Reembolsos\IConsultarReembolsoDeCompraUseCase;
use App\Application\Abstractions\Reembolsos\ISolicitarReembolsoDeCompraUseCase;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// Mis compras → "Solicitar reembolso": vista previa con la política y la solicitud
class ReembolsoCompraController extends Controller
{
    public function show(Request $request, int $id, IConsultarReembolsoDeCompraUseCase $consultar): JsonResponse
    {
        return response()->json(['data' => $this->vista($request, $id, $consultar)]);
    }

    public function store(
        Request $request,
        int $id,
        ISolicitarReembolsoDeCompraUseCase $solicitar,
        IConsultarReembolsoDeCompraUseCase $consultar,
    ): JsonResponse {
        $datos = $request->validate([
            'motivo' => ['nullable', 'string', 'max:255'],
        ]);

        $solicitar->execute($id, (int) $request->user()->id, $datos['motivo'] ?? null);

        return response()->json([
            'message' => 'Recibimos tu solicitud. Te avisaremos por correo cuando la revisemos.',
            'data' => $this->vista($request, $id, $consultar),
        ], 201);
    }

    private function vista(Request $request, int $id, IConsultarReembolsoDeCompraUseCase $consultar): array
    {
        return $consultar->execute($id, (int) $request->user()->id, new \DateTimeImmutable) + [
            // La tabla que ve el cliente: desde cuántas horas antes, qué porcentaje se devuelve
            'politica' => collect(config('reembolsos.tramos'))
                ->sortByDesc('horas_minimas')
                ->map(fn ($t) => ['horas_minimas' => $t['horas_minimas'], 'porcentaje_reembolso' => 100 - $t['penalizacion_porcentaje']])
                ->values()
                ->all(),
        ];
    }
}
