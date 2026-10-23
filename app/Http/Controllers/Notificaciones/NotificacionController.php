<?php

namespace App\Http\Controllers\Notificaciones;

use App\Application\Abstractions\Notificaciones\IConsultarBandejaUseCase;
use App\Application\Abstractions\Notificaciones\IMarcarAvisosLeidosUseCase;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// Los avisos de quien inició sesión: campana de la tienda y sección Notificaciones del panel
class NotificacionController extends Controller
{
    public function index(Request $request, IConsultarBandejaUseCase $consultar): JsonResponse
    {
        $datos = $request->validate([
            'pagina' => ['sometimes', 'integer', 'min:1'],
            'por_pagina' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        return response()->json(['data' => $consultar->execute(
            (int) $request->user()->id,
            (int) ($datos['pagina'] ?? 1),
            (int) ($datos['por_pagina'] ?? config('notificaciones.por_pagina')),
        )]);
    }

    public function leer(Request $request, int $id, IMarcarAvisosLeidosUseCase $marcar): JsonResponse
    {
        return response()->json(['data' => ['no_leidas' => $marcar->execute((int) $request->user()->id, $id)]]);
    }

    public function leerTodas(Request $request, IMarcarAvisosLeidosUseCase $marcar): JsonResponse
    {
        return response()->json(['data' => ['no_leidas' => $marcar->execute((int) $request->user()->id, null)]]);
    }
}
