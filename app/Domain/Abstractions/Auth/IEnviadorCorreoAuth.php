<?php
namespace App\Domain\Abstractions\Auth;

interface IEnviadorCorreoAuth
{
    public function enviarCodigoVerificacion(string $email, string $nombre, string $codigo, int $minutosValidez): void;

    public function enviarCodigoRecuperacion(string $email, string $nombre, string $codigo, int $minutosValidez): void;
}
