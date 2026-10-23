<?php

namespace App\Domain\Abstractions\Admin;

interface IReporteAdminRepository
{
    /**
     * Filas del informe con todo su detalle, en orden de creación. $tipo: eventos | reservas |
     * pagos | usuarios. $desde / $hasta: límites en UTC ('Y-m-d H:i:s', "hasta" exclusivo) sobre
     * la fecha de registro. Devuelve como máximo $limite filas (el caso de uso pide una de más
     * para saber si el período es demasiado grande).
     */
    public function filas(string $tipo, ?string $desde, ?string $hasta, int $limite): array;
}
