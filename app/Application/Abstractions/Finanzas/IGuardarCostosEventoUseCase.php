<?php

namespace App\Application\Abstractions\Finanzas;

use App\Application\DTOs\Finanzas\GuardarCostosEventoDTO;

interface IGuardarCostosEventoUseCase
{
    // Reemplaza los costos del evento por la lista recibida y la devuelve como ListarCostosEvento
    public function execute(GuardarCostosEventoDTO $dto): array;
}
