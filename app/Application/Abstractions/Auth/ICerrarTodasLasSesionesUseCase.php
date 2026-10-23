<?php
namespace App\Application\Abstractions\Auth;

interface ICerrarTodasLasSesionesUseCase
{
    public function execute(int $usuarioId): void;
}
