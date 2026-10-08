<?php
namespace App\Application\Abstractions\Auth;

use App\Application\DTOs\Auth\SolicitarRecuperacionDTO;

interface ISolicitarRecuperacionUseCase
{
    public function execute(SolicitarRecuperacionDTO $dto): void;
}
