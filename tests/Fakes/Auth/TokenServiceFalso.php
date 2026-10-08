<?php

namespace Tests\Fakes\Auth;

use App\Domain\Abstractions\Auth\ITokenService;
use App\Domain\Entities\Auth\Usuario;

class TokenServiceFalso implements ITokenService
{
    /** @var list<array{usuarioId: int, recordar: bool}> */
    public array $creados = [];

    /** @var list<int> */
    public array $revocados = [];

    /** @var list<int> ids de usuarios a los que se les revocaron todos los tokens */
    public array $revocadosTodos = [];

    /** @var list<int|null> token conservado en cada revocarTodos() */
    public array $tokensConservados = [];

    public function crear(Usuario $usuario, bool $recordar): string
    {
        $this->creados[] = ['usuarioId' => $usuario->getId(), 'recordar' => $recordar];

        return 'token-'.$usuario->getId();
    }

    public function revocar(int $tokenId): void
    {
        $this->revocados[] = $tokenId;
    }

    public function revocarTodos(Usuario $usuario, ?int $exceptoTokenId = null): void
    {
        $this->revocadosTodos[] = $usuario->getId();
        $this->tokensConservados[] = $exceptoTokenId;
    }
}
