<?php
namespace App\Application\UseCases\Inventario;

use App\Application\Abstractions\Inventario\IGenerarInventarioEventoUseCase;
use App\Domain\Abstractions\Inventario\IInventarioRepository;
use App\Domain\Exceptions\Inventario\LocalidadesSinPrecioException;
use App\Domain\Exceptions\Inventario\RecintoSinLugaresException;

/**
 * Crea los lugares a la venta de un evento a partir de su recinto.
 * Se llama al PUBLICAR el evento, dentro de la misma transacción: si falla, el evento no se publica.
 */
class GenerarInventarioEventoUseCase implements IGenerarInventarioEventoUseCase
{
    public function __construct(
        private IInventarioRepository $inventario,
    ) {}

    public function execute(int $eventoId): int
    {
        // Idempotente: publicar dos veces (o correr el comando de nuevo) no duplica lugares.
        if ($this->inventario->existeParaEvento($eventoId)) {
            return 0;
        }

        // Sin precio una zona no se puede vender: mejor que lo sepa el admin al publicar
        // y no un cliente al intentar comprar.
        $sinPrecio = $this->inventario->localidadesSinPrecio($eventoId);
        if ($sinPrecio !== []) {
            throw new LocalidadesSinPrecioException(
                'Falta el precio de: '.implode(', ', $sinPrecio).'. Configúralo antes de publicar.'
            );
        }

        $creados = $this->inventario->generarParaEvento($eventoId);
        if ($creados === 0) {
            throw new RecintoSinLugaresException();
        }

        return $creados;
    }
}
