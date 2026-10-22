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
        $reservas = DB::table('reservas as r')
            ->leftJoin('eventos as e', 'e.id', '=', 'r.evento_id')
            ->leftJoin('usuarios as u', 'u.id', '=', 'r.usuario_id')
            ->whereNull('r.deleted_at')
            ->select([
                'r.id',
                'r.usuario_id',
                'r.evento_id',
                'r.estado',
                'r.total',
                'r.expira_en',
                'r.created_at',
                'r.updated_at',
                'e.nombre as evento',
                'u.nombre as cliente_nombre',
                'u.apellido as cliente_apellido',
                'u.email as cliente_email',
            ])
            ->orderByDesc('r.id')
            ->paginate(10);

        return response()->json($reservas);
    }

    public function pagos(Request $request): JsonResponse
    {
        $pagos = DB::table('pagos as p')
            ->leftJoin('reservas as r', 'r.id', '=', 'p.reserva_id')
            ->leftJoin('eventos as e', 'e.id', '=', 'r.evento_id')
            ->leftJoin('usuarios as u', 'u.id', '=', 'r.usuario_id')
            ->whereNull('p.deleted_at')
            ->select([
                'p.id',
                'p.reserva_id',
                'p.monto',
                'p.estado',
                'p.created_at',
                'p.updated_at',
                'r.usuario_id',
                'r.evento_id',
                'r.estado as reserva_estado',
                'e.nombre as evento',
                'u.nombre as cliente_nombre',
                'u.apellido as cliente_apellido',
                'u.email as cliente_email',
            ])
            ->orderByDesc('p.id')
            ->paginate(10);

        return response()->json($pagos);
    }

    /**
     * Consultar una reserva concreta, solo Administrador.
     */
    public function reservaDetalle(int $id): JsonResponse
    {
        $reserva = DB::table('reservas as r')
            ->leftJoin('eventos as e', 'e.id', '=', 'r.evento_id')
            ->leftJoin('usuarios as u', 'u.id', '=', 'r.usuario_id')
            ->where('r.id', $id)
            ->whereNull('r.deleted_at')
            ->select([
                'r.id',
                'r.usuario_id',
                'r.evento_id',
                'r.estado',
                'r.total',
                'r.expira_en',
                'r.created_at',
                'r.updated_at',
                'e.nombre as evento',
                'u.nombre as cliente_nombre',
                'u.apellido as cliente_apellido',
                'u.email as cliente_email',
            ])
            ->first();

        abort_if(!$reserva, 404, 'Reserva no encontrada.');

        return response()->json(['data' => $reserva]);
    }

    /**
     * Consultar un pago concreto, solo Administrador.
     */
    public function pagoDetalle(int $id): JsonResponse
    {
        $pago = DB::table('pagos as p')
            ->leftJoin('reservas as r', 'r.id', '=', 'p.reserva_id')
            ->leftJoin('eventos as e', 'e.id', '=', 'r.evento_id')
            ->leftJoin('usuarios as u', 'u.id', '=', 'r.usuario_id')
            ->where('p.id', $id)
            ->whereNull('p.deleted_at')
            ->select([
                'p.id',
                'p.reserva_id',
                'p.monto',
                'p.estado',
                'p.created_at',
                'p.updated_at',
                'r.usuario_id',
                'r.evento_id',
                'r.estado as reserva_estado',
                'e.nombre as evento',
                'u.nombre as cliente_nombre',
                'u.apellido as cliente_apellido',
                'u.email as cliente_email',
            ])
            ->first();

        abort_if(!$pago, 404, 'Pago no encontrado.');

        return response()->json(['data' => $pago]);
    }

    /**
     * Exportación administrativa CSV para Excel.
     * Solo lectura; no modifica información.
     */
    public function reporteCsv(
        Request $request
    ): StreamedResponse {
        $datos = $request->validate([
            'tipo' => [
                'required',
                'in:usuarios,eventos,reservas,pagos',
            ],
            'desde' => [
                'nullable',
                'date_format:Y-m-d',
                'required_with:hasta',
            ],
            'hasta' => [
                'nullable',
                'date_format:Y-m-d',
                'required_with:desde',
                'after_or_equal:desde',
            ],
        ]);

        $tipo = $datos['tipo'];

        // Encabezados comprensibles y columnas reales de MySQL.
        switch ($tipo) {
            case 'reservas':
                $consulta = DB::table('reservas as r')
                    ->leftJoin('eventos as e', 'e.id', '=', 'r.evento_id')
                    ->leftJoin('usuarios as u', 'u.id', '=', 'r.usuario_id')
                    ->whereNull('r.deleted_at')
                    ->select([
                        'r.id as numero',
                        'e.nombre as evento',
                        'u.nombre as nombre',
                        'u.apellido as apellido',
                        'u.email as correo',
                        'r.estado as estado',
                        'r.total as importe',
                        'r.expira_en as vencimiento',
                        'r.created_at as registro',
                    ]);

                $fechaColumna = 'r.created_at';

                $columnas = [
                    'numero' => 'Numero de reserva',
                    'evento' => 'Evento',
                    'nombre' => 'Nombre',
                    'apellido' => 'Apellido',
                    'correo' => 'Correo electronico',
                    'estado' => 'Estado',
                    'importe' => 'Total (GTQ)',
                    'vencimiento' => 'Vencimiento',
                    'registro' => 'Fecha de registro',
                ];
                break;

            case 'pagos':
                $consulta = DB::table('pagos as p')
                    ->leftJoin('reservas as r', 'r.id', '=', 'p.reserva_id')
                    ->leftJoin('eventos as e', 'e.id', '=', 'r.evento_id')
                    ->leftJoin('usuarios as u', 'u.id', '=', 'r.usuario_id')
                    ->whereNull('p.deleted_at')
                    ->select([
                        'p.id as numero',
                        'p.reserva_id as reserva',
                        'e.nombre as evento',
                        'u.nombre as nombre',
                        'u.apellido as apellido',
                        'u.email as correo',
                        'p.estado as estado',
                        'p.monto as importe',
                        'p.created_at as registro',
                    ]);

                $fechaColumna = 'p.created_at';

                $columnas = [
                    'numero' => 'Numero de pago',
                    'reserva' => 'Reserva',
                    'evento' => 'Evento',
                    'nombre' => 'Nombre',
                    'apellido' => 'Apellido',
                    'correo' => 'Correo electronico',
                    'estado' => 'Estado del pago',
                    'importe' => 'Monto (GTQ)',
                    'registro' => 'Fecha de registro',
                ];
                break;

            case 'eventos':
                $consulta = DB::table('eventos as e')
                    ->leftJoin('recintos as re', 're.id', '=', 'e.recinto_id')
                    ->whereNull('e.deleted_at')
                    ->select([
                        'e.id as numero',
                        'e.nombre as evento',
                        're.nombre as recinto',
                        're.ciudad as ciudad',
                        'e.estado as estado',
                        'e.fecha_evento as fecha',
                        'e.fecha_publicacion as publicacion',
                        'e.created_at as registro',
                    ]);

                $fechaColumna = 'e.created_at';

                $columnas = [
                    'numero' => 'Numero de evento',
                    'evento' => 'Evento',
                    'recinto' => 'Recinto',
                    'ciudad' => 'Ciudad',
                    'estado' => 'Estado',
                    'fecha' => 'Fecha del evento',
                    'publicacion' => 'Fecha de publicacion',
                    'registro' => 'Fecha de registro',
                ];
                break;

            default:
                $consulta = DB::table('usuarios as u')
                    ->whereNull('u.deleted_at')
                    ->select([
                        'u.id as numero',
                        'u.nombre as nombre',
                        'u.apellido as apellido',
                        'u.email as correo',
                        'u.rol as rol',
                        'u.estado as estado',
                        'u.created_at as registro',
                    ]);

                $fechaColumna = 'u.created_at';

                $columnas = [
                    'numero' => 'Numero de usuario',
                    'nombre' => 'Nombre',
                    'apellido' => 'Apellido',
                    'correo' => 'Correo electronico',
                    'rol' => 'Rol',
                    'estado' => 'Estado',
                    'registro' => 'Fecha de registro',
                ];
                break;
        }

        if (!empty($datos['desde'])) {
            $consulta->whereDate(
                $fechaColumna,
                '>=',
                $datos['desde']
            );
        }

        if (!empty($datos['hasta'])) {
            $consulta->whereDate(
                $fechaColumna,
                '<=',
                $datos['hasta']
            );
        }

        $consulta->orderBy($fechaColumna)
                 ->orderBy(
                     match ($tipo) {
                         'reservas' => 'r.id',
                         'pagos' => 'p.id',
                         'eventos' => 'e.id',
                         default => 'u.id',
                     }
                 );

        $nombreArchivo = 'soiree_' .
            $tipo . '_' .
            now()->format('Ymd_His') .
            '.csv';

        return response()->streamDownload(
            function () use ($consulta, $columnas) {
                $salida = fopen('php://output', 'w');

                // BOM para mostrar caracteres UTF-8 en Excel.
                fwrite($salida, "\xEF\xBB\xBF");
                fwrite($salida, "sep=,\r\n");

                fputcsv($salida, array_values($columnas));

                foreach ($consulta->cursor() as $fila) {
                    $valores = [];

                    foreach ($columnas as $campo => $titulo) {
                        $valor = $fila->{$campo} ?? '';

                        if ($valor === null) {
                            $valor = '';
                        }

                        if ($campo === 'importe' && $valor !== '') {
                            // Sin símbolo Q: permite calcular sumas.
                            $valor = number_format(
                                (float) $valor,
                                2,
                                '.',
                                ''
                            );
                        } elseif (in_array($campo, [
                            'registro',
                            'vencimiento',
                            'fecha',
                            'publicacion',
                        ], true) && $valor !== '') {
                            $valor = \Carbon\Carbon::parse($valor)
                                ->format('Y-m-d H:i:s');
                        } else {
                            $valor = (string) $valor;
                        }

                        // Evitar fórmulas ejecutables al abrir
                        // campos de texto en aplicaciones de hojas
                        // de cálculo.
                        if (
                            $campo !== 'importe' &&
                            preg_match('/^[\s\x00-\x1F]*[=+@\-]/u', $valor)
                        ) {
                            $valor = "'" . $valor;
                        }

                        $valores[] = $valor;
                    }

                    fputcsv($salida, $valores);
                }

                fclose($salida);
            },
            $nombreArchivo,
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }

}
