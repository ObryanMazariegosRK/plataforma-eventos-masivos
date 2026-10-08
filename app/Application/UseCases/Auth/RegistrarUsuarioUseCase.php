<?php
namespace App\Application\UseCases\Auth;

use App\Application\Abstractions\Auth\IRegistrarUsuarioUseCase;
use App\Application\DTOs\Auth\RegistrarUsuarioDTO;
use App\Domain\Abstractions\Auth\IEnviadorCorreoAuth;
use App\Domain\Abstractions\Auth\IPasswordHasher;
use App\Domain\Abstractions\Auth\IUsuarioRepository;
use App\Domain\Entities\Auth\Usuario;
use App\Domain\Enums\Auth\EstadoUsuario;
use App\Domain\Enums\Auth\RolUsuario;
use App\Domain\Exceptions\Auth\CorreoYaRegistradoException;
use DateTimeImmutable;

class RegistrarUsuarioUseCase implements IRegistrarUsuarioUseCase
{
    public function __construct(
        private IUsuarioRepository $usuarios,
        private IPasswordHasher $hasher,
        private IEnviadorCorreoAuth $correo,
    ) {}

    public function execute(RegistrarUsuarioDTO $dto): Usuario
    {
        if ($this->usuarios->existeEmail($dto->email)) {
            throw new CorreoYaRegistradoException();
        }

        // El registro público siempre crea CLIENTES; admins/organizadores se crean aparte.
        $usuario = new Usuario(
            id: null,
            nombre: $dto->nombre,
            apellido: $dto->apellido,
            email: $dto->email,
            rol: RolUsuario::CLIENTE,
            estado: EstadoUsuario::ACTIVO,
            telefono: $dto->telefono,
            passwordHash: $this->hasher->hashear($dto->password),
        );

        $minutos = $dto->minutosValidezCodigo;
        $codigo = sprintf('%06d', random_int(0, 999999));   // random_int: criptográficamente seguro
        $usuario->asignarCodigoVerificacion($codigo, (new DateTimeImmutable())->modify("+{$minutos} minutes"));

        $usuario = $this->usuarios->guardar($usuario);

        $this->correo->enviarCodigoVerificacion($usuario->getEmail(), $usuario->getNombre(), $codigo, $minutos);

        return $usuario;
    }
}
