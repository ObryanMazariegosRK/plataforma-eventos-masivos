<?php
namespace App\Http\Controllers\Pasarelas;

use App\Application\Abstractions\Pasarelas\IListarPasarelasUseCase;
use App\Domain\Entities\Pasarelas\Pasarela;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class PasarelaController extends Controller
{
    public function index(IListarPasarelasUseCase $listar): JsonResponse
    {
        return response()->json(array_map(fn (Pasarela $p) => [
            'id'     => $p->getId(),
            'nombre' => $p->getNombre(),
            'codigo' => $p->getCodigo(),
        ], $listar->execute()));
    }
}
