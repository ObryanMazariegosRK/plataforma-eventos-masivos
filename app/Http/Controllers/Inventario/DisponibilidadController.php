<?php
namespace App\Http\Controllers\Inventario;

use App\Application\Abstractions\Inventario\IConsultarDisponibilidadUseCase;
use App\Application\Abstractions\Inventario\IConsultarHuellaDisponibilidadUseCase;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class DisponibilidadController extends Controller
{
    // Público: el mapa de asientos se ve antes de iniciar sesión (para reservar sí hace falta)
    public function show(int $id, IConsultarDisponibilidadUseCase $consultar): JsonResponse
    {
        return response()->json($consultar->execute($id));
    }

    // Huella del estado de los lugares: el mapa la pide cada ~1.5 s y solo descarga show() si cambió.
    // Se guarda 1 s en caché: aunque cientos de personas miren el mismo evento, la base la calcula
    // como mucho una vez por segundo.
    public function version(int $id, IConsultarHuellaDisponibilidadUseCase $consultar): JsonResponse
    {
        $huella = Cache::remember("disponibilidad:huella:{$id}", 1, fn () => $consultar->execute($id));

        return response()->json(['version' => $huella])->header('Cache-Control', 'no-store');
    }
}
