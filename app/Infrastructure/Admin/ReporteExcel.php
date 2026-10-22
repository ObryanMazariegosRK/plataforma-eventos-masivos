<?php

namespace App\Infrastructure\Admin;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date as FechaExcel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Dibuja en Excel el informe de GenerarReporteAdminUseCase, todo en una hoja: encabezado,
// indicadores, desglose y el detalle completo (con filtros) justo debajo.
class ReporteExcel
{
    private const CHOCOLATE = '2A1D15';

    private const DORADO = '8A6630';

    private const DORADO_CLARO = 'E3C99F';

    private const CREMA = 'FAF5EE';

    private const LINEA = 'E6D8C6';

    private const TEXTO = '2A1D15';

    private const GRIS = '7A6A5C';

    private const FORMATOS = [
        'moneda' => '"Q" #,##0.00',
        'porcentaje' => '0%',
        'entero' => '#,##0',
        'fecha' => 'dd/mm/yyyy hh:mm',
    ];

    // Ruta de un archivo temporal con el .xlsx (quien lo descarga lo borra después)
    public function generar(array $informe): string
    {
        $libro = new Spreadsheet;
        $libro->getProperties()->setCreator('Soirée')->setTitle($informe['titulo']);
        $libro->getDefaultStyle()->getFont()->setName('Calibri')->setSize(10)->getColor()->setRGB(self::TEXTO);

        $hoja = $libro->getActiveSheet();
        $hoja->setTitle(ucfirst($informe['tipo']));
        $hoja->setShowGridlines(false);

        $columnas = $informe['columnas'];
        $ultima = Coordinate::stringFromColumnIndex(count($columnas));
        foreach ($columnas as $i => $columna) {
            $hoja->getColumnDimension(Coordinate::stringFromColumnIndex($i + 1))->setWidth($columna['ancho']);
        }

        $fila = $this->encabezado($hoja, $informe, $ultima);
        $fila = $this->indicadores($hoja, $informe['indicadores'], count($columnas), $fila + 1);
        $fila = $this->desglose($hoja, $informe['desglose'], $fila + 1);
        $this->detalle($hoja, $informe, $ultima, $fila + 1);

        $configuracion = $hoja->getPageSetup();
        $configuracion->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_A4);
        $configuracion->setFitToPage(true)->setFitToWidth(1)->setFitToHeight(0);
        $hoja->getHeaderFooter()->setOddFooter('&LSoirée · '.$informe['titulo'].'&RPágina &P de &N');
        $hoja->setSelectedCell('A1');

        $ruta = tempnam(sys_get_temp_dir(), 'soiree_excel_');
        try {
            (new Xlsx($libro))->save($ruta);
        } finally {
            $libro->disconnectWorksheets();
        }

