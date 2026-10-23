<?php

namespace App\Rules;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Fecha y hora de una función, escrita en hora de Guatemala (config catalogo.zona_horaria):
 *  - en el futuro y como máximo catalogo.max_meses_evento meses adelante;
 *  - al reprogramar un evento publicado, nunca antes de la fecha que tenía ($noAntesDe):
 *    solo se puede aplazar, no adelantar (quien compró para dentro de 7 días no puede
 *    encontrarse con que la función es en 2).
 */
class FechaDeFuncion implements ValidationRule
{
    public function __construct(private readonly ?CarbonImmutable $noAntesDe = null) {}

    /** "2026-11-05T20:00" (hora de Guatemala) → "2026-11-06 02:00:00" (UTC, como se guarda). */
    public static function aUtc(string $valor): string
    {
        return self::leer($valor)->utc()->format('Y-m-d H:i:s');
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $fecha = self::leer((string) $value);
        } catch (\Throwable) {
            $fail('Ingresa una fecha y hora válidas.');

            return;
        }

        $zona = config('catalogo.zona_horaria');
        $meses = config('catalogo.max_meses_evento');
        $limite = CarbonImmutable::now($zona)->addMonths($meses)->endOfDay();

        if ($this->noAntesDe !== null && $fecha->lt($this->noAntesDe->startOfMinute())) {
            $fail('Solo puedes aplazar la función: elige el '.self::texto($this->noAntesDe).' o una fecha posterior.');
        } elseif ($fecha->lte(CarbonImmutable::now())) {
            $fail('La función debe ser en una fecha y hora futuras.');
        } elseif ($fecha->gt($limite)) {
            $fail("Puedes programar funciones hasta con {$meses} meses de anticipación (hasta el ".self::texto($limite, conHora: false).').');
        }
    }

    private static function leer(string $valor): CarbonImmutable
    {
        // Sin zona en el texto ("2026-11-05T20:00") se entiende hora de Guatemala
        return CarbonImmutable::parse($valor, config('catalogo.zona_horaria'));
    }

    private static function texto(CarbonImmutable $fecha, bool $conHora = true): string
    {
        return $fecha->setTimezone(config('catalogo.zona_horaria'))->locale('es')
            ->isoFormat($conHora ? 'dddd D [de] MMMM, h:mm a' : 'D [de] MMMM [de] YYYY');
    }
}
