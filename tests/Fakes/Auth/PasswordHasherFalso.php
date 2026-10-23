<?php

namespace Tests\Fakes\Auth;

use App\Domain\Abstractions\Auth\IPasswordHasher;

// "Hashea" con un prefijo: suficiente para probar la lógica sin bcrypt.
class PasswordHasherFalso implements IPasswordHasher
{
    public function hashear(string $password): string
    {
        return 'hash:'.$password;
    }

    public function verificar(string $password, string $hash): bool
    {
        return $hash === 'hash:'.$password;
    }
}
