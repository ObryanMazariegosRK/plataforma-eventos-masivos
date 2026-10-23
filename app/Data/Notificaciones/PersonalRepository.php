<?php

namespace App\Data\Notificaciones;

use App\Domain\Abstractions\Notificaciones\IPersonalRepository;
use Illuminate\Support\Facades\DB;

class PersonalRepository implements IPersonalRepository
{
    public function idsConRol(array $roles): array
    {
        return DB::table('usuarios')
            ->whereIn('rol', $roles)
            ->where('estado', 'activo')
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function nombreDeUsuario(int $usuarioId): string
    {
        $fila = DB::table('usuarios')->where('id', $usuarioId)->first(['nombre', 'apellido']);

        return $fila ? trim("{$fila->nombre} {$fila->apellido}") : 'Un cliente';
    }

    public function ocupacionPorPago(int $pagoId): ?array
    {
        $evento = DB::table('pagos')
            ->join('reservas', 'reservas.id', '=', 'pagos.reserva_id')
            ->join('eventos', 'eventos.id', '=', 'reservas.evento_id')
            ->where('pagos.id', $pagoId)
            ->first(['eventos.id', 'eventos.nombre']);

        if ($evento === null) {
            return null;
        }

        $conteo = DB::table('inventario')
            ->where('evento_id', $evento->id)
            ->whereNull('deleted_at')
            ->selectRaw("COUNT(*) as lugares, SUM(CASE WHEN estado = 'vendido' THEN 1 ELSE 0 END) as vendidos")
            ->first();

        return [
            'evento_id' => (int) $evento->id,
            'evento' => $evento->nombre,
            'vendidos' => (int) $conteo->vendidos,
            'lugares' => (int) $conteo->lugares,
        ];
    }
}
