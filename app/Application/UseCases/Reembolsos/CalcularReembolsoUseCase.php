<?php

namespace App\Application\UseCases\Reembolsos;

use DateTimeImmutable;
use DomainException;

class CalcularReembolsoUseCase
{
    public function execute(
        float $montoPagado,
        DateTimeImmutable $fechaEvento,
        ?DateTimeImmutable $ahora = null,
        // La función se aplazó: se devuelve el 100 % sin importar los plazos
        bool $eventoReprogramado = false
    ): array {

        if (!is_finite($montoPagado) || $montoPagado <= 0) {
            throw new DomainException(
                'El monto pagado no es válido.'
            );
        }

        if (!config('reembolsos.politica_habilitada', false)) {
            throw new DomainException(
                'La política de reembolsos aún no está configurada.'
            );
        }

        $ahora ??= new DateTimeImmutable();

        $segundosRestantes =
            $fechaEvento->getTimestamp() - $ahora->getTimestamp();

        if ($segundosRestantes <= 0) {
            throw new DomainException(
                'El evento ya comenzó o finalizó.'
            );
        }

        $horasRestantes = $segundosRestantes / 3600;

        if ($eventoReprogramado) {
            return [
                'monto_pagado' => round($montoPagado, 2),
                'penalizacion' => 0.0,
                'monto_reembolso' => round($montoPagado, 2),
                'porcentaje_penalizacion' => 0.0,
                'horas_restantes' => round($horasRestantes, 2),
                'por_reprogramacion' => true,
            ];
        }

        $tramos = config('reembolsos.tramos', []);

        if (!is_array($tramos) || empty($tramos)) {
            throw new DomainException(
                'No existen reglas de penalización configuradas.'
            );
        }

        foreach ($tramos as $tramo) {
            if (
                !is_array($tramo) ||
                !isset(
                    $tramo['horas_minimas'],
                    $tramo['penalizacion_porcentaje']
                ) ||
                !is_numeric($tramo['horas_minimas']) ||
                !is_numeric($tramo['penalizacion_porcentaje']) ||
                $tramo['horas_minimas'] < 0 ||
                $tramo['penalizacion_porcentaje'] < 0 ||
                $tramo['penalizacion_porcentaje'] > 100
            ) {
                throw new DomainException(
                    'La política de penalización contiene datos inválidos.'
                );
            }
        }

        usort(
            $tramos,
            fn ($a, $b) =>
                $b['horas_minimas'] <=> $a['horas_minimas']
        );

        foreach ($tramos as $tramo) {

            if ($horasRestantes >= $tramo['horas_minimas']) {

                $porcentaje = (float)
                    $tramo['penalizacion_porcentaje'];

                $penalizacion = round(
                    $montoPagado * $porcentaje / 100,
                    2
                );

                $reembolso = round(
                    $montoPagado - $penalizacion,
                    2
                );

                return [
                    'monto_pagado' => round($montoPagado, 2),
                    'penalizacion' => $penalizacion,
                    'monto_reembolso' => $reembolso,
                    'porcentaje_penalizacion' => $porcentaje,
                    'horas_restantes' => round($horasRestantes, 2),
                    'por_reprogramacion' => false,
                ];
            }
        }

        throw new DomainException(
            'La cancelación está fuera de los plazos permitidos.'
        );
    }
}
