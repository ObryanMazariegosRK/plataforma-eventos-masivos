<?php

namespace App\Http\Controllers\Catalogo;

use App\Application\Abstractions\Catalogo\IObtenerBannerCatalogoUseCase;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class BannerController extends Controller
{
    // Público: el catálogo lo pide para el fondo de la portada (data = null → foto por defecto)
    public function show(IObtenerBannerCatalogoUseCase $obtener): JsonResponse
    {
        return response()->json(['data' => $obtener->execute()]);
    }
}
