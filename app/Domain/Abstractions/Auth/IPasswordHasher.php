<?php
namespace App\Domain\Abstractions\Auth;

interface IPasswordHasher
{
    public function hashear(string $password): string;

    public function verificar(string $password, string $hash): bool;
}
