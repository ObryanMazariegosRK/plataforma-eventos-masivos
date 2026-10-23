<?php

namespace App\Application\Abstractions\Boletos;

use App\Application\DTOs\Boletos\ConsultarBoletoEscaneadoDTO;

interface IConsultarBoletoEscaneadoUseCase
{
    /**
     * Qué ve quien escanea un QR:
     *  - personal (admin u operador): {vista: 'personal', encontrado, boleto…, puede_entrar, motivo, es_hoy}
     *  - dueño del boleto: {vista: 'propietario', reserva_id, boleto_id}
     *  - cualquier otra persona: {vista: 'tienda'} (no se revela nada del boleto)
     */
    public function execute(ConsultarBoletoEscaneadoDTO $datos): array;
}
