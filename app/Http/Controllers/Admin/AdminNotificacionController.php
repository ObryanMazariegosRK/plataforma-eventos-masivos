<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class AdminNotificacionController extends Controller
{
    /**
     * Actividad reciente de reservas, pagos y boletos.
     *
     * No modifica registros ni dispara notificaciones
     * hacia los clientes.
     */
    public function index(): JsonResponse
    {
        // Nuevas reservas.
        $reservas = DB::table('reservas as r')
            ->leftJoin('eventos as e', 'e.id', '=', 'r.evento_id')
            ->whereNull('r.deleted_at')
            ->orderByDesc('r.created_at')
            ->limit(20)
            ->get([
                'r.id',
                'r.estado',
                'r.total',
                'r.created_at',
                'e.nombre as evento',
            ])
            ->map(fn ($r) => [
                'clave' => 'reserva-' . $r->id,
                'tipo' => 'reserva',
                'titulo' => 'Nueva reserva registrada',
                'descripcion' =>
                    'Reserva #' . $r->id . ' para ' .
                    ($r->evento ?: 'un evento') .
                    '. Estado: ' . $r->estado . '.',
                'fecha' => $r->created_at,
                'referencia_id' => (int) $r->id,
            ]);

        // Pagos efectivamente aprobados.
        $pagos = DB::table('pagos as p')
            ->join('reservas as r', 'r.id', '=', 'p.reserva_id')
            ->leftJoin('eventos as e', 'e.id', '=', 'r.evento_id')
            ->whereNull('p.deleted_at')
            ->where('p.estado', 'aprobado')
            ->orderByDesc('p.created_at')
            ->limit(20)
            ->get([
                'p.id',
                'p.monto',
                'p.created_at',
                'e.nombre as evento',
            ])
            ->map(fn ($p) => [
                'clave' => 'pago-' . $p->id,
                'tipo' => 'pago',
                'titulo' => 'Pago aprobado',
                'descripcion' =>
                    'Pago #' . $p->id . ' de Q' .
                    number_format((float) $p->monto, 2) .
                    ' para ' . ($p->evento ?: 'un evento') . '.',
                'fecha' => $p->created_at,
                'referencia_id' => (int) $p->id,
            ]);

        // Grupos de boletos emitidos por reserva.
        $compras = DB::table('boletos as b')
            ->join('reservas as r', 'r.id', '=', 'b.reserva_id')
            ->leftJoin('eventos as e', 'e.id', '=', 'r.evento_id')
            ->whereNull('b.deleted_at')
            ->where('b.estado', 'emitido')
            ->groupBy('b.reserva_id', 'e.nombre')
            ->orderByRaw('MAX(b.created_at) DESC')
            ->limit(20)
            ->get([
                'b.reserva_id',
                'e.nombre as evento',
                DB::raw('COUNT(b.id) as cantidad'),
                DB::raw('MAX(b.created_at) as fecha'),
            ])
            ->map(fn ($b) => [
                'clave' => 'boletos-' . $b->reserva_id,
                'tipo' => 'compra',
                'titulo' => 'Boletos emitidos',
                'descripcion' =>
                    $b->cantidad . ' boleto(s) emitido(s) para ' .
                    ($b->evento ?: 'un evento') .
                    ', reserva #' . $b->reserva_id . '.',
                'fecha' => $b->fecha,
                'referencia_id' => (int) $b->reserva_id,
            ]);

        $actividad = $reservas
            ->concat($pagos)
            ->concat($compras)
            ->sortByDesc('fecha')
            ->take(30)
            ->values();

        return response()->json([
            'data' => $actividad,
            'total' => $actividad->count(),
        ]);
    }
}
