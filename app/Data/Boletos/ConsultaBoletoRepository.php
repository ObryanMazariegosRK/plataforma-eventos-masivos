<?php

namespace App\Data\Boletos;

use App\Domain\Abstractions\Boletos\IConsultaBoletoRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ConsultaBoletoRepository implements IConsultaBoletoRepository
{
    public function porCodigo(string $codigo): ?array
    {
        $fila = DB::table('boletos')
            ->join('reservas', 'reservas.id', '=', 'boletos.reserva_id')
            ->join('usuarios', 'usuarios.id', '=', 'boletos.usuario_id')
            ->join('inventario', 'inventario.id', '=', 'boletos.inventario_id')
            ->join('localidades', 'localidades.id', '=', 'inventario.localidad_id')
            ->leftJoin('asientos', 'asientos.id', '=', 'inventario.asiento_id')
            ->join('eventos', 'eventos.id', '=', 'reservas.evento_id')
            ->join('recintos', 'recintos.id', '=', 'eventos.recinto_id')
            ->where('boletos.codigo_qr', $codigo)
            ->whereNull('boletos.deleted_at')
            ->first([
                'boletos.id', 'boletos.estado', 'boletos.usado_en', 'boletos.usuario_id', 'boletos.reserva_id',
                'usuarios.nombre', 'usuarios.apellido', 'reservas.estado as reserva_estado',
                'inventario.estado as inventario_estado', 'inventario.reserva_id as inventario_reserva_id',
                'localidades.nombre as localidad', 'localidades.color', 'asientos.fila', 'asientos.numero',
                'eventos.id as evento_id', 'eventos.nombre as evento', 'eventos.fecha_evento',
                'eventos.estado as evento_estado', 'recintos.nombre as recinto',
            ]);

        if ($fila === null) {
            return null;
        }

        return [
            'boleto_id' => (int) $fila->id,
            'estado' => $fila->estado,
            'usado_en' => $fila->usado_en ? Carbon::parse($fila->usado_en)->toIso8601String() : null,
            'usuario_id' => (int) $fila->usuario_id,
            'titular' => trim("{$fila->nombre} {$fila->apellido}"),
            'reserva_id' => (int) $fila->reserva_id,
            'reserva_estado' => $fila->reserva_estado,
            // El lugar sigue vendido a ESTA compra (un reembolso lo devuelve a la venta)
            'lugar_vendido' => $fila->inventario_estado === 'vendido' && (int) $fila->inventario_reserva_id === (int) $fila->reserva_id,
            'evento' => [
                'id' => (int) $fila->evento_id,
                'nombre' => $fila->evento,
                'fecha' => Carbon::parse($fila->fecha_evento)->toIso8601String(),
                'estado' => $fila->evento_estado,
                'recinto' => $fila->recinto,
            ],
            'lugar' => [
                'localidad' => $fila->localidad,
                'color' => $fila->color,
                'fila' => $fila->fila,
                'numero' => $fila->numero === null ? null : (int) $fila->numero,
            ],
        ];
    }
}
