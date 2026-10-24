<?php

namespace App\Http\Controllers\Admin;

use App\Application\UseCases\Admin\ObtenerResumenAdminUseCase;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        // Filtros del panel: estado de la reserva y evento
        $filtros = $request->validate([
            'estado' => ['sometimes', 'nullable', \Illuminate\Validation\Rule::enum(\App\Domain\Enums\Reservas\EstadoReserva::class)],
            'evento_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ]);

        $reservas = DB::table('reservas as r')
            ->leftJoin('eventos as e', 'e.id', '=', 'r.evento_id')
            ->leftJoin('usuarios as u', 'u.id', '=', 'r.usuario_id')
            ->whereNull('r.deleted_at')
            ->when($filtros['estado'] ?? null, fn ($q, $estado) => $q->where('r.estado', $estado))
            ->when($filtros['evento_id'] ?? null, fn ($q, $evento) => $q->where('r.evento_id', $evento))
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
}
