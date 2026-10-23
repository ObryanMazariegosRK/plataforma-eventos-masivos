<?php
namespace App\Infrastructure\Auth;

use App\Domain\Abstractions\Auth\IPasswordHasher;
use Illuminate\Support\Facades\Hash;

class LaravelPasswordHasher implements IPasswordHasher
{
    public function hashear(string $password): string
    {
        return Hash::make($password);
    }

    public function verificar(string $password, string $hash): bool
    {
        return Hash::check($password, $hash);
    }
}
