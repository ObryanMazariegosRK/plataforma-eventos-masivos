<?php
namespace App\Application\UseCases\Auth;

use App\Application\Abstractions\Auth\IRestablecerPasswordUseCase;
use App\Application\DTOs\Auth\RestablecerPasswordDTO;
use App\Domain\Abstractions\Auth\IPasswordHasher;
use App\Domain\Abstractions\Auth\ITokenService;
use App\Domain\Abstractions\Auth\IUsuarioRepository;
use App\Domain\Exceptions\Auth\CodigoRecuperacionInvalidoException;
use App\Domain\Exceptions\Auth\IntentosAgotadosException;
use App\Domain\Exceptions\Auth\UsuarioBloqueadoException;
use DateTimeImmutable;

class RestablecerPasswordUseCase implements IRestablecerPasswordUseCase
{
    public function __construct(
        private IUsuarioRepository $usuarios,
        private IPasswordHasher $hasher,
        private ITokenService $tokens,
    ) {}

    public function execute(RestablecerPasswordDTO $dto): void
    {
        $usuario = $this->usuarios->buscarPorEmail($dto->email);

        // Correo inexistente → mismo error que código incorrecto.
        if ($usuario === null) {
            throw new CodigoRecuperacionInvalidoException();
        }

        if (! $usuario->codigoRecuperacionEsValido($dto->codigo, new DateTimeImmutable())) {
            $agotado = $usuario->registrarIntentoRecuperacionFallido();
            $this->usuarios->guardar($usuario);

            throw $agotado ? new IntentosAgotadosException() : new CodigoRecuperacionInvalidoException();
        }

        // Pudo bloquearse después de pedir el código.
        if ($usuario->estaBloqueado()) {
            throw new UsuarioBloqueadoException();
        }

        $usuario->cambiarPassword($this->hasher->hashear($dto->password));
        $this->usuarios->guardar($usuario);

        // Cierra sesión en TODOS los dispositivos: si alguien había entrado a la cuenta, queda fuera.
        $this->tokens->revocarTodos($usuario);
    }
}
