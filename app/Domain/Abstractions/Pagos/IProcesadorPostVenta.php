<?php
namespace App\Domain\Abstractions\Pagos;

/**
 * Lo que pasa DESPUÉS de una venta y puede hacerse en segundo plano:
 * emitir los boletos y avisar al cliente. Si falla, se reintenta sin afectar el pago.
 */
interface IProcesadorPostVenta
{
    public function programar(int $pagoId): void;
}
