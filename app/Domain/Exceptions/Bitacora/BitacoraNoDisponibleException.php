<?php

namespace App\Domain\Exceptions\Bitacora;

use App\Domain\Exceptions\ReglaDeNegocioException;

// La base de la bitácora no responde (normalmente: falta instalarla)
class BitacoraNoDisponibleException extends ReglaDeNegocioException
{
    public function __construct()
    {
        parent::__construct('La bitácora no está disponible. Instálala con: php artisan bitacora:instalar');
    }
}
