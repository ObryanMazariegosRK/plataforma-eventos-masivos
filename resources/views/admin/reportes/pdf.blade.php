@php
    // Mismo informe que el Excel (GenerarReporteAdminUseCase); aquí solo se le da formato
    $formatear = function ($valor, string $formato) {
        if ($valor === null || $valor === '') {
            return '—';
        }

        return match ($formato) {
            'moneda'     => 'Q' . number_format((float) $valor, 2),
            'porcentaje' => round($valor * 100) . '%',
            'entero'     => number_format((int) $valor),
            'fecha'      => $valor->format('d/m/Y H:i'),
            default      => (string) $valor,
        };
    };
    $tonoEstado = fn (string $estado) => match (strtok(mb_strtolower($estado), ' ')) {
        'publicado', 'aprobado', 'confirmada', 'activo' => 'ok',
        'borrador', 'pendiente', 'activa' => 'espera',
        'cancelado', 'cancelada', 'rechazado', 'bloqueado' => 'mal',
        default => 'neutro',
    };
    $numericas = ['entero', 'moneda', 'porcentaje'];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $titulo }}</title>
    <style>
        @page { margin: 30px 34px 44px; }

        body { font-family: DejaVu Sans, sans-serif; color: #2a1d15; font-size: 8.5px; }

        .encabezado { background: #2a1d15; color: #fff; padding: 16px 20px 15px; }
        .encabezado .marca { color: #e3c99f; font-size: 8px; letter-spacing: 2px; }
        .encabezado h1 { margin: 6px 0 5px; font-size: 20px; font-weight: normal; }
        .encabezado .periodo { color: #d9cbb8; font-size: 8.5px; }

        .indicadores { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin: 16px -8px 6px; }
        .indicadores td { width: 25%; padding: 10px 12px; background: #faf5ee; border: 1px solid #e6d8c6; border-top: 2px solid #8a6630; vertical-align: top; }
        .indicadores small { display: block; color: #8a6630; font-size: 7px; font-weight: bold; letter-spacing: 1px; }
        .indicadores strong { display: block; margin: 5px 0 3px; font-size: 17px; }
        .indicadores span { color: #7a6a5c; font-size: 7.5px; }

        h2 { margin: 16px 0 8px; font-size: 12px; }

        .desglose { border-collapse: collapse; min-width: 300px; }
        .desglose th { background: #8a6630; color: #fff; padding: 6px 10px; font-size: 7.5px; text-align: left; }
        .desglose td { padding: 5px 10px; border-bottom: 1px solid #e6d8c6; }
        .desglose .cero td { color: #a89a8c; }
        .derecha { text-align: right; }

        table.detalle { width: 100%; border-collapse: collapse; }
        .detalle th { background: #2a1d15; color: #fff; padding: 7px 5px; font-size: 7px; text-align: left; }
        .detalle td { padding: 6px 5px; border-bottom: 1px solid #ece2d5; font-size: 7.5px; }
        .detalle tbody tr:nth-child(even) td { background: #faf5ee; }
        .detalle .vacio { color: #b3a699; text-align: center; }
        .estado { padding: 1px 5px; border-radius: 3px; font-weight: bold; }
        .estado.ok { background: #e3f1e7; color: #25593a; }
        .estado.espera { background: #fbf0dc; color: #7a5a24; }
        .estado.mal { background: #f8e3df; color: #8f3424; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }

        .pie { position: fixed; bottom: -28px; left: 0; right: 0; color: #9a8a7a; font-size: 7px; border-top: 1px solid #e6d8c6; padding-top: 6px; }
    </style>
</head>
<body>
    <div class="pie">Soirée · {{ $titulo }} · Documento de uso administrativo</div>

    <div class="encabezado">
        <div class="marca">SOIRÉE · INFORME ADMINISTRATIVO</div>
        <h1>{{ $titulo }}</h1>
        <div class="periodo">
            {{ $periodo }} · Generado el {{ $generado->format('d/m/Y \a \l\a\s H:i') }}@if ($generado_por) por {{ $generado_por }}@endif
        </div>
    </div>

    <table class="indicadores">
        <tr>
            @foreach ($indicadores as $indicador)
                <td>
                    <small>{{ mb_strtoupper($indicador['etiqueta']) }}</small>
                    <strong>{{ $formatear($indicador['valor'], $indicador['formato']) }}</strong>
                    <span>{{ $indicador['nota'] ?? '' }}</span>
                </td>
            @endforeach
        </tr>
    </table>

    <h2>{{ $desglose['titulo'] }}</h2>
    <table class="desglose">
        <thead>
            <tr>
                <th>{{ $desglose['titulo'] === 'Por rol' ? 'Rol' : 'Estado' }}</th>
                <th class="derecha">Cantidad</th>
                <th class="derecha">%</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($desglose['filas'] as $item)
                <tr @class(['cero' => $item['cantidad'] === 0])>
                    <td>{{ $item['etiqueta'] }}</td>
                    <td class="derecha">{{ $item['cantidad'] }}</td>
                    <td class="derecha">{{ round($item['porcentaje'] * 100) }}%</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Detalle · {{ $total }} {{ $total === 1 ? 'registro' : 'registros' }}</h2>
    <table class="detalle">
        <thead>
            <tr>
                @foreach ($columnas as $columna)
                    <th @class(['derecha' => in_array($columna['formato'], $numericas, true)])>{{ $columna['titulo'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($filas as $registro)
                <tr>
                    @foreach ($columnas as $columna)
                        @php($valor = $registro[$columna['clave']] ?? null)
                        @if ($valor === null || $valor === '')
                            <td class="vacio">—</td>
                        @elseif (in_array($columna['clave'], ['estado', 'rol'], true))
                            <td><span class="estado {{ $tonoEstado((string) $valor) }}">{{ $valor }}</span></td>
                        @else
                            <td @class(['derecha' => in_array($columna['formato'], $numericas, true)])>{{ $formatear($valor, $columna['formato']) }}</td>
                        @endif
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($columnas) }}" class="vacio">No hay registros en este período.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
