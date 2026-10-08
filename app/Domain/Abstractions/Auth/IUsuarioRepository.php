<?php
namespace App\Domain\Abstractions\Auth;

use App\Domain\Entities\Auth\Usuario;

interface IUsuarioRepository
{
    public function guardar(Usuario $usuario): Usuario;

    public function buscarPorId(int $id): ?Usuario;

    public function buscarPorEmail(string $email): ?Usuario;

    public function existeEmail(string $email): bool;
}
