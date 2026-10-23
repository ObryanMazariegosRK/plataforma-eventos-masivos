<?php

namespace App\Data\Bitacora;

use App\Domain\Abstractions\Bitacora\IContextoBitacora;
use Illuminate\Support\Facades\DB;

// Lee de la base principal lo necesario para describir una acción (nombre del evento,
// cómo estaba antes del cambio...). Solo columnas de negocio: nunca contraseñas ni códigos.
class ContextoBitacora implements IContextoBitacora
{
    public function evento(int $id): ?array
    {
        return $this->fila(DB::table('eventos')
            ->leftJoin('recintos', 'recintos.id', '=', 'eventos.recinto_id')
            ->where('eventos.id', $id)
            ->select('eventos.nombre', 'eventos.estado', 'eventos.fecha_evento', 'recintos.nombre as recinto', 'eventos.imagen_url'));
    }

    public function usuario(int $id): ?array
    {
        return $this->fila(DB::table('usuarios')->where('id', $id)
            ->select('nombre', 'apellido', 'email', 'rol', 'estado'));
    }

    public function reserva(int $id): ?array
    {
        return $this->fila(DB::table('reservas')
            ->join('eventos', 'eventos.id', '=', 'reservas.evento_id')
            ->where('reservas.id', $id)
            ->select('reservas.estado', 'reservas.total', 'eventos.nombre as evento'));
    }

    public function pago(int $id): ?array
    {
        return $this->fila(DB::table('pagos')
            ->join('reservas', 'reservas.id', '=', 'pagos.reserva_id')
            ->join('eventos', 'eventos.id', '=', 'reservas.evento_id')
            ->where('pagos.id', $id)
            ->select('pagos.estado', 'pagos.monto', 'pagos.reserva_id', 'eventos.nombre as evento'));
    }

    public function reembolso(int $id): ?array
    {
        return $this->fila(DB::table('reembolsos')->where('id', $id)
            ->select('estado', 'monto', 'pago_id'));
    }

    public function costosDeEvento(int $eventoId): float
    {
        return (float) DB::table('costos_evento')->where('evento_id', $eventoId)->whereNull('deleted_at')->sum('monto');
    }

    private function fila($consulta): ?array
    {
        $fila = $consulta->first();

        return $fila ? (array) $fila : null;
    }
}
