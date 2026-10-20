<?php
namespace App\Http\Controllers\Reservas;

use App\Application\Abstractions\Reservas\ICrearReservaUseCase;
use App\Application\DTOs\Reservas\CrearReservaDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reservas\CrearReservaRequest;
use Illuminate\Http\JsonResponse;
use App\Application\UseCases\Reservas\CancelarReservaUseCase;
use Illuminate\Http\Request;
use DomainException;

class ReservaController extends Controller
{
    public function store(CrearReservaRequest $request, ICrearReservaUseCase $crearReserva): JsonResponse
    {
        $dto = new CrearReservaDTO(
            // el usuario sale del token, NO del cuerpo: así nadie puede reservar a nombre de otro
            usuarioId: $request->user()->id,
            eventoId: $request->integer('evento_id'),
            total: 0.0,
            ttlSegundos: config('reservas.ttl_segundos'),
            inventarioIds: array_map(
                'intval',
                $request->validated('inventario_ids', [])
            ),
            cupos: array_map(fn (array $cupo) => [
                'localidad_id' => (int) $cupo['localidad_id'],
                'cantidad'     => (int) $cupo['cantidad'],
            ], $request->validated('cupos', [])),
            maxAsientos: config('reservas.max_asientos_por_reserva'),

        );

        $reserva = $crearReserva->execute($dto);

        return response()->json([
            'id'        => $reserva->getId(),
            'estado'    => $reserva->getEstado()->value,
            'expira_en' => $reserva->getExpiraEn()->format(DATE_ATOM),
            'total'     => $reserva->getTotal(),
        ], 201);
    }

    public function destroy(
        Request $request,
        CancelarReservaUseCase $cancelarReserva,
        int $id
    ): JsonResponse {
        try {
            $reserva = $cancelarReserva->execute(
                reservaId: $id,
                usuarioId: (int) $request->user()->id
            );

            return response()->json([
                'message' => 'Reserva cancelada correctamente.',
                'id' => $reserva->getId(),
                'estado' => $reserva->getEstado()->value,
            ], 200);

        } catch (DomainException $e) {
            $estadoHttp = match ($e->getMessage()) {
                'La reserva no existe.' => 404,
                'No tienes permiso para cancelar esta reserva.' => 403,
                default => 422,
            };

            return response()->json([
                'message' => $e->getMessage(),
            ], $estadoHttp);
        }
    }

}
