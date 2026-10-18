<?php
namespace App\Application\Abstractions\Pasarelas;

use App\Domain\Entities\Pasarelas\Pasarela;

interface IListarPasarelasUseCase
{
    /** @return list<Pasarela> */
    public function execute(): array;
}
