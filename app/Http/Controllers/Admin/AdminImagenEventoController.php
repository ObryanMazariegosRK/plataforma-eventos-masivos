<?php

namespace App\Http\Controllers\Admin;

use App\Application\Abstractions\Admin\ISubirImagenEventoUseCase;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// Formulario de evento → fotos: se suben al elegirlas y el formulario guarda la URL
class AdminImagenEventoController extends Controller
{
    public function store(Request $request, ISubirImagenEventoUseCase $subir): JsonResponse
    {
        $request->validate([
            'imagen' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:'.(config('catalogo.imagen_evento_max_mb') * 1024)],
        ], [
            'imagen.mimes' => 'La foto debe ser JPG, PNG o WebP.',
            'imagen.max' => 'La foto no puede pesar más de '.config('catalogo.imagen_evento_max_mb').' MB.',
        ]);

        return response()->json(['data' => ['url' => $subir->execute($request->file('imagen'))]], 201);
    }
}
