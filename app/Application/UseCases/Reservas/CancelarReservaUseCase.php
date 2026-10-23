<?php

namespace App\Application\UseCases\Reservas;

use App\Domain\Abstractions\Reservas\IReservaRepository;
use App\Domain\Entities\Reservas\Reserva;
use App\Domain\Enums\Inventario\EstadoAsiento;
use DomainException;
use Illuminate\Support\Facades\DB;

class CancelarReservaUseCase
{
    public function __construct(
        private readonly IReservaRepository $reservas
    ) {}

    public function execute(
        int $reservaId,
        int $usuarioId
    ): Reserva {
        if ($reservaId <= 0 || $usuarioId <= 0) {
            throw new DomainException(
                'Los identificadores no son válidos.'
            );
        }

        return DB::transaction(function () use (
            $reservaId,
            $usuarioId
        ): Reserva {

            // 1. Bloquear la reserva durante la operación.
            $registro = DB::table('reservas')
                ->where('id', $reservaId)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            if ($registro === null) {
                throw new DomainException(
                    'La reserva no existe.'
                );
            }

            // 2. Verificar que pertenezca al cliente.
            if ((int) $registro->usuario_id !== $usuarioId) {
                throw new DomainException(
                    'No tienes permiso para cancelar esta reserva.'
                );
            }

            // 3. Recuperar la entidad de dominio.
            $reserva = $this->reservas->buscarPorId($reservaId);

            if ($reserva === null) {
                throw new DomainException(
                    'La reserva no existe.'
                );
            }

            // 4. Validar y cambiar su estado.
            // La entidad impide cancelar reservas confirmadas,
            // vencidas o previamente canceladas.
            $reserva->cancelar();

            // 5. Guardar el nuevo estado.
            $this->reservas->guardar($reserva);

            // 6. Liberar únicamente los asientos bloqueados
            // que pertenecen a esta reserva.
            DB::table('inventario')
                ->where('reserva_id', $reservaId)
                ->where('estado', EstadoAsiento::BLOQUEADO->value)
                ->whereNull('deleted_at')
                ->update([
                    'estado' => EstadoAsiento::DISPONIBLE->value,
                    'reserva_id' => null,
                    'bloqueado_hasta' => null,
                    'updated_by' => $usuarioId,
                    'updated_at' => now(),
                ]);

            return $reserva;

        }, 3);
    }
}
