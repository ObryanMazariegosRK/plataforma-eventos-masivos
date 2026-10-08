<?php
namespace App\Domain\Abstractions\Auth;

use App\Domain\Entities\Auth\Usuario;

interface ITokenService
{
    /** Crea un token de acceso y devuelve su valor en texto plano. */
    public function crear(Usuario $usuario, bool $recordar): string;

    /** Invalida un token (cerrar sesión en ese dispositivo). */
    public function revocar(int $tokenId): void;

    /**
     * Invalida TODOS los tokens del usuario (cerrar sesión en todos sus dispositivos).
     * Con $exceptoTokenId se conserva esa sesión (la del dispositivo que hace la petición).
     */
    public function revocarTodos(Usuario $usuario, ?int $exceptoTokenId = null): void;
}
