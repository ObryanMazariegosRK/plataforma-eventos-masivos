<?php
namespace App\Jobs\Pagos;

use App\Application\Abstractions\Boletos\IEmitirBoletosUseCase;
use App\Application\UseCases\Notificaciones\NotificarCompraConfirmadaUseCase;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Lo corre el worker (contenedor "queue") después de que un pago se aprueba:
 * 1) emite los boletos (Boletos, Freyder)  2) avisa al cliente (Notificaciones, Freyder).
 * Los dos casos de uso son idempotentes: si el job se reintenta, no duplica nada.
 */
class ProcesarCompraConfirmadaJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;
    public int $backoff = 10;   // segundos entre reintentos

    public function __construct(
        public readonly int $pagoId,
    ) {}

    public function handle(IEmitirBoletosUseCase $emitirBoletos, NotificarCompraConfirmadaUseCase $notificar): void
    {
        $emitirBoletos->execute($this->pagoId);
        $notificar->execute($this->pagoId);
    }
}
