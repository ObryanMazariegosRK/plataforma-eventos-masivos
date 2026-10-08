<?php
namespace App\Application\UseCases\Auth;

use App\Application\Abstractions\Auth\IVerificarCorreoUseCase;
use App\Application\DTOs\Auth\SesionDTO;
use App\Application\DTOs\Auth\VerificarCorreoDTO;
use App\Domain\Abstractions\Auth\ITokenService;
use App\Domain\Abstractions\Auth\IUsuarioRepository;
use App\Domain\Exceptions\Auth\CodigoVerificacionInvalidoException;
use App\Domain\Exceptions\Auth\CorreoYaVerificadoException;
use App\Domain\Exceptions\Auth\IntentosAgotadosException;
use DateTimeImmutable;

class VerificarCorreoUseCase implements IVerificarCorreoUseCase
{
    public function __construct(
        private IUsuarioRepository $usuarios,
        private ITokenService $tokens,
    ) {}

    public function execute(VerificarCorreoDTO $dto): SesionDTO
    {
        $usuario = $this->usuarios->buscarPorEmail($dto->email);

        // Correo inexistente → mismo error que código incorrecto,
        // para no revelar qué correos están registrados.
        if ($usuario === null) {
            throw new CodigoVerificacionInvalidoException();
        }
        if ($usuario->estaVerificado()) {
            throw new CorreoYaVerificadoException();
        }

        $ahora = new DateTimeImmutable();
        if (! $usuario->codigoEsValido($dto->codigo, $ahora)) {
            // Cada fallo cuenta; al 5.º el código se destruye (frena la fuerza bruta).
            $agotado = $usuario->registrarIntentoVerificacionFallido();
            $this->usuarios->guardar($usuario);

            throw $agotado ? new IntentosAgotadosException() : new CodigoVerificacionInvalidoException();
        }

        $usuario->marcarComoVerificado($ahora);
        $usuario = $this->usuarios->guardar($usuario);

        // Inicia sesión de una vez, para no pedir credenciales justo después de verificar.
        return new SesionDTO($this->tokens->crear($usuario, false), $usuario);
    }
}
