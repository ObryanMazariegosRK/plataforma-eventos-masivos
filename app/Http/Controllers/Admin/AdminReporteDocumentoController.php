<?php

namespace App\Http\Controllers\Admin;

use App\Application\Abstractions\Admin\IGenerarReporteAdminUseCase;
use App\Application\DTOs\Admin\GenerarReporteAdminDTO;
use App\Http\Controllers\Controller;
use App\Infrastructure\Admin\ReporteExcel;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

// Panel → Reportes: el mismo informe (GenerarReporteAdminUseCase) en Excel o en PDF
class AdminReporteDocumentoController extends Controller
{
    public function excel(Request $request, IGenerarReporteAdminUseCase $generar, ReporteExcel $excel)
    {
        $informe = $generar->execute($this->datos($request, config('reportes.limite_excel')));

        return response()->download($excel->generar($informe), $this->nombreArchivo($informe, 'xlsx'), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    public function pdf(Request $request, IGenerarReporteAdminUseCase $generar)
    {
        $informe = $generar->execute($this->datos($request, config('reportes.limite_pdf')));

        return Pdf::loadView('admin.reportes.pdf', $informe)
            ->setPaper('a4', 'landscape')
            ->download($this->nombreArchivo($informe, 'pdf'));
    }

    private function datos(Request $request, int $limite): GenerarReporteAdminDTO
    {
        $filtros = $request->validate([
            'tipo' => ['required', 'in:reservas,pagos,eventos,usuarios'],
            'desde' => ['nullable', 'date_format:Y-m-d'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde'],
        ]);
        $usuario = $request->user();

        return new GenerarReporteAdminDTO(
            tipo: $filtros['tipo'],
            limite: $limite,
            zonaHoraria: config('reportes.zona_horaria'),
            desde: $filtros['desde'] ?? null,
            hasta: $filtros['hasta'] ?? null,
            generadoPor: $usuario ? trim("{$usuario->nombre} {$usuario->apellido}") : null,
        );
    }

    private function nombreArchivo(array $informe, string $extension): string
    {
        return 'soiree_'.$informe['tipo'].'_'.$informe['generado']->format('Ymd_His').'.'.$extension;
    }
}
