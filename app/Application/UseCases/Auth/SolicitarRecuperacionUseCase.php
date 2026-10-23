<?php
namespace App\Application\UseCases\Auth;

use App\Application\Abstractions\Auth\ISolicitarRecuperacionUseCase;
use App\Application\DTOs\Auth\SolicitarRecuperacionDTO;
use App\Domain\Abstractions\Auth\IEnviadorCorreoAuth;
use App\Domain\Abstractions\Auth\IUsuarioRepository;
use DateTimeImmutable;

class SolicitarRecuperacionUseCase implements ISolicitarRecuperacionUseCase
{
    public function __construct(
        private IUsuarioRepository $usuarios,
        private IEnviadorCorreoAuth $correo,
    ) {}

    public function execute(SolicitarRecuperacionDTO $dto): void
    {
        $usuario = $this->usuarios->buscarPorEmail($dto->email);

        // No existe, está bloqueado o nunca verificó su correo → no se envía nada.
        // El controller responde lo mismo en todos los casos (no revela qué correos existen).
        if ($usuario === null || $usuario->estaBloqueado() || ! $usuario->estaVerificado()) {
            return;
        }

        $minutos = $dto->minutosValidezCodigo;
        $codigo = sprintf('%06d', random_int(0, 999999));
        $usuario->asignarCodigoRecuperacion($codigo, (new DateTimeImmutable())->modify("+{$minutos} minutes"));
        $this->usuarios->guardar($usuario);

        $this->correo->enviarCodigoRecuperacion($usuario->getEmail(), $usuario->getNombre(), $codigo, $minutos);
    }
}
