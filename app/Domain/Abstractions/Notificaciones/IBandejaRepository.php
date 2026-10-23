<?php

namespace App\Domain\Abstractions\Notificaciones;

// Los avisos de una persona dentro de Soirée (campana del cliente, sección del panel)
interface IBandejaRepository
{
    /**
     * Del más reciente al más antiguo.
     *
     * @return array{avisos: list<array{id: int, tipo: ?string, titulo: string, mensaje: string, url: ?string, leida: bool, fecha: string}>,
     *               total: int, no_leidas: int}
     */
    public function deUsuario(int $usuarioId, int $pagina, int $porPagina): array;

    /** Marca como leídos sus avisos (uno o, con null, todos). Devuelve cuántos cambiaron. */
    public function marcarLeidas(int $usuarioId, ?int $notificacionId): int;
}
