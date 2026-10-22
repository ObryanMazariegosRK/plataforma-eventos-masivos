<?php
namespace App\Domain\Abstractions\Pasarelas;

use DateTimeImmutable;

/**
 * Contrato que cumple CUALQUIER pasarela (simulada, PayPal, Paggo...).
 * El cobro siempre es en dos pasos, como en las pasarelas reales:
 *   1. crearOrden(): la pasarela prepara el cobro y da una URL para que la persona lo apruebe.
 *   2. (la persona aprueba o rechaza en la página de la pasarela)
 *   3. capturar(): se cobra de verdad. Hasta aquí no se ha movido dinero.
 */
interface IPasarelaPago
{
    public function crearOrden(
        float $monto,
        string $moneda,
        string $referencia,
        string $urlRetorno,
        string $urlCancelacion,
        // Después de esta hora la orden ya no se puede aprobar (se pasa la expiración de la
        // reserva: pagar un asiento que ya se liberó no tiene sentido)
        DateTimeImmutable $venceEn,
    ): OrdenPasarela;

    public function capturar(string $ordenId): ResultadoCaptura;
}
