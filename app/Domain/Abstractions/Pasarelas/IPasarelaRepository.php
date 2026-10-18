<?php
namespace App\Domain\Abstractions\Pasarelas;

use App\Domain\Entities\Pasarelas\Pasarela;

interface IPasarelaRepository
{
    public function buscarPorId(int $id): ?Pasarela;

    public function buscarActivaPorCodigo(string $codigo): ?Pasarela;

    /** @return list<Pasarela> */
    public function listarActivas(): array;
}
