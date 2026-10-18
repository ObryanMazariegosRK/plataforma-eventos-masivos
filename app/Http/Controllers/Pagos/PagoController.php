<?php
namespace App\Http\Controllers\Pagos;

use App\Application\Abstractions\Pagos\IConfirmarPagoUseCase;
use App\Application\Abstractions\Pagos\IConsultarPagoUseCase;
use App\Application\Abstractions\Pagos\IIniciarPagoUseCase;
use App\Application\DTOs\Pagos\ConfirmarPagoDTO;
use App\Application\DTOs\Pagos\IniciarPagoDTO;
use App\Domain\Entities\Pagos\Pago;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pagos\IniciarPagoRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PagoController extends Controller
{
    // Paso 1: crea el pago y devuelve la URL de la pasarela
    public function iniciar(IniciarPagoRequest $request, IIniciarPagoUseCase $iniciar): JsonResponse
    {
        $resultado = $iniciar->execute(new IniciarPagoDTO(
            usuarioId: $request->user()->id,
            reservaId: $request->integer('reserva_id'),
            codigoPasarela: $request->validated('pasarela'),
            moneda: config('pagos.moneda'),
            urlRetorno: route('pagos.retorno'),
            urlCancelacion: route('pagos.cancelado'),
        ));

        return response()->json([
            'pago_id'        => $resultado->pagoId,
            'url_aprobacion' => $resultado->urlAprobacion,
        ], 201);
    }

    // Paso 3: la persona volvió de la pasarela → se cobra
    public function confirmar(Request $request, int $id, IConfirmarPagoUseCase $confirmar): JsonResponse
    {
        $pago = $confirmar->execute(new ConfirmarPagoDTO(
            usuarioId: $request->user()->id,
            pagoId: $id,
        ));

        return response()->json($this->pagoJson($pago));
    }

    public function mostrar(Request $request, int $id, IConsultarPagoUseCase $consultar): JsonResponse
    {
        return response()->json($this->pagoJson($consultar->execute($request->user()->id, $id)));
    }

    private function pagoJson(Pago $p): array
    {
        return [
            'id'         => $p->getId(),
            'reserva_id' => $p->getReservaId(),
            'monto'      => $p->getMonto(),
            'moneda'     => config('pagos.moneda'),
            'estado'     => $p->getEstado()->value,
            'intento'    => $p->getIntento(),
            'referencia' => $p->getReferenciaExterna(),
        ];
    }
}
