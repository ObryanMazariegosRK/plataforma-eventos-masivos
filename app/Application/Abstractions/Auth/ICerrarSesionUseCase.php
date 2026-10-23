<?php
namespace App\Application\Abstractions\Auth;

interface ICerrarSesionUseCase
{
    public function execute(int $tokenId): void;
}
