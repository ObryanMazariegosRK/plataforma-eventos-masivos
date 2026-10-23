<?php

namespace App\Application\UseCases\Transacciones;

use App\Domain\Abstractions\Transacciones\ITransaccionRepository;
use App\Domain\Entities\Transacciones\Transaccion;
use App\Domain\Enums\Transacciones\TipoTransaccion;
use DomainException;

class RegistrarTransaccionUseCase
{
    public function __construct(
        private readonly ITransaccionRepository $transacciones
    ) {}

    public function execute(
        TipoTransaccion $tipo,
        float $monto,
        int $usuarioId,
        ?int $reservaId = null,
        ?int $pagoId = null,
        ?string $referencia = null,
        ?string $descripcion = null,
        ?int $registradoPor = null
    ): Transaccion {

        // Validar que los identificadores sean correctos.
        if ($usuarioId <= 0) {
            throw new DomainException(
                'El usuario de la transacción no es válido.'
            );
        }

        if ($reservaId !== null && $reservaId <= 0) {
            throw new DomainException(
                'La reserva de la transacción no es válida.'
            );
        }

        if ($pagoId !== null && $pagoId <= 0) {
            throw new DomainException(
                'El pago de la transacción no es válido.'
            );
        }

        if ($registradoPor !== null && $registradoPor <= 0) {
            throw new DomainException(
                'El usuario que registra la transacción no es válido.'
            );
        }

        // Crear una entidad nueva.
        // La entidad también valida que el monto sea positivo.
        $transaccion = new Transaccion(
            id: null,
            tipo: $tipo,
            monto: $monto,
            usuarioId: $usuarioId,
            reservaId: $reservaId,
            pagoId: $pagoId,
            referencia: $referencia,
            descripcion: $descripcion
        );

        // Guardar el movimiento en el historial.
        return $this->transacciones->registrar(
            $transaccion,
            $registradoPor
        );
    }
}
