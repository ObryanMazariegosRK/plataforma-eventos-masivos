<?php
namespace App\Application\UseCases\Auth;

use App\Application\Abstractions\Auth\ICerrarTodasLasSesionesUseCase;
use App\Domain\Abstractions\Auth\ITokenService;
use App\Domain\Abstractions\Auth\IUsuarioRepository;
use App\Domain\Exceptions\Auth\UsuarioNoEncontradoException;

class CerrarTodasLasSesionesUseCase implements ICerrarTodasLasSesionesUseCase
{
    public function __construct(
        private IUsuarioRepository $usuarios,
        private ITokenService $tokens,
    ) {}

    public function execute(int $usuarioId): void
    {
        $usuario = $this->usuarios->buscarPorId($usuarioId)
            ?? throw new UsuarioNoEncontradoException();

        // Incluye el token de esta petición: el usuario queda fuera en todos lados.
        $this->tokens->revocarTodos($usuario);
    }
}
