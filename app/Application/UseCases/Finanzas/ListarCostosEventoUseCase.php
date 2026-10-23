<?php

namespace App\Application\UseCases\Finanzas;

use App\Application\Abstractions\Finanzas\IListarCostosEventoUseCase;
use App\Domain\Abstractions\Finanzas\ICostoEventoRepository;
use App\Domain\Entities\Finanzas\CostoEvento;
use App\Domain\Exceptions\Finanzas\EventoSinCostosEditablesException;

class ListarCostosEventoUseCase implements IListarCostosEventoUseCase
{
    public function __construct(private ICostoEventoRepository $costos) {}

    public function execute(int $eventoId): array
    {
        if (! $this->costos->existeEvento($eventoId)) {
            throw new EventoSinCostosEditablesException($eventoId);
        }

        $lista = $this->costos->deEvento($eventoId);

        return [
            'costos' => array_map(fn (CostoEvento $c) => [
                'id' => $c->getId(),
                'categoria' => $c->getCategoria()->value,
                'categoria_etiqueta' => $c->getCategoria()->etiqueta(),
                'concepto' => $c->getConcepto(),
                'monto' => $c->getMonto(),
            ], $lista),
            'total' => round(array_sum(array_map(fn (CostoEvento $c) => $c->getMonto(), $lista)), 2),
        ];
    }
}
