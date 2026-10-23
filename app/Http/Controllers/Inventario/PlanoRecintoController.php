<?php

namespace App\Http\Controllers\Inventario;

use App\Application\Abstractions\Inventario\IConsultarPlanoRecintoUseCase;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

// Formulario de evento → "Ver plano": el recinto con sus localidades y butacas
class PlanoRecintoController extends Controller
{
    public function show(int $recintoId, IConsultarPlanoRecintoUseCase $consultar): JsonResponse
    {
        return response()->json(['data' => $consultar->execute($recintoId)]);
    }
}
