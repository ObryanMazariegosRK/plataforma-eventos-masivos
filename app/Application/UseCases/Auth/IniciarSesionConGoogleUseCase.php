<?php
namespace App\Application\UseCases\Auth;

use App\Application\Abstractions\Auth\IIniciarSesionConGoogleUseCase;
use App\Application\DTOs\Auth\IniciarSesionConGoogleDTO;
use App\Application\DTOs\Auth\SesionDTO;
use App\Domain\Abstractions\Auth\ITokenService;
use App\Domain\Abstractions\Auth\IUsuarioRepository;
use App\Domain\Entities\Auth\Usuario;
use App\Domain\Enums\Auth\EstadoUsuario;
use App\Domain\Enums\Auth\RolUsuario;
use App\Domain\Exceptions\Auth\CorreoNoVerificadoException;
use App\Domain\Exceptions\Auth\UsuarioBloqueadoException;
use DateTimeImmutable;

class IniciarSesionConGoogleUseCase implements IIniciarSesionConGoogleUseCase
{
    public function __construct(
        private IUsuarioRepository $usuarios,
        private ITokenService $tokens,
    ) {}

    public function execute(IniciarSesionConGoogleDTO $dto): SesionDTO
    {
        // Sin correo verificado por Google no se puede confiar en que sea su dueño.
        if (! $dto->emailVerificado) {
            throw new CorreoNoVerificadoException('Tu correo de Google no está verificado. Verifícalo en Google o regístrate con contraseña.');
        }

        $ahora = new DateTimeImmutable();

        // Caso 1: ya había entrado antes con Google.
        $usuario = $this->usuarios->buscarPorGoogleId($dto->googleId);

        if ($usuario === null) {
            // Caso 2: ya tiene cuenta con ese correo (registro normal) → se vincula.
            // Caso 3: no existe → se crea un cliente ya verificado y sin contraseña.
            $usuario = $this->usuarios->buscarPorEmail($dto->email)
                ?? new Usuario(
                    id: null,
                    nombre: $dto->nombre,
                    apellido: $dto->apellido,
                    email: $dto->email,
                    rol: RolUsuario::CLIENTE,
                    estado: EstadoUsuario::ACTIVO,
                    emailVerificadoEn: $ahora,
                );

            // Se revisa ANTES de guardar: a una cuenta bloqueada no se le vincula nada.
            if ($usuario->estaBloqueado()) {
                throw new UsuarioBloqueadoException();
            }

            $usuario->vincularGoogle($dto->googleId, $ahora);
            $usuario = $this->usuarios->guardar($usuario);
        }

        if ($usuario->estaBloqueado()) {
            throw new UsuarioBloqueadoException();
        }

        return new SesionDTO($this->tokens->crear($usuario, false), $usuario);
    }
}
