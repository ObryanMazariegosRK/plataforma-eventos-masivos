<?php

namespace App\Application\Abstractions\Notificaciones;

interface IConsultarBandejaUseCase
{
    /** @return array{avisos: list<array>, no_leidas: int, meta: array{pagina: int, por_pagina: int, total: int, hay_mas: bool}} */
    public function execute(int $usuarioId, int $pagina, int $porPagina): array;
}
