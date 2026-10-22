<?php

namespace App\Data\Admin;

use App\Domain\Abstractions\Admin\IReporteAdminRepository;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

// Consultas de los informes del panel. Solo datos de negocio: nunca contraseñas, códigos ni QR.
class ReporteAdminRepository implements IReporteAdminRepository
{
    public function filas(string $tipo, ?string $desde, ?string $hasta, int $limite): array
    {
        [$consulta, $fecha, $id] = match ($tipo) {
            'eventos' => [$this->eventos(), 'e.created_at', 'e.id'],
            'reservas' => [$this->reservas(), 'r.created_at', 'r.id'],
            'pagos' => [$this->pagos(), 'p.created_at', 'p.id'],
            'usuarios' => [$this->usuarios(), 'u.created_at', 'u.id'],
        };

        return $consulta
            ->when($desde, fn (Builder $q) => $q->where($fecha, '>=', $desde))
            ->when($hasta, fn (Builder $q) => $q->where($fecha, '<', $hasta))
            ->orderBy($fecha)
            ->orderBy($id)
            ->limit($limite)
            ->get()
            ->map(fn ($fila) => (array) $fila)
            ->all();
    }

    private function eventos(): Builder
    {
        $ocupacion = DB::table('inventario')->whereNull('deleted_at')
            ->selectRaw("evento_id, COUNT(*) as lugares, SUM(CASE WHEN estado = 'vendido' THEN 1 ELSE 0 END) as vendidos")
            ->groupBy('evento_id');
        $ingresos = DB::table('pagos as p')
            ->join('reservas as r', 'r.id', '=', 'p.reserva_id')
            ->where('p.estado', 'aprobado')->whereNull('p.deleted_at')
            ->selectRaw('r.evento_id, SUM(p.monto) as ingresos')
            ->groupBy('r.evento_id');

        return DB::table('eventos as e')
            ->leftJoin('recintos as re', 're.id', '=', 'e.recinto_id')
            ->leftJoin('usuarios as c', 'c.id', '=', 'e.created_by')
            ->leftJoinSub($ocupacion, 'oc', 'oc.evento_id', '=', 'e.id')
            ->leftJoinSub($ingresos, 'ing', 'ing.evento_id', '=', 'e.id')
            ->whereNull('e.deleted_at')
            ->select([
                'e.id', 'e.nombre', 're.nombre as recinto', 're.ciudad', 'e.fecha_evento', 'e.estado',
                'e.created_at', 'c.nombre as creador_nombre', 'c.apellido as creador_apellido',
                'oc.lugares', 'oc.vendidos', 'ing.ingresos',
            ]);
    }

    private function reservas(): Builder
    {
        $lugares = DB::table('inventario')->whereNull('deleted_at')->whereNotNull('reserva_id')
            ->selectRaw('reserva_id, COUNT(*) as lugares')->groupBy('reserva_id');
        $pagadas = DB::table('pagos')->where('estado', 'aprobado')->whereNull('deleted_at')
            ->selectRaw('reserva_id, MAX(updated_at) as pagada_en')->groupBy('reserva_id');

        return DB::table('reservas as r')
            ->leftJoin('eventos as e', 'e.id', '=', 'r.evento_id')
            ->leftJoin('usuarios as u', 'u.id', '=', 'r.usuario_id')
            ->leftJoinSub($lugares, 'lu', 'lu.reserva_id', '=', 'r.id')
            ->leftJoinSub($pagadas, 'pa', 'pa.reserva_id', '=', 'r.id')
            ->whereNull('r.deleted_at')
            ->select([
                'r.id', 'e.nombre as evento', 'u.nombre', 'u.apellido', 'u.email', 'lu.lugares',
                'r.total', 'r.estado', 'r.created_at', 'pa.pagada_en',
            ]);
    }

    private function pagos(): Builder
    {
        return DB::table('pagos as p')
            ->leftJoin('reservas as r', 'r.id', '=', 'p.reserva_id')
            ->leftJoin('eventos as e', 'e.id', '=', 'r.evento_id')
            ->leftJoin('usuarios as u', 'u.id', '=', 'r.usuario_id')
            ->leftJoin('pasarelas as pa', 'pa.id', '=', 'p.pasarela_id')
            ->whereNull('p.deleted_at')
            ->select([
                'p.id', 'p.reserva_id', 'e.nombre as evento', 'u.nombre', 'u.apellido', 'u.email',
                'pa.nombre as pasarela', 'p.intento', 'p.monto', 'p.estado', 'p.referencia_externa', 'p.created_at',
            ]);
    }

    private function usuarios(): Builder
    {
        $compras = DB::table('reservas')->where('estado', 'confirmada')->whereNull('deleted_at')
            ->selectRaw('usuario_id, COUNT(*) as compras')->groupBy('usuario_id');

        return DB::table('usuarios as u')
            ->leftJoinSub($compras, 'co', 'co.usuario_id', '=', 'u.id')
            ->whereNull('u.deleted_at')
            ->select([
                'u.id', 'u.nombre', 'u.apellido', 'u.email', 'u.rol', 'u.estado', 'u.email_verified_at',
                'u.google_id', 'co.compras', 'u.created_at',
            ]);
    }
}
