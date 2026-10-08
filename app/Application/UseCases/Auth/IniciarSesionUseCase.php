<?php
namespace App\Application\UseCases\Auth;

use App\Application\Abstractions\Auth\IIniciarSesionUseCase;
use App\Application\DTOs\Auth\IniciarSesionDTO;
use App\Application\DTOs\Auth\SesionDTO;
use App\Domain\Abstractions\Auth\IPasswordHasher;
use App\Domain\Abstractions\Auth\ITokenService;
use App\Domain\Abstractions\Auth\IUsuarioRepository;
use App\Domain\Exceptions\Auth\CorreoNoVerificadoException;
use App\Domain\Exceptions\Auth\CredencialesInvalidasException;
use App\Domain\Exceptions\Auth\UsuarioBloqueadoException;

class IniciarSesionUseCase implements IIniciarSesionUseCase
{
    public function __construct(
        private IUsuarioRepository $usuarios,
        private IPasswordHasher $hasher,
        private ITokenService $tokens,
    ) {}

    public function execute(IniciarSesionDTO $dto): SesionDTO
    {
        $usuario = $this->usuarios->buscarPorEmail($dto->email);

        // Correo inexistente y contraseña incorrecta dan el MISMO error.
        if ($usuario === null
            || $usuario->getPasswordHash() === null
            || ! $this->hasher->verificar($dto->password, $usuario->getPasswordHash())) {
            throw new CredencialesInvalidasException();
        }

        // Solo después de validar la contraseña se revela el estado de la cuenta.
        if ($usuario->estaBloqueado()) {
            throw new UsuarioBloqueadoException();
        }
        if (! $usuario->estaVerificado()) {
            throw new CorreoNoVerificadoException();
        }

        return new SesionDTO($this->tokens->crear($usuario, $dto->recordar), $usuario);
    }
}
