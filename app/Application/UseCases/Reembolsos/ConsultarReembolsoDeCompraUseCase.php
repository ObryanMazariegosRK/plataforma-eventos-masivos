<?php

namespace App\Application\UseCases\Reembolsos;

use App\Application\Abstractions\Reembolsos\IConsultarReembolsoDeCompraUseCase;
use App\Domain\Abstractions\Reembolsos\IReembolsoRepository;
use App\Domain\Enums\Recintos\EstadoEvento;
use App\Domain\Enums\Reservas\EstadoReserva;
use App\Domain\Exceptions\Pagos\ReservaNoEncontradaException;
use DateTimeImmutable;
use DomainException;

/** Vista previa para el cliente: cuánto le devolverían hoy, o por qué ya no se puede. */
class ConsultarReembolsoDeCompraUseCase implements IConsultarReembolsoDeCompraUseCase
{
    public function __construct(
        private readonly IReembolsoRepository $reembolsos,
        private readonly CalcularReembolsoUseCase $calculadora,
    ) {}

    public function execute(int $reservaId, int $usuarioId, DateTimeImmutable $ahora): array
    {
        $compra = $this->reembolsos->contextoDeReserva($reservaId);
        if ($compra === null || $compra['usuario_id'] !== $usuarioId) {
            throw new ReservaNoEncontradaException;
        }

        $anteriores = $this->reembolsos->buscarPorPago($compra['pago_id']);
        $ultimo = end($anteriores) ?: null;

        $calculo = null;
        $motivoNo = match (true) {
            $compra['evento_estado'] === EstadoEvento::CANCELADO->value => 'El evento fue cancelado: te devolvemos el 100 % automáticamente.',
            $ultimo !== null => 'Ya pediste el reembolso de esta compra.',
            $compra['reserva_estado'] !== EstadoReserva::CONFIRMADA->value => 'La compra no está confirmada.',
            $compra['boletos_no_disponibles'] > 0 => 'Algún boleto de esta compra ya se usó o fue anulado.',
            default => null,
        };

        if ($motivoNo === null) {
            try {
                $calculo = $this->calculadora->execute(
                    $compra['monto_pagado'],
                    new DateTimeImmutable($compra['fecha_evento']),
                    $ahora,
                    $compra['evento_reprogramado'],
                );
                $calculo['porcentaje_reembolso'] = 100 - $calculo['porcentaje_penalizacion'];
            } catch (DomainException) {
                $motivoNo = new DateTimeImmutable($compra['fecha_evento']) > $ahora
                    ? 'Faltan menos de 24 horas para la función: la política ya no permite reembolsos.'
                    : 'La función ya comenzó o terminó.';
            }
        }

        return [
            'reserva_id' => $reservaId,
            'evento' => $compra['evento'],
            'monto_pagado' => $compra['monto_pagado'],
            'puede_solicitar' => $motivoNo === null,
            'motivo_no' => $motivoNo,
            'calculo' => $calculo,
            'reembolso' => $ultimo === null ? null : [
                'id' => $ultimo->getId(),
                'estado' => $ultimo->getEstado()->value,
                'monto' => $ultimo->getMonto(),
                'motivo' => $ultimo->getMotivo(),
            ],
        ];
    }
}
