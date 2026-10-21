<?php
namespace App\Infrastructure\Pasarelas;

use App\Domain\Abstractions\Pasarelas\IPasarelaPago;
use App\Domain\Abstractions\Pasarelas\OrdenPasarela;
use App\Domain\Abstractions\Pasarelas\ResultadoCaptura;
use DateTimeImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Pasarela de PRUEBA: imita el flujo de una pasarela real (PayPal, Paggo...) sin mover dinero.
 * Las órdenes viven en la caché (Redis) y la "página de la pasarela" es /pasarela-simulada/{orden},
 * donde la persona paga con una tarjeta de prueba (config pagos.simulada.tarjetas) o cancela.
 */
class PasarelaSimulada implements IPasarelaPago
{
    public const PENDIENTE = 'pendiente';
    public const APROBADA  = 'aprobada';    // la persona autorizó, falta cobrar
    public const RECHAZADA = 'rechazada';   // p. ej. fondos insuficientes
    public const CAPTURADA = 'capturada';   // cobrada

    public function crearOrden(float $monto, string $moneda, string $referencia, string $urlRetorno, string $urlCancelacion, DateTimeImmutable $venceEn): OrdenPasarela
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
            // Se puede pagar hasta que vence la reserva ("minutos_orden" es solo un tope).
            // Después la orden se sigue recordando (ver guardar()) solo para explicar qué
            // pasó si alguien vuelve a la página
            'vence_en'        => min(
                $venceEn->getTimestamp(),
                now()->addMinutes(config('pagos.simulada.minutos_orden'))->getTimestamp(),
            ),
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
            self::PENDIENTE => ResultadoCaptura::rechazado($this->vencida($orden)
                ? 'La orden de pago venció antes de completarse.'
                : 'El pago todavía no fue autorizado en la pasarela.'),
            default         => ResultadoCaptura::rechazado('La orden de pago no existe o venció.'),
        };
    }

    // ---- Usados por la página de la pasarela simulada (PasarelaSimuladaController) ----

    public function obtener(string $ordenId): ?array
    {
        return Cache::get($this->clave($ordenId));
    }

    /** Pendiente pero fuera de tiempo: ya no se puede pagar. */
    public function vencida(array $orden): bool
    {
        return $orden['estado'] === self::PENDIENTE
            && isset($orden['vence_en'])
            && now()->getTimestamp() > $orden['vence_en'];
    }

    /**
     * Qué responde el "banco" a los datos de la tarjeta: 'aprobar', 'rechazar' (sin fondos)
     * o null si no coinciden con ninguna tarjeta de prueba. Nunca se guardan los datos.
     */
    public function evaluarTarjeta(string $numero, string $vence, string $cvv): ?string
    {
        $numero = preg_replace('/\D/', '', $numero);
        $vence = preg_replace('/\s/', '', $vence);

        foreach (config('pagos.simulada.tarjetas') as $tipo => $tarjeta) {
            if (hash_equals($tarjeta['numero'], $numero)
                && hash_equals($tarjeta['vence'], $vence)
                && hash_equals($tarjeta['cvv'], trim($cvv))) {
                return $tipo === 'aprobada' ? 'aprobar' : 'rechazar';
            }
        }

        return null;
    }

    /** Registra la decisión de la persona y devuelve a dónde redirigir el navegador. */
    public function decidir(string $ordenId, string $accion): ?string
    {
        $orden = $this->obtener($ordenId);
        if ($orden === null || $orden['estado'] !== self::PENDIENTE || $this->vencida($orden)) {
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
        // Se guarda un día más allá del vencimiento: así, si la persona vuelve con "Atrás",
        // la página puede decirle si ya pagó o si la orden venció (en vez de un 404)
        $vence = $orden['vence_en'] ?? now()->addMinutes(config('pagos.simulada.minutos_orden'))->getTimestamp();
        Cache::put($this->clave($ordenId), $orden, \Illuminate\Support\Carbon::createFromTimestamp($vence)->addDay());
    }

    private function clave(string $ordenId): string
    {
        return 'pasarela_simulada:'.$ordenId;
    }
}
