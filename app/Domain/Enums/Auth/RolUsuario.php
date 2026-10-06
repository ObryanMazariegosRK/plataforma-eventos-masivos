<?php
namespace App\Domain\Enums\Auth;

enum RolUsuario: string
{
    case CLIENTE = 'cliente';
    case ADMIN = 'admin';
    case ORGANIZADOR = 'organizador';
}