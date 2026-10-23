<?php
namespace App\Application\UseCases\Auth;

use App\Application\Abstractions\Auth\IObtenerPerfilUseCase;
use App\Domain\Abstractions\Auth\IUsuarioRepository;
use App\Domain\Entities\Auth\Usuario;
use App\Domain\Exceptions\Auth\UsuarioNoEncontradoException;

class ObtenerPerfilUseCase implements IObtenerPerfilUseCase
{
    public function __construct(
        private IUsuarioRepository $usuarios,
    ) {}

    public function execute(int $usuarioId): Usuario
    {
        return $this->usuarios->buscarPorId($usuarioId)
            ?? throw new UsuarioNoEncontradoException();
    }
}
