<?php

namespace App\Application\UseCases\Notificaciones;

use App\Application\Abstractions\Notificaciones\IConsultarBandejaUseCase;
use App\Domain\Abstractions\Notificaciones\IBandejaRepository;

class ConsultarBandejaUseCase implements IConsultarBandejaUseCase
{
    public function __construct(private readonly IBandejaRepository $bandeja) {}

    public function execute(int $usuarioId, int $pagina, int $porPagina): array
    {
        $resultado = $this->bandeja->deUsuario($usuarioId, $pagina, $porPagina);

        return [
            'avisos' => $resultado['avisos'],
            'no_leidas' => $resultado['no_leidas'],
            'meta' => [
                'pagina' => $pagina,
                'por_pagina' => $porPagina,
                'total' => $resultado['total'],
                'hay_mas' => $pagina * $porPagina < $resultado['total'],
            ],
        ];
    }
}
