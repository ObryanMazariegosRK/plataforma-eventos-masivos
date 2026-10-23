<?php

namespace App\Application\UseCases\Boletos;

use App\Application\Abstractions\Boletos\IConsultarBoletoEscaneadoUseCase;
use App\Application\DTOs\Boletos\ConsultarBoletoEscaneadoDTO;
use App\Domain\Abstractions\Boletos\IConsultaBoletoRepository;
use DateTimeImmutable;
use DateTimeZone;

class ConsultarBoletoEscaneadoUseCase implements IConsultarBoletoEscaneadoUseCase
{
    public function __construct(private readonly IConsultaBoletoRepository $consulta) {}

    public function execute(ConsultarBoletoEscaneadoDTO $datos): array
    {
        // Sirve el código solo (QR antiguos o escrito a mano) o la URL completa del QR
        $codigo = preg_match('/([a-f0-9]{64})\s*$/i', $datos->codigo, $m) ? strtolower($m[1]) : null;
        $boleto = $codigo === null ? null : $this->consulta->porCodigo($codigo);

        if (! $datos->esPersonal) {
            return $boleto !== null && $boleto['usuario_id'] === $datos->usuarioId
                ? ['vista' => 'propietario', 'reserva_id' => $boleto['reserva_id'], 'boleto_id' => $boleto['boleto_id']]
                : ['vista' => 'tienda'];
        }

        if ($boleto === null) {
            return ['vista' => 'personal', 'encontrado' => false, 'puede_entrar' => false,
                'motivo' => 'Este código no corresponde a ningún boleto de Soirée.'];
        }

        $zona = new DateTimeZone($datos->zonaHoraria);
        $diaFuncion = (new DateTimeImmutable($boleto['evento']['fecha']))->setTimezone($zona)->format('Y-m-d');
        $hoy = $datos->ahora->setTimezone($zona)->format('Y-m-d');

        $motivo = match (true) {
            $boleto['estado'] === 'usado' => 'Este boleto ya se usó para entrar.',
            $boleto['estado'] === 'anulado' => 'Boleto anulado: la compra se reembolsó o la función se canceló.',
            $boleto['evento']['estado'] === 'cancelado' => 'La función fue cancelada.',
            $boleto['reserva_estado'] !== 'confirmada' || ! $boleto['lugar_vendido'] => 'La compra de este boleto ya no es válida.',
            $diaFuncion < $hoy => 'Esta función ya pasó.',
            default => null,
        };

        return [
            'vista' => 'personal',
            'encontrado' => true,
            'codigo' => $codigo,
            'puede_entrar' => $motivo === null,
            'motivo' => $motivo,
            // Válido pero de otro día: se avisa antes de dejar pasar
            'es_hoy' => $diaFuncion === $hoy,
            'boleto' => [
                'id' => $boleto['boleto_id'],
                'estado' => $boleto['estado'],
                'usado_en' => $boleto['usado_en'],
                'titular' => $boleto['titular'],
            ],
            'evento' => $boleto['evento'],
            'lugar' => $boleto['lugar'],
        ];
    }
}
