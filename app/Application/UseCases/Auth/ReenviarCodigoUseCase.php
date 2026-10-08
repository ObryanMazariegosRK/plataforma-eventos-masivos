<?php
namespace App\Application\UseCases\Auth;

use App\Application\Abstractions\Auth\IReenviarCodigoUseCase;
use App\Application\DTOs\Auth\ReenviarCodigoDTO;
use App\Domain\Abstractions\Auth\IEnviadorCorreoAuth;
use App\Domain\Abstractions\Auth\IUsuarioRepository;
use DateTimeImmutable;

class ReenviarCodigoUseCase implements IReenviarCodigoUseCase
{
    public function __construct(
        private IUsuarioRepository $usuarios,
        private IEnviadorCorreoAuth $correo,
    ) {}

    public function execute(ReenviarCodigoDTO $dto): void
    {
        $usuario = $this->usuarios->buscarPorEmail($dto->email);

        // Si no existe o ya está verificado no se hace nada, y el controller responde
        // lo mismo en todos los casos (no revela qué correos están registrados).
        if ($usuario === null || $usuario->estaVerificado()) {
            return;
        }

        // Un código nuevo invalida al anterior.
        $minutos = $dto->minutosValidezCodigo;
        $codigo = sprintf('%06d', random_int(0, 999999));
        $usuario->asignarCodigoVerificacion($codigo, (new DateTimeImmutable())->modify("+{$minutos} minutes"));
        $this->usuarios->guardar($usuario);

        $this->correo->enviarCodigoVerificacion($usuario->getEmail(), $usuario->getNombre(), $codigo, $minutos);
    }
}
