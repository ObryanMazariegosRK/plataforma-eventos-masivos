<?php

namespace App\Http\Controllers\Finanzas;

use App\Application\Abstractions\Finanzas\IGuardarCostosEventoUseCase;
use App\Application\Abstractions\Finanzas\IListarCostosEventoUseCase;
use App\Application\DTOs\Finanzas\GuardarCostosEventoDTO;
use App\Domain\Enums\Finanzas\CategoriaCosto;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

// Costos de producción de un evento: los registran el administrador y el operador.
// Las ganancias (FinanzasController) solo las ve el administrador.
class CostoEventoController extends Controller
{
    // Reglas compartidas con la creación del evento (AdminEventoController@store)
    public static function reglas(): array
    {
        return [
            'costos' => ['sometimes', 'array', 'max:'.config('finanzas.max_costos_por_evento')],
            'costos.*.categoria' => ['required', Rule::enum(CategoriaCosto::class)],
            'costos.*.concepto' => ['required', 'string', 'max:150'],
            'costos.*.monto' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999999999.99'],
        ];
    }

    public function index(int $id, IListarCostosEventoUseCase $listar): JsonResponse
    {
        return response()->json(['data' => $listar->execute($id) + ['categorias' => $this->categorias()]]);
    }

    public function guardar(Request $request, int $id, IGuardarCostosEventoUseCase $guardar): JsonResponse
    {
        $datos = $request->validate(['costos' => ['present', 'array']] + self::reglas());

        $resultado = $guardar->execute(new GuardarCostosEventoDTO(
            eventoId: $id,
            usuarioId: (int) $request->user()->id,
            costos: $datos['costos'],
            maximo: config('finanzas.max_costos_por_evento'),
        ));

        return response()->json([
            'message' => 'Costos del evento guardados.',
            'data' => $resultado,
        ]);
    }

    private function categorias(): array
    {
        return array_map(fn (CategoriaCosto $c) => ['valor' => $c->value, 'etiqueta' => $c->etiqueta()], CategoriaCosto::cases());
    }
}
