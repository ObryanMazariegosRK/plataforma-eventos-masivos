<?php
namespace App\Infrastructure\Pasarelas;

use App\Domain\Abstractions\Pasarelas\IPasarelaPago;
use App\Domain\Abstractions\Pasarelas\OrdenPasarela;
use App\Domain\Abstractions\Pasarelas\ResultadoCaptura;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Pasarela de PRUEBA: imita el flujo de una pasarela real (PayPal, Paggo...) sin mover dinero.
 * Las órdenes viven en la caché (Redis) y la "página de la pasarela" es /pasarela-simulada/{orden},
 * donde se elige aprobar, rechazar o cancelar.
 */
class PasarelaSimulada implements IPasarelaPago
{
    public const PENDIENTE = 'pendiente';
    public const APROBADA  = 'aprobada';    // la persona autorizó, falta cobrar
    public const RECHAZADA = 'rechazada';   // p. ej. fondos insuficientes
    public const CAPTURADA = 'capturada';   // cobrada

    public function crearOrden(float $monto, string $moneda, string $referencia, string $urlRetorno, string $urlCancelacion): OrdenPasarela
    {
        $id = 'SIM-'.Str::upper(Str::random(16));

        $this->guardar($id, [
            'monto'           => $monto,
            'moneda'          => $moneda,
            'referencia'      => $referencia,
            'url_retorno'     => $urlRetorno,
            'url_cancelacion' => $urlCancelacion,
            'estado'          => self::PENDIENTE,
            'captura'         => null,
        ]);

        return new OrdenPasarela($id, route('pasarela-simulada.mostrar', $id));
    }

    public function capturar(string $ordenId): ResultadoCaptura
    {
        $orden = $this->obtener($ordenId);

        return match ($orden['estado'] ?? null) {
            self::APROBADA  => $this->cobrar($ordenId, $orden),
            // capturar dos veces la misma orden devuelve el mismo cobro (no cobra doble)
            self::CAPTURADA => ResultadoCaptura::aprobado($orden['captura']),
            self::RECHAZADA => ResultadoCaptura::rechazado('La pasarela rechazó el pago (fondos insuficientes).'),
            self::PENDIENTE => ResultadoCaptura::rechazado('El pago todavía no fue autorizado en la pasarela.'),
            default         => ResultadoCaptura::rechazado('La orden de pago no existe o venció.'),
        };
    }

    // ---- Usados por la página de la pasarela simulada (PasarelaSimuladaController) ----

    public function obtener(string $ordenId): ?array
    {
        return Cache::get($this->clave($ordenId));
    }

    /** Registra la decisión de la persona y devuelve a dónde redirigir el navegador. */
    public function decidir(string $ordenId, string $accion): ?string
    {
        $orden = $this->obtener($ordenId);
        if ($orden === null || $orden['estado'] !== self::PENDIENTE) {
            return null;
        }

        if ($accion === 'cancelar') {
            return $orden['url_cancelacion'];
        }

        $orden['estado'] = $accion === 'aprobar' ? self::APROBADA : self::RECHAZADA;
        $this->guardar($ordenId, $orden);

        // aprobar o rechazar: la app confirma el pago y se entera del resultado
        return $orden['url_retorno'];
    }

    private function cobrar(string $ordenId, array $orden): ResultadoCaptura
    {
        $orden['estado'] = self::CAPTURADA;
        $orden['captura'] = 'SIMCAP-'.Str::upper(Str::random(16));
        $this->guardar($ordenId, $orden);

        return ResultadoCaptura::aprobado($orden['captura']);
    }

    private function guardar(string $ordenId, array $orden): void
    {
        Cache::put($this->clave($ordenId), $orden, now()->addMinutes(config('pagos.simulada.minutos_orden')));
    }

    private function clave(string $ordenId): string
    {
        return 'pasarela_simulada:'.$ordenId;
    }
}
