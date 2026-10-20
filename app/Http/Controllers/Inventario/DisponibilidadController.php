<?php
namespace App\Http\Controllers\Inventario;

use App\Application\Abstractions\Inventario\IConsultarDisponibilidadUseCase;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class DisponibilidadController extends Controller
{
    // Público: el mapa de asientos se ve antes de iniciar sesión (para reservar sí hace falta)
    public function show(int $id, IConsultarDisponibilidadUseCase $consultar): JsonResponse
    {
        return response()->json($consultar->execute($id));
    }
}
