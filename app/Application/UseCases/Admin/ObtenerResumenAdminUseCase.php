<?php

namespace App\Application\UseCases\Admin;

use Illuminate\Support\Facades\DB;

class ObtenerResumenAdminUseCase
{
    private const ESTADOS_RESERVA = [
        'activa' => 'Activas (apartadas ahora)',
        'confirmada' => 'Confirmadas (pagadas)',
        'vencida' => 'Vencidas',
        'cancelada' => 'Canceladas',
    ];

    private const ESTADOS_EVENTO = [
        'borrador' => 'Borrador',
        'publicado' => 'Publicado',
        'agotado' => 'Agotado',
        'finalizado' => 'Finalizado',
        'cancelado' => 'Cancelado',
    ];

    private function porEstado(string $tabla, array $estados): array
    {
        $conteo = DB::table($tabla)->whereNull('deleted_at')
            ->select('estado')->selectRaw('COUNT(*) as total')->groupBy('estado')
            ->pluck('total', 'estado')->all();

        // Un estado fuera del catálogo (si algún día se agrega uno) también se muestra
        foreach (array_diff_key($conteo, $estados) as $estado => $_) {
            $estados[$estado] = ucfirst($estado);
        }

        return array_map(fn ($estado) => [
            'estado' => $estado,
            'etiqueta' => $estados[$estado],
            'total' => (int) ($conteo[$estado] ?? 0),
        ], array_keys($estados));
    }

    public function ejecutar(): array
    {
        $usuarios = DB::table('usuarios')->whereNull('deleted_at');
        $eventos = DB::table('eventos')->whereNull('deleted_at');
        $reservas = DB::table('reservas')->whereNull('deleted_at');
        $pagos = DB::table('pagos')->whereNull('deleted_at');

        return [
            'totales' => [
                'usuarios' => (clone $usuarios)->count(),
                // Agentes operativos (rol organizador): crean y gestionan eventos
                'operadores' => (clone $usuarios)->where('rol', 'organizador')->count(),
                'eventos' => (clone $eventos)->count(),
                'eventos_publicados' => (clone $eventos)->where('estado', 'publicado')->count(),
                'reservas' => (clone $reservas)->count(),
                'pagos' => (clone $pagos)->count(),
                'pagos_aprobados' => (clone $pagos)->where('estado', 'aprobado')->count(),
                // Solo lo cobrado: los pagos pendientes o rechazados no son ingresos
                'monto_pagos_aprobados' => round((float) (clone $pagos)->where('estado', 'aprobado')->sum('monto'), 2),
            ],
            // Todos los estados posibles en orden lógico (los que no tienen registros salen en 0)
            'reservas_por_estado' => $this->porEstado('reservas', self::ESTADOS_RESERVA),
            'eventos_por_estado' => $this->porEstado('eventos', self::ESTADOS_EVENTO),
            'reservas_recientes' => DB::table('reservas as r')
                ->leftJoin('usuarios as u', 'r.usuario_id', '=', 'u.id')
                ->leftJoin('eventos as e', 'r.evento_id', '=', 'e.id')
                ->whereNull('r.deleted_at')
                ->orderByDesc('r.id')->limit(8)
                ->get(['r.id', 'r.estado', 'r.total', 'r.created_at', 'e.nombre as evento', 'u.nombre as usuario']),
        ];
    }
}
