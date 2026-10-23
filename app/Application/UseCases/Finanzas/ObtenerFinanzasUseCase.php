<?php

namespace App\Application\UseCases\Finanzas;

use App\Application\Abstractions\Finanzas\IObtenerFinanzasUseCase;
use App\Application\DTOs\Finanzas\ObtenerFinanzasDTO;
use App\Domain\Abstractions\Finanzas\IFinanzasRepository;
use DateTimeImmutable;
use DateTimeZone;

// Ventas y ganancias para el Resumen del administrador. Soirée produce sus eventos:
//   ingresos netos = pagos aprobados − reembolsos − comisión de la pasarela
//   ganancia       = ingresos netos − costos de producción del evento
// Mientras el evento no ocurre se muestra su punto de equilibrio (boletos que faltan para
// cubrir los costos), porque la ganancia final solo existe cuando ya pasó.
class ObtenerFinanzasUseCase implements IObtenerFinanzasUseCase
{
    public function __construct(private IFinanzasRepository $finanzas) {}

    public function execute(ObtenerFinanzasDTO $dto): array
    {
        $zona = new DateTimeZone($dto->zonaHoraria);
        $utc = new DateTimeZone('UTC');
        $hoy = $dto->ahora->setTimezone($zona)->setTime(0, 0);
        $inicio = $hoy->modify('-'.($dto->dias - 1).' days');
        $inicioAnterior = $inicio->modify("-{$dto->dias} days");
        $enUtc = fn (DateTimeImmutable $dia) => $dia->setTimezone($utc)->format('Y-m-d H:i:s');

        // Un solo viaje a la base: el período y el anterior (para comparar)
        $pagos = $this->finanzas->pagosAprobadosEntre($enUtc($inicioAnterior), $enUtc($hoy->modify('+1 day')));

        $porDia = [];
        $anterior = ['monto' => 0.0, 'boletos' => 0];
        foreach ($pagos as $pago) {
            $dia = (new DateTimeImmutable($pago['pagado_en'], $utc))->setTimezone($zona);
            if ($dia < $inicio) {
                $anterior['monto'] += $pago['monto'];
                $anterior['boletos'] += $pago['boletos'];

                continue;
            }
            $clave = $dia->format('Y-m-d');
            $porDia[$clave]['monto'] = ($porDia[$clave]['monto'] ?? 0) + $pago['monto'];
            $porDia[$clave]['boletos'] = ($porDia[$clave]['boletos'] ?? 0) + $pago['boletos'];
        }

        // Todos los días del período, también los que no tuvieron ventas
        $ventas = [];
        for ($dia = $inicio; $dia <= $hoy; $dia = $dia->modify('+1 day')) {
            $clave = $dia->format('Y-m-d');
            $ventas[] = [
                'dia' => $clave,
                'monto' => round($porDia[$clave]['monto'] ?? 0, 2),
                'boletos' => $porDia[$clave]['boletos'] ?? 0,
            ];
        }
        $monto = round(array_sum(array_column($ventas, 'monto')), 2);

        $eventos = array_map(fn (array $e) => $this->resultado($e, $dto), $this->finanzas->resultadosPorEvento());

        // Ganancia del mes: eventos que ya ocurrieron este mes (hora de Guatemala)
        $mes = $hoy->format('Y-m');
        $delMes = array_filter($eventos, fn ($e) => $e['realizado'] && $e['fecha_evento']
            && (new DateTimeImmutable($e['fecha_evento'], $utc))->setTimezone($zona)->format('Y-m') === $mes);
        $realizados = array_filter($eventos, fn ($e) => $e['realizado']);

        return [
            'periodo' => [
                'dias' => $dto->dias,
                'desde' => $inicio->format('Y-m-d'),
                'hasta' => $hoy->format('Y-m-d'),
            ],
            'ventas_diarias' => $ventas,
            'totales' => [
                'ventas' => $monto,
                'boletos' => array_sum(array_column($ventas, 'boletos')),
                'ventas_anteriores' => round($anterior['monto'], 2),
                'boletos_anteriores' => $anterior['boletos'],
                // null = no hubo ventas antes con qué comparar
                'variacion' => $anterior['monto'] > 0 ? round(($monto - $anterior['monto']) / $anterior['monto'], 4) : null,
                'ganancia_mes' => round(array_sum(array_column($delMes, 'ganancia')), 2),
                'eventos_mes' => count($delMes),
                'eventos_mes_sin_costos' => count(array_filter($delMes, fn ($e) => ! $e['tiene_costos'])),
                'ganancia_acumulada' => round(array_sum(array_column($realizados, 'ganancia')), 2),
            ],
            'comision_pasarela' => $dto->comisionPasarela,
            'eventos' => array_values($eventos),
        ];
    }

    private function resultado(array $e, ObtenerFinanzasDTO $dto): array
    {
        $comision = round($e['ingresos_brutos'] * $dto->comisionPasarela, 2);
        $netos = round($e['ingresos_brutos'] - $e['reembolsos'] - $comision, 2);
        $ganancia = round($netos - $e['costos'], 2);
        $realizado = $e['fecha_evento'] !== null
            && new DateTimeImmutable($e['fecha_evento'], new DateTimeZone('UTC')) <= $dto->ahora;

        // Boletos que hay que vender (al precio promedio, ya sin comisión) para cubrir los costos
        $precioNeto = $e['precio_promedio'] * (1 - $dto->comisionPasarela);
        $equilibrio = $e['costos'] > 0 && $precioNeto > 0 ? (int) ceil($e['costos'] / $precioNeto) : 0;

        $estado = match (true) {
            $e['estado'] === 'cancelado' => 'cancelado',
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
            'resultado' => $estado,
        ];
    }
}
