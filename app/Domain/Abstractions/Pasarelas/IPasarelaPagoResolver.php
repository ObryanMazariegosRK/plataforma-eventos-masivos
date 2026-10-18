<?php
namespace App\Domain\Abstractions\Pasarelas;

/** Entrega la implementación de pasarela que corresponde a un código ('simulada', 'paypal'...). */
interface IPasarelaPagoResolver
{
    public function para(string $codigo): IPasarelaPago;
}
