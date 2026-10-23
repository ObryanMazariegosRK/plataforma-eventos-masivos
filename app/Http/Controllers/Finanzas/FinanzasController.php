<?php

namespace App\Http\Controllers\Finanzas;

use App\Application\Abstractions\Finanzas\IObtenerFinanzasUseCase;
use App\Application\DTOs\Finanzas\ObtenerFinanzasDTO;
use App\Http\Controllers\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

// Panel → Resumen: ventas, ganancias e historial de eventos del período (solo administrador)
class FinanzasController extends Controller
{
    private const MAX_DIAS = 366;

    public function index(Request $request, IObtenerFinanzasUseCase $finanzas): JsonResponse
    {
        $datos = $request->validate([
            'desde' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'hasta' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ], [
            'hasta.after_or_equal' => 'La fecha "Hasta" no puede ser anterior a "Desde".',
        ]);

        if (! empty($datos['desde']) && ! empty($datos['hasta'])
            && CarbonImmutable::parse($datos['desde'])->diffInDays(CarbonImmutable::parse($datos['hasta'])) >= self::MAX_DIAS) {
            throw ValidationException::withMessages(['hasta' => ['Elige un período de hasta un año.']]);
        }

        return response()->json(['data' => $finanzas->execute(new ObtenerFinanzasDTO(
            ahora: now()->toDateTimeImmutable(),
            zonaHoraria: config('finanzas.zona_horaria'),
            desde: $datos['desde'] ?? null,
            hasta: $datos['hasta'] ?? null,
            diasPorDefecto: config('finanzas.dias_ventas'),
            comisionPasarela: config('finanzas.comision_pasarela'),
        ))]);
    }
}
