<?php
namespace App\Application\UseCases\Pasarelas;

use App\Application\Abstractions\Pasarelas\IListarPasarelasUseCase;
use App\Domain\Abstractions\Pasarelas\IPasarelaRepository;

class ListarPasarelasUseCase implements IListarPasarelasUseCase
{
    public function __construct(
        private IPasarelaRepository $pasarelas,
    ) {}

    public function execute(): array
    {
        return $this->pasarelas->listarActivas();
    }
}
