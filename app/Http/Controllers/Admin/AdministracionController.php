<?php

namespace App\Http\Controllers\Admin;

use App\Application\UseCases\Admin\ObtenerResumenAdminUseCase;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AdministracionController extends Controller
{
    public function resumen(ObtenerResumenAdminUseCase $caso): JsonResponse
    {
        return response()->json($caso->ejecutar());
    }

    public function usuarios(Request $request): JsonResponse
    {
        $filtro = trim((string) $request->query('buscar', ''));
        $query = DB::table('usuarios')->whereNull('deleted_at');
        if ($filtro !== '') {
            $query->where(function ($q) use ($filtro) {
                $q->where('nombre', 'like', '%' . $filtro . '%')
                    ->orWhere('apellido', 'like', '%' . $filtro . '%')
                    ->orWhere('email', 'like', '%' . $filtro . '%');
            });
        }
        return response()->json($query->select('id', 'nombre', 'apellido', 'email', 'rol', 'estado', 'created_at')
            ->orderByDesc('id')->paginate(10));
    }

    public function eventos(Request $request): JsonResponse
    {
        return response()->json(DB::table('eventos')->whereNull('deleted_at')
            ->select('id', 'nombre', 'fecha_evento', 'estado', 'recinto_id')
            ->orderByDesc('id')->paginate(10));
    }

    public function reservas(Request $request): JsonResponse
    {
        return response()->json(DB::table('reservas as r')
            ->leftJoin('eventos as e', 'e.id', '=', 'r.evento_id')
            ->whereNull('r.deleted_at')->select('r.id', 'r.estado', 'r.total', 'r.created_at', 'e.nombre as evento')
            ->orderByDesc('r.id')->paginate(10));
    }

    public function pagos(Request $request): JsonResponse
    {
        return response()->json(DB::table('pagos')->whereNull('deleted_at')
            ->select('id', 'reserva_id', 'monto', 'estado', 'created_at')
            ->orderByDesc('id')->paginate(10));
    }

    public function reporteCsv(Request $request): StreamedResponse
    {
        $validado = $request->validate([
            'tipo' => ['required', 'in:usuarios,eventos,reservas,pagos'],
            'desde' => ['nullable', 'date_format:Y-m-d', 'required_with:hasta'],
            'hasta' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:desde', 'required_with:desde'],
        ]);
        $tipo = $validado['tipo'];
        $columnas = match ($tipo) {
            'usuarios' => ['id', 'nombre', 'apellido', 'email', 'rol', 'estado', 'created_at'],
            'eventos' => ['id', 'recinto_id', 'nombre', 'fecha_evento', 'estado', 'created_at'],
            'reservas' => ['id', 'usuario_id', 'evento_id', 'estado', 'total', 'created_at'],
            'pagos' => ['id', 'reserva_id', 'monto', 'estado', 'created_at'],
        };
        $query = DB::table($tipo)->whereNull('deleted_at')->orderBy('id');
        if (!empty($validado['desde'])) $query->whereDate('created_at', '>=', $validado['desde']);
        if (!empty($validado['hasta'])) $query->whereDate('created_at', '<=', $validado['hasta']);

        return response()->streamDownload(function () use ($query, $columnas) {
            $salida = fopen('php://output', 'w');
            // UTF-8 BOM for Excel. Escape formula-leading input to prevent CSV injection.
            fwrite($salida, "\xEF\xBB\xBF");
            fputcsv($salida, $columnas);
            foreach ($query->select($columnas)->cursor() as $fila) {
                $valores = [];
                foreach ($columnas as $campo) {
                    $valor = (string) ($fila->{$campo} ?? '');
                    if (preg_match('/^[\s\x00-\x1F]*[=+@\-]/u', $valor)) $valor = "'" . $valor;
                    $valores[] = $valor;
                }
                fputcsv($salida, $valores);
            }
            fclose($salida);
        }, 'pulso_' . $tipo . '_' . date('Ymd') . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
