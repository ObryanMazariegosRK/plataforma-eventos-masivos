<?php

namespace App\Http\Controllers\Finanzas;

use App\Application\Abstractions\Finanzas\IObtenerFinanzasUseCase;
use App\Application\DTOs\Finanzas\ObtenerFinanzasDTO;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

// Panel → Resumen: ventas de los últimos días y rentabilidad por evento (solo administrador)
class FinanzasController extends Controller
{
    public function index(IObtenerFinanzasUseCase $finanzas): JsonResponse
    {
        return response()->json(['data' => $finanzas->execute(new ObtenerFinanzasDTO(
            ahora: new \DateTimeImmutable,
            zonaHoraria: config('finanzas.zona_horaria'),
            dias: config('finanzas.dias_ventas'),
            comisionPasarela: config('finanzas.comision_pasarela'),
        ))]);
    }
}
