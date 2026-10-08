<?php
namespace App\Infrastructure\Auth;

use App\Domain\Abstractions\Auth\ITokenService;
use App\Domain\Entities\Auth\Usuario;
use App\Models\Auth\Usuario as UsuarioModel;
use Laravel\Sanctum\PersonalAccessToken;

class SanctumTokenService implements ITokenService
{
    public function crear(Usuario $usuario, bool $recordar): string
    {
        $expiraEn = $recordar
            ? now()->addDays(config('autenticacion.token_recordar_dias'))
            : now()->addHours(config('autenticacion.token_horas'));

        return UsuarioModel::findOrFail($usuario->getId())
            ->createToken('auth_token', ['*'], $expiraEn)
            ->plainTextToken;
    }

    public function revocar(int $tokenId): void
    {
        PersonalAccessToken::whereKey($tokenId)->delete();
    }

    public function revocarTodos(Usuario $usuario, ?int $exceptoTokenId = null): void
    {
        UsuarioModel::find($usuario->getId())
            ?->tokens()
            ->when($exceptoTokenId !== null, fn ($q) => $q->whereKeyNot($exceptoTokenId))
            ->delete();
    }
}
