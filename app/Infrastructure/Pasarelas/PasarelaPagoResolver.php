<?php
namespace App\Infrastructure\Pasarelas;

use App\Domain\Abstractions\Pasarelas\IPasarelaPago;
use App\Domain\Abstractions\Pasarelas\IPasarelaPagoResolver;
use App\Domain\Exceptions\Pagos\PasarelaNoDisponibleException;
use Illuminate\Contracts\Container\Container;

class PasarelaPagoResolver implements IPasarelaPagoResolver
{
    // código en la tabla `pasarelas` → clase que la implementa.
    // Para agregar PayPal o Paggo: crear la clase y agregar una línea aquí.
    private const IMPLEMENTACIONES = [
        'simulada' => PasarelaSimulada::class,
    ];

    public function __construct(
        private Container $contenedor,
    ) {}

    public function para(string $codigo): IPasarelaPago
    {
        $clase = self::IMPLEMENTACIONES[$codigo] ?? throw new PasarelaNoDisponibleException();

        return $this->contenedor->make($clase);
    }
}
