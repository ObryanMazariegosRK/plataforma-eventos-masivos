<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class AdminReporteDocumentoController extends Controller
{
    private function obtenerDatos(Request $request, int $limite): array
    {
        $filtros = $request->validate([
            'tipo' => ['required', 'in:reservas,pagos,eventos,usuarios'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ]);

        $tipo = $filtros['tipo'];

        switch ($tipo) {
            case 'reservas':
                $query = DB::table('reservas as r')
                    ->leftJoin('eventos as e', 'e.id', '=', 'r.evento_id')
                    ->leftJoin('usuarios as u', 'u.id', '=', 'r.usuario_id')
                    ->whereNull('r.deleted_at')
                    ->select([
                        'r.id as numero',
                        'e.nombre as evento',
                        'u.nombre as nombre',
                        'u.apellido as apellido',
                        'r.estado',
                        'r.total as monto',
                        'r.created_at as fecha',
                    ]);
                $fechaCampo = 'r.created_at';
                $ordenId = 'r.id';
                break;

            case 'pagos':
                $query = DB::table('pagos as p')
                    ->leftJoin('reservas as r', 'r.id', '=', 'p.reserva_id')
                    ->leftJoin('eventos as e', 'e.id', '=', 'r.evento_id')
                    ->leftJoin('usuarios as u', 'u.id', '=', 'r.usuario_id')
                    ->whereNull('p.deleted_at')
                    ->select([
                        'p.id as numero',
                        'e.nombre as evento',
                        'u.nombre as nombre',
                        'u.apellido as apellido',
                        'p.estado',
                        'p.monto',
                        'p.created_at as fecha',
                    ]);
                $fechaCampo = 'p.created_at';
                $ordenId = 'p.id';
                break;

            case 'eventos':
                $query = DB::table('eventos as e')
                    ->leftJoin('recintos as re', 're.id', '=', 'e.recinto_id')
                    ->whereNull('e.deleted_at')
                    ->select([
                        'e.id as numero',
                        'e.nombre as evento',
                        're.nombre as recinto',
                        're.ciudad',
                        'e.estado',
                        'e.fecha_evento as fecha',
                    ]);
                $fechaCampo = 'e.created_at';
                $ordenId = 'e.id';
                break;

            default:
                $query = DB::table('usuarios as u')
                    ->whereNull('u.deleted_at')
                    ->select([
                        'u.id as numero',
                        'u.nombre',
                        'u.apellido',
                        'u.email as correo',
                        'u.rol',
                        'u.estado',
                        'u.created_at as fecha',
                    ]);
                $fechaCampo = 'u.created_at';
                $ordenId = 'u.id';
        }

        if (!empty($filtros['desde'])) {
            $query->whereDate($fechaCampo, '>=', $filtros['desde']);
        }

        if (!empty($filtros['hasta'])) {
            $query->whereDate($fechaCampo, '<=', $filtros['hasta']);
        }

        $registros = $query
            ->orderBy($fechaCampo)
            ->orderBy($ordenId)
            ->limit($limite + 1)
            ->get();

        if ($registros->count() > $limite) {
            throw ValidationException::withMessages([
                'reporte' => [
                    "El informe supera {$limite} registros. "
                    . 'Selecciona un período más corto.',
                ],
            ]);
        }

        $columnas = match ($tipo) {
            'reservas', 'pagos' => [
                'numero' => 'Número',
                'evento' => 'Evento',
                'nombre' => 'Nombre',
                'apellido' => 'Apellido',
                'estado' => 'Estado',
                'monto' => 'Monto (GTQ)',
                'fecha' => 'Fecha',
            ],
            'eventos' => [
                'numero' => 'Número',
                'evento' => 'Evento',
                'recinto' => 'Recinto',
                'ciudad' => 'Ciudad',
                'estado' => 'Estado',
                'fecha' => 'Fecha del evento',
            ],
            'usuarios' => [
                'numero' => 'Número',
                'nombre' => 'Nombre',
                'apellido' => 'Apellido',
                'correo' => 'Correo',
                'rol' => 'Rol',
                'estado' => 'Estado',
                'fecha' => 'Registro',
            ],
        };

        $resumenEstados = $registros
            ->groupBy(fn ($fila) => $fila->estado ?? 'sin estado')
            ->map(fn ($grupo) => $grupo->count())
            ->all();

        $montoAprobado = $tipo === 'pagos'
            ? $registros->filter(fn ($fila) =>
                $fila->estado === 'aprobado'
            )->sum('monto')
            : 0;

        return [
            'tipo' => $tipo,
            'titulo' => 'Reporte de ' . ucfirst($tipo),
            'desde' => $filtros['desde'] ?? 'Todos',
            'hasta' => $filtros['hasta'] ?? 'Todos',
            'generado' => now()->format('d/m/Y H:i'),
            'columnas' => $columnas,
            'registros' => $registros,
            'total' => $registros->count(),
            'estados' => $resumenEstados,
            'monto_aprobado' => (float) $montoAprobado,
        ];
    }

    public function excel(Request $request)
    {
        $datos = $this->obtenerDatos($request, 10000);

        $libro = new Spreadsheet();
        $resumen = $libro->getActiveSheet();
        $resumen->setTitle('Resumen');

        $resumen->mergeCells('A1:F2');
        $resumen->setCellValue('A1', 'SOIRÉE | REPORTE ADMINISTRATIVO');

        $resumen->getStyle('A1:F2')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 18,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '38261B'],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        $resumen->setCellValue('A4', 'Tipo de informe');
        $resumen->setCellValue('B4', $datos['titulo']);
        $resumen->setCellValue('A5', 'Fecha de generación');
        $resumen->setCellValue('B5', $datos['generado']);
        $resumen->setCellValue('A6', 'Desde');
        $resumen->setCellValue('B6', $datos['desde']);
        $resumen->setCellValue('A7', 'Hasta');
        $resumen->setCellValue('B7', $datos['hasta']);
        $resumen->setCellValue('A9', 'Registros');
        $resumen->setCellValue('B9', $datos['total']);

        $fila = 11;

        if ($datos['tipo'] === 'pagos') {
            $resumen->setCellValue('A' . $fila, 'Pagos aprobados (GTQ)');
            $resumen->setCellValue('B' . $fila, $datos['monto_aprobado']);
            $resumen->getStyle('B' . $fila)
                ->getNumberFormat()
                ->setFormatCode('"Q" #,##0.00');
            $fila += 2;
        }

        $resumen->setCellValue('A' . $fila, 'Estado');
        $resumen->setCellValue('B' . $fila, 'Cantidad');
        $resumen->getStyle("A{$fila}:B{$fila}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '9C7444'],
            ],
        ]);

        foreach ($datos['estados'] as $estado => $cantidad) {
            $fila++;
            $resumen->setCellValue("A{$fila}", ucfirst($estado));
            $resumen->setCellValue("B{$fila}", $cantidad);
        }

        $resumen->getColumnDimension('A')->setWidth(29);
        $resumen->getColumnDimension('B')->setWidth(34);

        $detalle = $libro->createSheet();
        $detalle->setTitle('Detalle');

        $indice = 1;

        foreach ($datos['columnas'] as $encabezado) {
            $detalle->setCellValue(
                [$indice, 1],
                $encabezado
            );
            $indice++;
        }

        $columnaFinal = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::
            stringFromColumnIndex(count($datos['columnas']));

        $detalle->getStyle("A1:{$columnaFinal}1")
            ->applyFromArray([
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '38261B'],
                ],
            ]);

        $fila = 2;

        foreach ($datos['registros'] as $registro) {
            $indice = 1;

            foreach ($datos['columnas'] as $campo => $titulo) {
                $valor = $registro->{$campo} ?? '';

                if ($campo === 'monto') {
                    $detalle->setCellValue([$indice, $fila], (float) $valor);
                    $detalle->getStyle([$indice, $fila])
                        ->getNumberFormat()
                        ->setFormatCode('"Q" #,##0.00');
                } elseif ($campo === 'numero') {
                    $detalle->setCellValue([$indice, $fila], (int) $valor);
                } else {
                    // Evita interpretar datos de usuarios como fórmulas.
                    $detalle->setCellValueExplicit(
                        [$indice, $fila],
                        (string) ($valor ?? ''),
                        \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
                    );
                }

                $indice++;
            }

            if ($fila % 2 === 0) {
                $detalle->getStyle("A{$fila}:{$columnaFinal}{$fila}")
                    ->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()
                    ->setRGB('F9F3EB');
            }

            $fila++;
        }

        foreach (range(1, count($datos['columnas'])) as $n) {
            $letra = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::
                stringFromColumnIndex($n);
            $detalle->getColumnDimension($letra)->setWidth(24);
        }

        $detalle->freezePane('A2');
        $detalle->setAutoFilter("A1:{$columnaFinal}" . max(1, $fila - 1));

        // ACABADO FINAL SOIREE XLSX
        // Presentación profesional del resumen y el detalle.

        $chocolate = '38261B';
        $dorado = 'AC8352';
        $crema = 'FAF4EB';
        $blanco = 'FFFFFF';

        // Encabezado principal del resumen.
        $resumen->getRowDimension(1)->setRowHeight(30);
        $resumen->getRowDimension(2)->setRowHeight(22);

        $resumen->getStyle('A1:F2')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 18,
                'color' => ['rgb' => $blanco],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => $chocolate],
            ],
            'alignment' => [
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        $resumen->getStyle('A4:A9')
            ->getFont()->setBold(true);

        $resumen->getStyle('A4:A9')
            ->getFont()->getColor()->setRGB($dorado);

        $resumen->getColumnDimension('A')->setWidth(30);
        $resumen->getColumnDimension('B')->setWidth(38);

        foreach (range(4, 9) as $numeroFila) {
            $resumen->getRowDimension($numeroFila)
                ->setRowHeight(24);
        }

        // Encabezados del detalle.
        $detalle->getRowDimension(1)->setRowHeight(38);

        $detalle->getStyle("A1:{$columnaFinal}1")
            ->applyFromArray([
                'font' => [
                    'bold' => true,
                    'size' => 11,
                    'color' => ['rgb' => $blanco],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => $chocolate],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'wrapText' => true,
                ],
            ]);

        $campos = array_keys($datos['columnas']);

        // Anchos específicos: evita fechas con ########.
        foreach ($campos as $indiceCampo => $campo) {
            $letra = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::
                stringFromColumnIndex($indiceCampo + 1);

            $ancho = match ($campo) {
                'numero' => 15,
                'evento', 'recinto' => 35,
                'correo' => 43,
                'nombre', 'apellido' => 25,
                'ciudad', 'estado', 'rol' => 22,
                'fecha' => 29,
                'monto' => 23,
                default => 25,
            };

            $detalle->getColumnDimension($letra)
                ->setWidth($ancho);
        }

        // Formato de la tabla.
        $ultimaFila = $fila - 1;

        if ($ultimaFila >= 2) {
            $detalle->getStyle(
                "A2:{$columnaFinal}{$ultimaFila}"
            )->applyFromArray([
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
                'borders' => [
                    'bottom' => [
                        'borderStyle' =>
                            \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_HAIR,
                        'color' => ['rgb' => 'E7D8C6'],
                    ],
                ],
            ]);

            for ($filaActual = 2;
                 $filaActual <= $ultimaFila;
                 $filaActual++) {

                $detalle->getRowDimension($filaActual)
                    ->setRowHeight(25);
            }

            // Diferenciar visualmente cada estado.
            $indiceEstado = array_search(
                'estado',
                $campos,
                true
            );

            if ($indiceEstado !== false) {
                $letraEstado =
                    \PhpOffice\PhpSpreadsheet\Cell\Coordinate::
                    stringFromColumnIndex($indiceEstado + 1);

                foreach ($datos['registros'] as $indice => $registro) {
                    $numeroFila = $indice + 2;
                    $estadoActual = strtolower(
                        (string) ($registro->estado ?? '')
                    );

                    $fondoEstado = match ($estadoActual) {
                        'aprobado', 'publicado',
                        'confirmada', 'activo' => 'E2F2E8',

                        'pendiente', 'borrador' => 'FFF1D9',

                        'cancelado', 'cancelada',
                        'rechazado' => 'FBE5E2',

                        default => $crema,
                    };

                    $celda = $letraEstado . $numeroFila;

                    $detalle->getStyle($celda)
                        ->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()
                        ->setRGB($fondoEstado);

                    $detalle->getStyle($celda)
                        ->getFont()
                        ->setBold(true);
                }
            }
        }

        // Filtros, navegación e impresión.
        $detalle->freezePane('A2');

        $detalle->setAutoFilter(
            "A1:{$columnaFinal}" . max(1, $ultimaFila)
        );

        $detalle->getPageSetup()->setOrientation(
            \PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::
                ORIENTATION_LANDSCAPE
        );

        $detalle->getPageSetup()->setPaperSize(
            \PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::
                PAPERSIZE_A4
        );

        $detalle->getPageSetup()
            ->setFitToPage(true)
            ->setFitToWidth(1)
            ->setFitToHeight(0);

        $detalle->getPageSetup()
            ->setRowsToRepeatAtTopByStartAndEnd(1, 1);

        $detalle->getHeaderFooter()->setOddFooter(
            '&LSoiree - Informe administrativo'
            . '&RPagina &P de &N'
        );

        // Abrir el libro directamente en el resumen.
        $libro->setActiveSheetIndex(0);

        $nombre = 'soiree_' . $datos['tipo'] . '_'
            . now()->format('Ymd_His') . '.xlsx';

        $temporal = tempnam(sys_get_temp_dir(), 'soiree_excel_');

        if ($temporal === false) {
            abort(500, 'No se pudo preparar el archivo Excel.');
        }

        try {
            (new Xlsx($libro))->save($temporal);
        } catch (\Throwable $e) {
            @unlink($temporal);
            throw $e;
        } finally {
            $libro->disconnectWorksheets();
        }

        return response()->download($temporal, $nombre, [
            'Content-Type' =>
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function pdf(Request $request)
    {
        $datos = $this->obtenerDatos($request, 500);

        $nombre = 'soiree_' . $datos['tipo'] . '_'
            . now()->format('Ymd_His') . '.pdf';

        return Pdf::loadView('admin.reportes.pdf', $datos)
            ->setPaper('a4', 'landscape')
            ->download($nombre);
    }
}
