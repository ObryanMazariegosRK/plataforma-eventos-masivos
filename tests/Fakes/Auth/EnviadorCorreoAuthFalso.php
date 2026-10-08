<?php

namespace Tests\Fakes\Auth;

use App\Domain\Abstractions\Auth\IEnviadorCorreoAuth;

// Guarda los correos "enviados" para poder inspeccionarlos en la prueba.
class EnviadorCorreoAuthFalso implements IEnviadorCorreoAuth
{
    /** @var list<array{email: string, nombre: string, codigo: string, minutos: int}> */
    public array $enviados = [];

    /** @var list<array{email: string, nombre: string, codigo: string, minutos: int}> */
    public array $recuperaciones = [];

    public function enviarCodigoVerificacion(string $email, string $nombre, string $codigo, int $minutosValidez): void
    {
        $this->enviados[] = ['email' => $email, 'nombre' => $nombre, 'codigo' => $codigo, 'minutos' => $minutosValidez];
    }

    public function enviarCodigoRecuperacion(string $email, string $nombre, string $codigo, int $minutosValidez): void
    {
        $this->recuperaciones[] = ['email' => $email, 'nombre' => $nombre, 'codigo' => $codigo, 'minutos' => $minutosValidez];
    }
}
