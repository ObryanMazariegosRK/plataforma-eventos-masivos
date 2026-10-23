<?php

namespace App\Application\Abstractions\Finanzas;

interface IListarCostosEventoUseCase
{
    // ['costos' => [{id, categoria, categoria_etiqueta, concepto, monto}], 'total' => float]
    public function execute(int $eventoId): array;
}
