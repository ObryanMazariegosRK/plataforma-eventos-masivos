<?php

namespace App\Application\UseCases\Finanzas;

use App\Application\Abstractions\Finanzas\IObtenerFinanzasUseCase;
use App\Application\DTOs\Finanzas\ObtenerFinanzasDTO;
use App\Domain\Abstractions\Finanzas\IFinanzasRepository;
use DateTimeImmutable;
use DateTimeZone;

// Ventas y ganancias para el Resumen del administrador, en el período elegido. Soirée produce
// sus eventos:
//   ingresos netos = pagos aprobados − reembolsos − comisión de la pasarela
//   ganancia       = ingresos netos − costos de producción del evento
// La ganancia solo es definitiva cuando el evento ya ocurrió (o se canceló): esos forman el
// historial del período. Los próximos muestran su punto de equilibrio (boletos que faltan para
// cubrir los costos), porque aún no se sabe si darán ganancia.
class ObtenerFinanzasUseCase implements IObtenerFinanzasUseCase
{
    private DateTimeZone $zona;

    private DateTimeZone $utc;

    public function __construct(private IFinanzasRepository $finanzas) {}

    public function execute(ObtenerFinanzasDTO $dto): array
    {
        $this->zona = new DateTimeZone($dto->zonaHoraria);
        $this->utc = new DateTimeZone('UTC');

        $hoy = $dto->ahora->setTimezone($this->zona)->setTime(0, 0);
        $hasta = $dto->hasta ? new DateTimeImmutable($dto->hasta, $this->zona) : $hoy;
        $desde = $dto->desde ? new DateTimeImmutable($dto->desde, $this->zona) : $hasta->modify('-'.($dto->diasPorDefecto - 1).' days');
        $dias = (int) $desde->diff($hasta)->days + 1;
        $agrupacion = $dias <= 45 ? 'dia' : ($dias <= 210 ? 'semana' : 'mes');

        // El período anterior, del mismo largo, sirve para comparar las ventas
        $desdeAnterior = $desde->modify("-{$dias} days");
        $pagos = $this->finanzas->pagosAprobadosEntre($this->enUtc($desdeAnterior), $this->enUtc($hasta->modify('+1 day')));

        $grupos = $this->grupos($desde, $hasta, $agrupacion);
        $anterior = ['monto' => 0.0, 'boletos' => 0];
        foreach ($pagos as $pago) {
            $dia = (new DateTimeImmutable($pago['pagado_en'], $this->utc))->setTimezone($this->zona);
            if ($dia < $desde) {
                $anterior['monto'] += $pago['monto'];
                $anterior['boletos'] += $pago['boletos'];

                continue;
            }
            $clave = $this->claveGrupo($dia, $agrupacion);
            if (isset($grupos[$clave])) {
                $grupos[$clave]['monto'] += $pago['monto'];
                $grupos[$clave]['boletos'] += $pago['boletos'];
            }
        }
        $ventas = array_map(fn ($g) => ['monto' => round($g['monto'], 2)] + $g, array_values($grupos));
        $monto = round(array_sum(array_column($ventas, 'monto')), 2);

        $eventos = array_map(fn (array $e) => $this->resultado($e, $dto), $this->finanzas->resultadosPorEvento());

        // Historial: eventos cerrados (ya ocurrieron o se cancelaron) con fecha dentro del período
        $historial = array_values(array_filter($eventos, function ($e) use ($desde, $hasta) {
            if (! $e['cerrado'] || ! $e['fecha_evento']) {
                return false;
            }
            $dia = (new DateTimeImmutable($e['fecha_evento'], $this->utc))->setTimezone($this->zona)->setTime(0, 0);

            return $dia >= $desde && $dia <= $hasta;
        }));
        $proximos = array_values(array_filter($eventos, fn ($e) => ! $e['cerrado']));

        $ganancia = round(array_sum(array_column($historial, 'ganancia')), 2);
        $ingresosHistorial = array_sum(array_column($historial, 'ingresos_brutos'));

        return [
            'periodo' => [
                'desde' => $desde->format('Y-m-d'),
                'hasta' => $hasta->format('Y-m-d'),
                'dias' => $dias,
                'agrupacion' => $agrupacion,
            ],
            'ventas' => $ventas,
            'totales' => [
                'ventas' => $monto,
                'boletos' => array_sum(array_column($ventas, 'boletos')),
                'ventas_anteriores' => round($anterior['monto'], 2),
                'boletos_anteriores' => $anterior['boletos'],
                // null = no hubo ventas antes con qué comparar
                'variacion' => $anterior['monto'] > 0 ? round(($monto - $anterior['monto']) / $anterior['monto'], 4) : null,
                'ganancia' => $ganancia,
                'margen' => $ingresosHistorial > 0 ? round($ganancia / $ingresosHistorial, 4) : null,
                'eventos_cerrados' => count($historial),
                'eventos_sin_costos' => count(array_filter($historial, fn ($e) => ! $e['tiene_costos'])),
            ],
            'comision_pasarela' => $dto->comisionPasarela,
            'historial' => $historial,
            'proximos' => $proximos,
        ];
    }

