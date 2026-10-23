<?php

namespace App\Application\UseCases\Reembolsos;

use App\Application\Abstractions\Reembolsos\IListarReembolsosAdminUseCase;
use App\Domain\Abstractions\Reembolsos\IReembolsoRepository;

class ListarReembolsosAdminUseCase implements IListarReembolsosAdminUseCase
{
    // Orden del flujo: lo que espera una decisión primero
    private const ESTADOS = [
        'solicitado' => 'Por revisar',
        'aprobado' => 'Devolución pendiente',
        'procesado' => 'Devuelto',
        'rechazado' => 'Rechazado',
    ];

    public function __construct(private readonly IReembolsoRepository $reembolsos) {}

    public function execute(?string $estado, ?\DateTimeImmutable $desde, int $pagina, int $porPagina): array
    {
        $resultado = $this->reembolsos->listar($estado, $desde, $pagina, $porPagina);

        return [
            'reembolsos' => $resultado['reembolsos'],
            'por_estado' => array_map(
                fn ($clave, $etiqueta) => ['estado' => $clave, 'etiqueta' => $etiqueta, 'total' => $resultado['por_estado'][$clave] ?? 0],
                array_keys(self::ESTADOS),
                self::ESTADOS,
            ),
            'meta' => [
                'pagina' => $pagina,
                'por_pagina' => $porPagina,
                'total' => $resultado['total'],
                'hay_mas' => $pagina * $porPagina < $resultado['total'],
            ],
        ];
    }
}
