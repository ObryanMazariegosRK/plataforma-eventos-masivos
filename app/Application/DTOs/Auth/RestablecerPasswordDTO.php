<?php
namespace App\Application\DTOs\Auth;

class RestablecerPasswordDTO
{
    public function __construct(
        public readonly string $email,
        public readonly string $codigo,
        public readonly string $password,
    ) {}
}
