<?php

namespace App\Domain\Abstractions\Notificaciones;

// Lecturas para avisar al personal de Soirée (administradores y operadores)
interface IPersonalRepository
{
    /** @param list<string> $roles  @return int[] ids de los usuarios activos con esos roles */
    public function idsConRol(array $roles): array;

    public function nombreDeUsuario(int $usuarioId): string;

    /** @return array{evento_id: int, evento: string, vendidos: int, lugares: int}|null */
    public function ocupacionPorPago(int $pagoId): ?array;
}
