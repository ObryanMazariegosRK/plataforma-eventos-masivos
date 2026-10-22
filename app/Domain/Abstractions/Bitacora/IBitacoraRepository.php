<?php

namespace App\Domain\Abstractions\Bitacora;

use App\Domain\Entities\Bitacora\RegistroBitacora;

interface IBitacoraRepository
{
    // Solo se agregan filas: la bitácora no tiene métodos para editar ni borrar.
    public function registrar(RegistroBitacora $registro): void;

    /**
     * Registros del más reciente al más antiguo. Filtros (todos opcionales):
     * categoria, resultado, rol ('sin_sesion' = acciones sin usuario), usuario (nombre o correo),
     * desde / hasta (Y-m-d H:i:s en UTC; "hasta" es exclusivo).
     */
    public function buscar(array $filtros, int $limite, int $desde): array;

    public function contar(array $filtros): int;
}
