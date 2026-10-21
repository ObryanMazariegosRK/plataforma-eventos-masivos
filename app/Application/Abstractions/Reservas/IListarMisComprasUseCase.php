<?php
namespace App\Application\Abstractions\Reservas;

use DateTimeImmutable;

interface IListarMisComprasUseCase
{
    // Compras (reservas pagadas) del usuario: ['proximas' => [...], 'pasadas' => [...]]
    public function execute(int $usuarioId, DateTimeImmutable $ahora): array;
}
