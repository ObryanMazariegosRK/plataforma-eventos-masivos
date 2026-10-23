<?php

namespace App\Data\Finanzas;

use App\Domain\Abstractions\Finanzas\IFinanzasRepository;
use Illuminate\Support\Facades\DB;

// Lecturas para las gráficas de ventas y ganancias del panel (solo lectura)
class FinanzasRepository implements IFinanzasRepository
{
    public function pagosAprobadosEntre(string $desde, string $hasta): array
    {
        $boletos = DB::table('inventario')->whereNull('deleted_at')->whereNotNull('reserva_id')
            ->selectRaw('reserva_id, COUNT(*) as boletos')->groupBy('reserva_id');

        // El pago se marca aprobado al cobrarse: su updated_at es el momento de la venta
        return DB::table('pagos as p')
            ->leftJoinSub($boletos, 'b', 'b.reserva_id', '=', 'p.reserva_id')
            ->where('p.estado', 'aprobado')
            ->whereNull('p.deleted_at')
            ->where('p.updated_at', '>=', $desde)
            ->where('p.updated_at', '<', $hasta)
            ->orderBy('p.updated_at')
            ->get(['p.updated_at as pagado_en', 'p.monto', 'b.boletos'])
            ->map(fn ($fila) => [
                'pagado_en' => (string) $fila->pagado_en,
                'monto' => (float) $fila->monto,
                'boletos' => (int) $fila->boletos,
            ])
            ->all();
    }

    public function resultadosPorEvento(): array
    {
        $ingresos = DB::table('pagos as p')
            ->join('reservas as r', 'r.id', '=', 'p.reserva_id')
            ->where('p.estado', 'aprobado')->whereNull('p.deleted_at')
            ->selectRaw('r.evento_id, SUM(p.monto) as ingresos_brutos')
            ->groupBy('r.evento_id');

        // Dinero devuelto o por devolver (aprobado o ya procesado)
        $reembolsos = DB::table('reembolsos as re')
            ->join('pagos as p', 'p.id', '=', 're.pago_id')
            ->join('reservas as r', 'r.id', '=', 'p.reserva_id')
            ->whereIn('re.estado', ['aprobado', 'procesado'])->whereNull('re.deleted_at')
            ->selectRaw('r.evento_id, SUM(re.monto) as reembolsos')
            ->groupBy('r.evento_id');

        $costos = DB::table('costos_evento')->whereNull('deleted_at')
            ->selectRaw('evento_id, SUM(monto) as costos')
            ->groupBy('evento_id');

        // Precio promedio por lugar a la venta (para calcular el punto de equilibrio)
        $lugares = DB::table('inventario as i')
            ->leftJoin('precios as pr', function ($join) {
                $join->on('pr.evento_id', '=', 'i.evento_id')
                    ->on('pr.localidad_id', '=', 'i.localidad_id')
                    ->whereNull('pr.deleted_at');
            })
            ->whereNull('i.deleted_at')
            ->selectRaw("i.evento_id, COUNT(*) as lugares, SUM(CASE WHEN i.estado = 'vendido' THEN 1 ELSE 0 END) as vendidos, AVG(pr.precio) as precio_promedio")
            ->groupBy('i.evento_id');

        return DB::table('eventos as e')
            ->leftJoinSub($ingresos, 'ing', 'ing.evento_id', '=', 'e.id')
            ->leftJoinSub($reembolsos, 'ree', 'ree.evento_id', '=', 'e.id')
            ->leftJoinSub($costos, 'co', 'co.evento_id', '=', 'e.id')
            ->leftJoinSub($lugares, 'lu', 'lu.evento_id', '=', 'e.id')
            ->whereNull('e.deleted_at')
            ->where('e.estado', '!=', 'borrador')
            ->orderBy('e.fecha_evento')
            ->get([
                'e.id', 'e.nombre', 'e.estado', 'e.fecha_evento', 'ing.ingresos_brutos', 'ree.reembolsos',
                'co.costos', 'lu.vendidos', 'lu.lugares', 'lu.precio_promedio',
            ])
            ->map(fn ($f) => [
                'id' => (int) $f->id,
                'nombre' => $f->nombre,
                'estado' => $f->estado,
                'fecha_evento' => $f->fecha_evento ? (string) $f->fecha_evento : null,
                'ingresos_brutos' => (float) $f->ingresos_brutos,
                'reembolsos' => (float) $f->reembolsos,
                'costos' => (float) $f->costos,
                'vendidos' => (int) $f->vendidos,
                'lugares' => (int) $f->lugares,
                'precio_promedio' => (float) $f->precio_promedio,
            ])
            ->all();
    }
}
