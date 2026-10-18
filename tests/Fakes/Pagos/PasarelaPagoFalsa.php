<?php

namespace Tests\Fakes\Pagos;

use App\Domain\Abstractions\Pasarelas\IPasarelaPago;
use App\Domain\Abstractions\Pasarelas\IPasarelaPagoResolver;
use App\Domain\Abstractions\Pasarelas\OrdenPasarela;
use App\Domain\Abstractions\Pasarelas\ResultadoCaptura;

/** Pasarela falsa que también es su propio resolver. La prueba decide si aprueba o rechaza. */
class PasarelaPagoFalsa implements IPasarelaPago, IPasarelaPagoResolver
{
    public bool $aprobar = true;

    /** @var list<array{monto: float, referencia: string, urlRetorno: string}> */
    public array $ordenes = [];

    /** @var list<string> */
    public array $capturas = [];

    public function para(string $codigo): IPasarelaPago
    {
        return $this;
    }

    public function crearOrden(float $monto, string $moneda, string $referencia, string $urlRetorno, string $urlCancelacion): OrdenPasarela
    {
        $this->ordenes[] = compact('monto', 'referencia', 'urlRetorno');

        return new OrdenPasarela('ORD-'.count($this->ordenes), 'https://pasarela.test/aprobar/ORD-'.count($this->ordenes));
    }

    public function capturar(string $ordenId): ResultadoCaptura
    {
        $this->capturas[] = $ordenId;

        return $this->aprobar
            ? ResultadoCaptura::aprobado('CAP-'.$ordenId)
            : ResultadoCaptura::rechazado('Fondos insuficientes.');
    }
}
