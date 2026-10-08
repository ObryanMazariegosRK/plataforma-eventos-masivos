<?php

namespace Tests\Fakes\Auth;

use App\Domain\Abstractions\Auth\IUsuarioRepository;
use App\Domain\Entities\Auth\Usuario;

class UsuarioRepositoryEnMemoria implements IUsuarioRepository
{
    /** @var array<int, Usuario> */
    public array $usuarios = [];

    public function guardar(Usuario $usuario): Usuario
    {
        $id = $usuario->getId() ?? count($this->usuarios) + 1;

        // Se guarda una COPIA (como hace la BD) con el id asignado.
        // Closure::call permite escribir la propiedad privada $id solo aquí, en la prueba.
        $copia = clone $usuario;
        (fn () => $this->id = $id)->call($copia);

        $this->usuarios[$id] = $copia;

        return clone $copia;
    }

    public function buscarPorId(int $id): ?Usuario
    {
        return isset($this->usuarios[$id]) ? clone $this->usuarios[$id] : null;
    }

    public function buscarPorEmail(string $email): ?Usuario
    {
        foreach ($this->usuarios as $usuario) {
            if ($usuario->getEmail() === $email) {
                return clone $usuario;
            }
        }

        return null;
    }

    public function existeEmail(string $email): bool
    {
        return $this->buscarPorEmail($email) !== null;
    }
}
