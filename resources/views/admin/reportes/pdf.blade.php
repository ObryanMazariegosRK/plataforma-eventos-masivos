<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <style>
        @page {
            margin: 34px 36px 42px;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            color: #38261b;
            font-size: 9px;
        }

        .header {
            background: #38261b;
            color: #ffffff;
            padding: 18px 22px;
        }

        .brand {
            font-size: 21px;
            font-weight: bold;
        }

        .subtitle {
            margin-top: 5px;
            color: #e3c99f;
            font-size: 9px;
            letter-spacing: 1px;
        }

        .title {
            font-size: 19px;
            margin: 22px 0 7px;
        }

        .description {
            color: #806954;
            line-height: 1.7;
            margin-bottom: 18px;
        }

        .summary {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 23px;
        }

        .summary td {
            width: 25%;
            padding: 12px;
            border: 1px solid #e5d6c4;
            background: #fbf6ee;
        }

        .summary small {
            display: block;
            font-size: 8px;
            color: #92714d;
            margin-bottom: 7px;
        }

        .summary strong {
            display: block;
            font-size: 13px;
        }

        .section-title {
            font-size: 13px;
            margin: 18px 0 12px;
        }

        table.records {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
        }

        .records th {
            background: #38261b;
            color: #fff;
            padding: 9px 6px;
            font-size: 8px;
            text-align: left;
            overflow-wrap: anywhere;
        }

        .records td {
            padding: 8px 6px;
            border-bottom: 1px solid #e8dccd;
            font-size: 8px;
            overflow-wrap: anywhere;
        }

        .records tbody tr:nth-child(even) {
            background: #faf6f0;
        }

        thead {
            display: table-header-group;
        }

        tr {
            page-break-inside: avoid;
        }

        .footer {
            margin-top: 20px;
            color: #927f6d;
            font-size: 8px;
            border-top: 1px solid #e1d4c3;
            padding-top: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand">SOIRÉE</div>
        <div class="subtitle">INFORME ADMINISTRATIVO</div>
    </div>

    <h1 class="title">{{ $titulo }}</h1>

    <p class="description">
        Informe generado desde la plataforma Soirée.
        Contiene los registros correspondientes al período seleccionado.
    </p>

    <table class="summary">
        <tr>
            <td>
                <small>DESDE</small>
                <strong>{{ $desde }}</strong>
            </td>
            <td>
                <small>HASTA</small>
                <strong>{{ $hasta }}</strong>
            </td>
            <td>
                <small>REGISTROS</small>
                <strong>{{ $total }}</strong>
            </td>
            <td>
                <small>GENERADO</small>
                <strong>{{ $generado }}</strong>
            </td>
        </tr>
    </table>

    @if ($tipo === 'pagos')
        <p>
            <strong>Total de pagos aprobados:</strong>
            Q{{ number_format($monto_aprobado, 2) }}
        </p>
        <p class="description">
            Los pagos pendientes o rechazados no forman parte
            de este importe. No se descuentan aquí los reembolsos.
        </p>
    @endif

    <h2 class="section-title">Detalle de registros</h2>

    <table class="records">
        <thead>
            <tr>
                @foreach ($columnas as $encabezado)
                    <th>{{ $encabezado }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($registros as $registro)
                <tr>
                    @foreach ($columnas as $campo => $tituloColumna)
                        <td>
                            @if ($campo === 'monto')
                                Q{{ number_format((float) ($registro->{$campo} ?? 0), 2) }}
                            @else
                                {{ $registro->{$campo} ?? '—' }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($columnas) }}">
                        No se encontraron registros en el período.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Soirée · Plataforma de Eventos Masivos
        · Documento generado automáticamente
        · Uso administrativo
    </div>
</body>
</html>
