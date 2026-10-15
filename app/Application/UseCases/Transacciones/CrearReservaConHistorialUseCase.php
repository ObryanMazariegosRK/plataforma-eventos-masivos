<?php

namespace App\Application\UseCases\Transacciones;

use App\Application\Abstractions\Reservas\ICrearReservaUseCase;
use App\Application\DTOs\Reservas\CrearReservaDTO;
use App\Application\UseCases\Reservas\CrearReservaUseCase;
use App\Domain\Entities\Reservas\Reserva;
use App\Domain\Enums\Transacciones\TipoOperacionHistorial;
use Illuminate\Support\Facades\DB;

class CrearReservaConHistorialUseCase implements ICrearReservaUseCase
{
    public function __construct(
        private readonly CrearReservaUseCase $crearReserva,
        private readonly RegistrarOperacionHistorialUseCase $registrarHistorial
    ) {}

    public function execute(CrearReservaDTO $dto): Reserva
    {
        return DB::transaction(function () use ($dto) {

            // 1. Ejecutar el caso de uso original.
            $reserva = $this->crearReserva->execute($dto);

            // 2. Obtener el ID generado.
            $reservaId = $reserva->getId();

            if ($reservaId === null) {
                throw new \DomainException(
                    'La reserva no tiene un identificador válido.'
                );
            }

            // 3. Registrar la creación en el historial.
            $this->registrarHistorial->execute(
                tipo: TipoOperacionHistorial::RESERVA_CREADA,
                claveIdempotencia: 'reserva-creada-' . $reservaId,
                usuarioId: $reserva->getUsuarioId(),
                eventoId: $reserva->getEventoId(),
                reservaId: $reservaId,
                registradoPor: $reserva->getUsuarioId(),
                descripcion: 'Reserva creada por el cliente',
                datos: [
                    'estado' => $reserva->getEstado()->value,
                    'total' => $reserva->getTotal(),
                    'expira_en' => $reserva->getExpiraEn()->format(DATE_ATOM),
                ]
            );

            // 4. Devolver la reserva creada.
            return $reserva;

        }, 3);
    }
}
