<?php

namespace App\Domain\Abstractions\Boletos;

use App\Domain\Entities\Boletos\Boleto;

interface IBoletoRepository
{
    // Guardar un boleto nuevo o actualizar uno existente.
    public function guardar(Boleto $boleto): Boleto;

    // Buscar un boleto mediante su inventario.
    public function buscarPorInventario(int $inventarioId): ?Boleto;
}
