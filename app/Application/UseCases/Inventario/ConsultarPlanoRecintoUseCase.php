<?php

namespace App\Application\UseCases\Inventario;

use App\Application\Abstractions\Inventario\IConsultarPlanoRecintoUseCase;
use App\Domain\Abstractions\Inventario\IInventarioRepository;
use App\Domain\Exceptions\Inventario\RecintoSinLugaresException;

class ConsultarPlanoRecintoUseCase implements IConsultarPlanoRecintoUseCase
{
    public function __construct(private IInventarioRepository $inventario) {}

    public function execute(int $recintoId): array
    {
        $plano = $this->inventario->planoRecinto($recintoId);

        if ($plano === null || $plano['localidades'] === []) {
            throw new RecintoSinLugaresException('El recinto no existe o no tiene localidades configuradas.');
        }

        return $plano;
    }
}
