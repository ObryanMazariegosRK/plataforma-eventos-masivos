<?php
namespace App\Infrastructure\Auth;

use App\Domain\Abstractions\Auth\IEnviadorCorreoAuth;
use App\Infrastructure\Auth\Mail\CodigoRecuperacionMail;
use App\Infrastructure\Auth\Mail\CodigoVerificacionMail;
use Illuminate\Support\Facades\Mail;

class LaravelEnviadorCorreoAuth implements IEnviadorCorreoAuth
{
    public function enviarCodigoVerificacion(string $email, string $nombre, string $codigo, int $minutosValidez): void
    {
        Mail::to($email)->send(new CodigoVerificacionMail($nombre, $codigo, $minutosValidez));
    }

    public function enviarCodigoRecuperacion(string $email, string $nombre, string $codigo, int $minutosValidez): void
    {
        Mail::to($email)->send(new CodigoRecuperacionMail($nombre, $codigo, $minutosValidez));
    }
}