        return $ruta;
    }

    private function encabezado(Worksheet $hoja, array $informe, string $ultima): int
    {
        $generado = 'Generado el '.$informe['generado']->format('d/m/Y \a \l\a\s H:i')
            .($informe['generado_por'] ? ' por '.$informe['generado_por'] : '');

        foreach ([
            1 => ['SOIRÉE  ·  INFORME ADMINISTRATIVO', 9, true, self::DORADO_CLARO, 22],
            2 => [$informe['titulo'], 20, true, 'FFFFFF', 34],
            3 => ["{$informe['periodo']}   ·   {$generado}", 10, false, 'D9CBB8', 22],
        ] as $fila => [$texto, $tamano, $negrita, $color, $alto]) {
            $hoja->mergeCells("A{$fila}:{$ultima}{$fila}");
            $hoja->setCellValue("A{$fila}", $texto);
            $hoja->getRowDimension($fila)->setRowHeight($alto);
            $hoja->getStyle("A{$fila}")->getFont()->setSize($tamano)->setBold($negrita)->getColor()->setRGB($color);
        }

        $estilo = $hoja->getStyle("A1:{$ultima}3");
        $estilo->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::CHOCOLATE);
        $estilo->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setIndent(1);
        $hoja->getRowDimension(4)->setRowHeight(12);

        return 4;
    }

    // Cuatro tarjetas (etiqueta, valor grande y nota) repartidas a lo ancho de la tabla
    private function indicadores(Worksheet $hoja, array $indicadores, int $columnas, int $fila): int
    {
        $ancho = max(1, intdiv($columnas, count($indicadores)));

        foreach ($indicadores as $i => $indicador) {
            $desde = Coordinate::stringFromColumnIndex($i * $ancho + 1);
            $hasta = Coordinate::stringFromColumnIndex(($i + 1) * $ancho);

            foreach ([0, 1, 2] as $paso) {
                $hoja->mergeCells("{$desde}".($fila + $paso).":{$hasta}".($fila + $paso));
            }

            $hoja->setCellValue($desde.$fila, mb_strtoupper($indicador['etiqueta']));
            $hoja->getStyle($desde.$fila)->getFont()->setSize(8)->setBold(true)->getColor()->setRGB(self::DORADO);

            $valor = $desde.($fila + 1);
            $hoja->setCellValue($valor, $indicador['valor']);
            $hoja->getStyle($valor)->getNumberFormat()->setFormatCode(self::FORMATOS[$indicador['formato']]);
            $hoja->getStyle($valor)->getFont()->setSize(18)->setBold(true);
            $hoja->getStyle($valor)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

            $hoja->setCellValue($desde.($fila + 2), $indicador['nota'] ?? '');
            $hoja->getStyle($desde.($fila + 2))->getFont()->setSize(8)->getColor()->setRGB(self::GRIS);

            $tarjeta = $hoja->getStyle("{$desde}{$fila}:{$hasta}".($fila + 2));
            $tarjeta->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::CREMA);
            $tarjeta->getAlignment()->setIndent(1)->setVertical(Alignment::VERTICAL_CENTER);
            $tarjeta->getBorders()->getOutline()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::LINEA);
            $tarjeta->getBorders()->getTop()->setBorderStyle(Border::BORDER_MEDIUM)->getColor()->setRGB(self::DORADO);
        }

        $hoja->getRowDimension($fila)->setRowHeight(20);
        $hoja->getRowDimension($fila + 1)->setRowHeight(28);
        $hoja->getRowDimension($fila + 2)->setRowHeight(16);

        return $fila + 3;
    }

    // Tabla pequeña: estado (o rol) · cantidad · porcentaje
    private function desglose(Worksheet $hoja, array $desglose, int $fila): int
    {
        $hoja->setCellValue("A{$fila}", $desglose['titulo']);
        $hoja->getStyle("A{$fila}")->getFont()->setSize(12)->setBold(true);
        $hoja->getRowDimension($fila)->setRowHeight(24);

        $fila++;
        $hoja->mergeCells("A{$fila}:B{$fila}");
        $hoja->fromArray(['', '', 'Cantidad', '%'], null, "A{$fila}");
        $hoja->setCellValue("A{$fila}", $desglose['titulo'] === 'Por rol' ? 'Rol' : 'Estado');
        $this->estiloCabecera($hoja, "A{$fila}:D{$fila}", self::DORADO);

        foreach ($desglose['filas'] as $item) {
            $fila++;
            $hoja->mergeCells("A{$fila}:B{$fila}");
            $hoja->setCellValue("A{$fila}", $item['etiqueta']);
            $hoja->setCellValue("C{$fila}", $item['cantidad']);
            $hoja->setCellValue("D{$fila}", $item['porcentaje']);
            $hoja->getStyle("D{$fila}")->getNumberFormat()->setFormatCode('0%');
            $hoja->getStyle("A{$fila}:D{$fila}")->getBorders()->getBottom()->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB(self::LINEA);
            if ($item['cantidad'] === 0) {
                $hoja->getStyle("A{$fila}:D{$fila}")->getFont()->getColor()->setRGB('A89A8C');
            }
            $hoja->getRowDimension($fila)->setRowHeight(18);
        }

        return $fila + 1;
    }

    private function detalle(Worksheet $hoja, array $informe, string $ultima, int $fila): void
    {
        $total = $informe['total'];
        $hoja->setCellValue("A{$fila}", 'Detalle · '.$total.($total === 1 ? ' registro' : ' registros'));
        $hoja->getStyle("A{$fila}")->getFont()->setSize(12)->setBold(true);
        $hoja->getRowDimension($fila)->setRowHeight(24);

        $cabecera = ++$fila;
        foreach ($informe['columnas'] as $i => $columna) {
            $hoja->setCellValue([$i + 1, $fila], $columna['titulo']);
        }
        $this->estiloCabecera($hoja, "A{$fila}:{$ultima}{$fila}", self::CHOCOLATE);
        $hoja->getRowDimension($fila)->setRowHeight(30);
        $hoja->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($fila, $fila);

        if ($total === 0) {
            $fila++;
            $hoja->mergeCells("A{$fila}:{$ultima}{$fila}");
            $hoja->setCellValue("A{$fila}", 'No hay registros en este período.');
            $hoja->getStyle("A{$fila}")->getFont()->setItalic(true)->getColor()->setRGB(self::GRIS);

            return;
        }

        foreach ($informe['filas'] as $n => $registro) {
            $fila++;
            if ($n % 2 === 1) {
                $hoja->getStyle("A{$fila}:{$ultima}{$fila}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::CREMA);
            }
            foreach ($informe['columnas'] as $i => $columna) {
                $this->celda($hoja, $i + 1, $fila, $registro[$columna['clave']] ?? null, $columna['formato']);
                if ($columna['clave'] === 'estado' || $columna['clave'] === 'rol') {
                    $this->colorEstado($hoja, $i + 1, $fila, (string) $registro[$columna['clave']]);
                }
            }
            $hoja->getRowDimension($fila)->setRowHeight(20);
        }

        $tabla = $hoja->getStyle('A'.($cabecera + 1).":{$ultima}{$fila}");
        $tabla->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $tabla->getBorders()->getHorizontal()->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB(self::LINEA);
        $tabla->getBorders()->getBottom()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB(self::LINEA);
        $hoja->setAutoFilter("A{$cabecera}:{$ultima}{$fila}");
    }

    private function celda(Worksheet $hoja, int $columna, int $fila, mixed $valor, string $formato): void
    {
        if ($valor === null || $valor === '') {
            $hoja->setCellValue([$columna, $fila], '—');
            $hoja->getStyle([$columna, $fila])->getFont()->getColor()->setRGB('B3A699');
            $hoja->getStyle([$columna, $fila])->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            return;
        }

        if ($formato === 'texto') {
            // Como texto explícito: un nombre que empiece con "=" nunca se ejecuta como fórmula
            $hoja->setCellValueExplicit([$columna, $fila], (string) $valor, DataType::TYPE_STRING);

            return;
        }

        $hoja->setCellValue([$columna, $fila], $formato === 'fecha' ? FechaExcel::dateTimeToExcel($valor) : $valor);
        $hoja->getStyle([$columna, $fila])->getNumberFormat()->setFormatCode(self::FORMATOS[$formato]);
    }

    private function colorEstado(Worksheet $hoja, int $columna, int $fila, string $estado): void
    {
        [$fondo, $texto] = match (strtok(mb_strtolower($estado), ' ')) {
            'publicado', 'aprobado', 'confirmada', 'activo' => ['E3F1E7', '25593A'],
            'borrador', 'pendiente', 'activa' => ['FBF0DC', '7A5A24'],
            'cancelado', 'cancelada', 'rechazado', 'bloqueado' => ['F8E3DF', '8F3424'],
            'administrador', 'operador' => ['F1E8DA', self::DORADO],
            default => [null, self::GRIS],
        };

        $estilo = $hoja->getStyle([$columna, $fila]);
        $estilo->getFont()->setBold(true)->getColor()->setRGB($texto);
        if ($fondo) {
            $estilo->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($fondo);
        }
    }

    private function estiloCabecera(Worksheet $hoja, string $rango, string $fondo): void
    {
        $estilo = $hoja->getStyle($rango);
        $estilo->getFont()->setBold(true)->setSize(9)->getColor()->setRGB('FFFFFF');
        $estilo->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($fondo);
        $estilo->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setWrapText(true);
    }
}
