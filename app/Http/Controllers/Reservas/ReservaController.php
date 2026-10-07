<?php
namespace App\Http\Controllers\Reservas;

use App\Application\Abstractions\Reservas\ICrearReservaUseCase;
use App\Application\DTOs\Reservas\CrearReservaDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reservas\CrearReservaRequest;
use Illuminate\Http\JsonResponse;

class ReservaController extends Controller
{
    public function store(CrearReservaRequest $request, ICrearReservaUseCase $crearReserva): JsonResponse
    {
        $dto = new CrearReservaDTO(
            usuarioId: $request->integer('usuario_id'),
            eventoId: $request->integer('evento_id'),
            total: $request->float('total'),
            ttlSegundos: config('reservas.ttl_segundos'),
        );

        $reserva = $crearReserva->execute($dto);

        return response()->json([
            'id'        => $reserva->getId(),
            'estado'    => $reserva->getEstado()->value,
            'expira_en' => $reserva->getExpiraEn()->format(DATE_ATOM),
            'total'     => $reserva->getTotal(),
        ], 201);
    }
}
