<?php
namespace App\Domain\Enums\Auth;

enum EstadoUsuario: string
{
    case ACTIVO = 'activo';
    case BLOQUEADO = 'bloqueado';
}