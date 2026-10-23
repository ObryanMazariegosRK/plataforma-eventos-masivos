<?php
namespace App\Application\UseCases\Admin;

use App\Application\Abstractions\Admin\IBuscarEventosAdminUseCase;
use App\Domain\Abstractions\Admin\ICatalogoEventosAdminRepository;
use App\Domain\Enums\Recintos\EstadoEvento;

class BuscarEventosAdminUseCase implements IBuscarEventosAdminUseCase
{
    private const ETIQUETAS = [
        'borrador'   => 'Borrador',
        'publicado'  => 'Publicado',
        'agotado'    => 'Agotado',
        'finalizado' => 'Finalizado',
        'cancelado'  => 'Cancelado',
    ];

    public function __construct(private ICatalogoEventosAdminRepository $eventos) {}

    public function execute(array $filtros, int $pagina, int $porPagina): array
    {
        $conteo = $this->eventos->contarPorEstado();

        return [
            'eventos' => $this->eventos->buscar($filtros, $porPagina, ($pagina - 1) * $porPagina),
            'total'   => $this->eventos->contar($filtros),
            // Todos los estados en orden lógico, también los que están en 0
            'por_estado' => array_map(fn (EstadoEvento $estado) => [
                'estado'   => $estado->value,
                'etiqueta' => self::ETIQUETAS[$estado->value],
                'total'    => $conteo[$estado->value] ?? 0,
            ], EstadoEvento::cases()),
        ];
    }
}
