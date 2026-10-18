<?php

namespace Tests\Fakes\Pagos;

use App\Domain\Abstractions\Pagos\IProcesadorPostVenta;
use App\Domain\Abstractions\Pagos\IRegistroVenta;
use App\Domain\Entities\Pagos\Pago;
use App\Domain\Entities\Reservas\Reserva;

/** Hace de IRegistroVenta y de IProcesadorPostVenta: solo anota a quién se llamó. */
class RegistroVentaFalso implements IRegistroVenta, IProcesadorPostVenta
{
    /** @var list<int> */
    public array $ventasRegistradas = [];

    /** @var list<int> */
    public array $postVentasProgramadas = [];

    public function registrar(Pago $pago, Reserva $reserva, string $codigoPasarela): void
    {
        $this->ventasRegistradas[] = $pago->getId();
    }

    public function programar(int $pagoId): void
    {
        $this->postVentasProgramadas[] = $pagoId;
    }
}
