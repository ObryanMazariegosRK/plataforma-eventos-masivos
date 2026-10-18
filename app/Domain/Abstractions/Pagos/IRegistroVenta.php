<?php
namespace App\Domain\Abstractions\Pagos;

use App\Domain\Entities\Pagos\Pago;
use App\Domain\Entities\Reservas\Reserva;

/**
 * Deja constancia contable de una venta (transacción VENTA + historial).
 * Se ejecuta DENTRO de la transacción del pago: si falla, el pago no se aprueba.
 */
interface IRegistroVenta
{
    public function registrar(Pago $pago, Reserva $reserva, string $codigoPasarela): void;
}
