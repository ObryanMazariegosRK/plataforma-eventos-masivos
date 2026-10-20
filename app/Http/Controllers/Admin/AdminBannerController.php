<?php

namespace App\Http\Controllers\Admin;

use App\Application\Abstractions\Catalogo\IActualizarBannerCatalogoUseCase;
use App\Application\Abstractions\Catalogo\IEliminarBannerCatalogoUseCase;
use App\Application\Abstractions\Catalogo\IObtenerBannerCatalogoUseCase;
use App\Application\DTOs\Catalogo\ActualizarBannerDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Catalogo\ActualizarBannerRequest;
use Illuminate\Http\JsonResponse;

// Panel de admin → Banner: imagen o video corto de fondo en la portada del catálogo
class AdminBannerController extends Controller
{
    public function show(IObtenerBannerCatalogoUseCase $obtener): JsonResponse
    {
        return response()->json(['data' => $obtener->execute()]);
    }

    public function update(ActualizarBannerRequest $request, IActualizarBannerCatalogoUseCase $actualizar): JsonResponse
    {
        $archivo = $request->file('archivo');

        $banner = $actualizar->execute(new ActualizarBannerDTO(
            archivo: $archivo,
            tipoMime: (string) $archivo->getMimeType(),
            usuarioId: $request->user()->id,
        ));

        return response()->json(['message' => 'Banner actualizado.', 'data' => $banner]);
    }

    public function destroy(IEliminarBannerCatalogoUseCase $eliminar): JsonResponse
    {
        $eliminar->execute();

        return response()->json(['message' => 'Banner quitado: el catálogo usa la foto por defecto.', 'data' => null]);
    }
}
