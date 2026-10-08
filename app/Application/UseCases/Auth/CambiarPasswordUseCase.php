<?php
namespace App\Application\UseCases\Auth;

use App\Application\Abstractions\Auth\ICambiarPasswordUseCase;
use App\Application\DTOs\Auth\CambiarPasswordDTO;
use App\Domain\Abstractions\Auth\IPasswordHasher;
use App\Domain\Abstractions\Auth\ITokenService;
use App\Domain\Abstractions\Auth\IUsuarioRepository;
use App\Domain\Exceptions\Auth\PasswordActualIncorrectaException;
use App\Domain\Exceptions\Auth\PasswordNuevaIgualException;
use App\Domain\Exceptions\Auth\UsuarioNoEncontradoException;

class CambiarPasswordUseCase implements ICambiarPasswordUseCase
{
    public function __construct(
        private IUsuarioRepository $usuarios,
        private IPasswordHasher $hasher,
        private ITokenService $tokens,
    ) {}

    public function execute(CambiarPasswordDTO $dto): void
    {
        $usuario = $this->usuarios->buscarPorId($dto->usuarioId)
            ?? throw new UsuarioNoEncontradoException();

        // Aunque ya tenga sesión, se pide la contraseña actual: si alguien
        // toma un celular desbloqueado o roba el token, no puede cambiarla.
        if ($usuario->getPasswordHash() === null
            || ! $this->hasher->verificar($dto->passwordActual, $usuario->getPasswordHash())) {
            throw new PasswordActualIncorrectaException();
        }

        if ($dto->passwordActual === $dto->passwordNueva) {
            throw new PasswordNuevaIgualException();
        }

        $usuario->cambiarPassword($this->hasher->hashear($dto->passwordNueva));
        $this->usuarios->guardar($usuario);

        // Cierra las sesiones de los OTROS dispositivos; esta sigue abierta.
        $this->tokens->revocarTodos($usuario, exceptoTokenId: $dto->tokenActualId);
    }
}
