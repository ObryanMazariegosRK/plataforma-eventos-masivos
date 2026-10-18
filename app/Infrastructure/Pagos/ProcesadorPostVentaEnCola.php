<?php
namespace App\Infrastructure\Pagos;

use App\Domain\Abstractions\Pagos\IProcesadorPostVenta;
use App\Jobs\Pagos\ProcesarCompraConfirmadaJob;

class ProcesadorPostVentaEnCola implements IProcesadorPostVenta
{
    public function programar(int $pagoId): void
    {
        // afterCommit: el job se envía a la cola SOLO si la transacción del pago se confirma.
        // Si el pago se deshace, el worker nunca intentará emitir boletos de un pago inexistente.
        ProcesarCompraConfirmadaJob::dispatch($pagoId)->afterCommit();
    }
}