    // Grupos vacíos de todo el período (también los que no tuvieron ventas)
    private function grupos(DateTimeImmutable $desde, DateTimeImmutable $hasta, string $agrupacion): array
    {
        $grupos = [];
        for ($dia = $desde; $dia <= $hasta; $dia = $dia->modify('+1 day')) {
            $clave = $this->claveGrupo($dia, $agrupacion);
            $grupos[$clave] ??= ['inicio' => $dia->format('Y-m-d'), 'fin' => $dia->format('Y-m-d'), 'monto' => 0.0, 'boletos' => 0];
            $grupos[$clave]['fin'] = $dia->format('Y-m-d');
        }

        return $grupos;
    }

    private function claveGrupo(DateTimeImmutable $dia, string $agrupacion): string
    {
        return match ($agrupacion) {
            'dia' => $dia->format('Y-m-d'),
            'semana' => $dia->format('o-W'),   // semana ISO (de lunes a domingo)
            'mes' => $dia->format('Y-m'),
        };
    }

    private function enUtc(DateTimeImmutable $dia): string
    {
        return $dia->setTimezone($this->utc)->format('Y-m-d H:i:s');
    }

    private function resultado(array $e, ObtenerFinanzasDTO $dto): array
    {
        $comision = round($e['ingresos_brutos'] * $dto->comisionPasarela, 2);
        $netos = round($e['ingresos_brutos'] - $e['reembolsos'] - $comision, 2);
        $ganancia = round($netos - $e['costos'], 2);
        $realizado = $e['fecha_evento'] !== null
            && new DateTimeImmutable($e['fecha_evento'], $this->utc) <= $dto->ahora;
        $cancelado = $e['estado'] === 'cancelado';

        // Boletos que hay que vender (al precio promedio, ya sin comisión) para cubrir los costos
        $precioNeto = $e['precio_promedio'] * (1 - $dto->comisionPasarela);
        $equilibrio = $e['costos'] > 0 && $precioNeto > 0 ? (int) ceil($e['costos'] / $precioNeto) : 0;

        $resultado = match (true) {
            $cancelado => 'cancelado',
            $realizado => $ganancia >= 0 ? 'ganancia' : 'perdida',
            $e['costos'] <= 0 => 'sin_costos',   // sin costos no hay equilibrio que medir
            $e['vendidos'] >= $equilibrio => 'cubierto',
            default => 'en_venta',
        };

        return [
            'id' => $e['id'],
            'nombre' => $e['nombre'],
            'estado' => $e['estado'],
            'fecha_evento' => $e['fecha_evento'],
            'realizado' => $realizado,
            // Su ganancia ya no cambia: ocurrió o se canceló
            'cerrado' => $realizado || $cancelado,
            'ingresos_brutos' => $e['ingresos_brutos'],
            'reembolsos' => $e['reembolsos'],
            'comision' => $comision,
            'ingresos_netos' => $netos,
            'costos' => $e['costos'],
            // Sin costos registrados la "ganancia" es solo ingresos: el panel lo advierte
            'tiene_costos' => $e['costos'] > 0,
            'ganancia' => $ganancia,
            'margen' => $e['ingresos_brutos'] > 0 ? round($ganancia / $e['ingresos_brutos'], 4) : null,
            'vendidos' => $e['vendidos'],
            'lugares' => $e['lugares'],
            'equilibrio_boletos' => $equilibrio,
            // Ni vendiendo todo se cubren los costos: hay que revisar precios o gastos
            'equilibrio_alcanzable' => $equilibrio <= $e['lugares'],
            'avance_equilibrio' => $equilibrio > 0 ? round(min(1, $e['vendidos'] / $equilibrio), 4) : 1.0,
            'resultado' => $resultado,
        ];
    }
}
