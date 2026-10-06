<?php
namespace App\Domain\Enums\Notificaciones;

enum CanalNotificacion: string
{
    case EMAIL = 'email';
    case SMS   = 'sms';
    case PUSH  = 'push';
}