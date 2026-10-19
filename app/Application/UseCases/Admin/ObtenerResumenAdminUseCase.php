<?php

namespace App\Application\UseCases\Admin;

use Illuminate\Support\Facades\DB;

class ObtenerResumenAdminUseCase
{
    public function ejecutar(): array
    {
        $usuarios = DB::table('usuarios')->whereNull('deleted_at');
        $eventos = DB::table('eventos')->whereNull('deleted_at');
        $reservas = DB::table('reservas')->whereNull('deleted_at');
        $pagos = DB::table('pagos')->whereNull('deleted_at');

        return [
            'totales' => [
                'usuarios' => (clone $usuarios)->count(),
                'administradores' => (clone $usuarios)->where('rol', 'admin')->count(),
                'eventos' => (clone $eventos)->count(),
                'eventos_publicados' => (clone $eventos)->where('estado', 'publicado')->count(),
                'reservas' => (clone $reservas)->count(),
                'pagos' => (clone $pagos)->count(),
                'monto_pagos_registrados' => round((float) (clone $pagos)->sum('monto'), 2),
            ],
            'reservas_por_estado' => DB::table('reservas')->whereNull('deleted_at')
                ->select('estado')->selectRaw('COUNT(*) as total')->groupBy('estado')->orderBy('estado')->get(),
            'eventos_por_estado' => DB::table('eventos')->whereNull('deleted_at')
                ->select('estado')->selectRaw('COUNT(*) as total')->groupBy('estado')->orderBy('estado')->get(),
            'reservas_recientes' => DB::table('reservas as r')
                ->leftJoin('usuarios as u', 'r.usuario_id', '=', 'u.id')
                ->leftJoin('eventos as e', 'r.evento_id', '=', 'e.id')
                ->whereNull('r.deleted_at')
                ->orderByDesc('r.id')->limit(8)
                ->get(['r.id', 'r.estado', 'r.total', 'r.created_at', 'e.nombre as evento', 'u.nombre as usuario'])
        ];
    }
}
