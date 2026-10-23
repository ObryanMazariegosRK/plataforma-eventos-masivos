<?php

namespace App\Data\Finanzas;

use App\Domain\Abstractions\Finanzas\ICostoEventoRepository;
use App\Domain\Entities\Finanzas\CostoEvento;
use App\Domain\Enums\Finanzas\CategoriaCosto;
use Illuminate\Support\Facades\DB;

class CostoEventoRepository implements ICostoEventoRepository
{
    public function existeEvento(int $eventoId): bool
    {
        return DB::table('eventos')->where('id', $eventoId)->whereNull('deleted_at')->exists();
    }

    public function deEvento(int $eventoId): array
    {
        return DB::table('costos_evento')
            ->where('evento_id', $eventoId)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get()
            ->map(fn ($fila) => new CostoEvento(
                id: (int) $fila->id,
                eventoId: (int) $fila->evento_id,
                categoria: CategoriaCosto::tryFrom($fila->categoria) ?? CategoriaCosto::OTROS,
                concepto: $fila->concepto,
                monto: (float) $fila->monto,
            ))
            ->all();
    }

    public function reemplazar(int $eventoId, array $costos, int $usuarioId): void
    {
        DB::transaction(function () use ($eventoId, $costos, $usuarioId) {
            $ahora = now();

            DB::table('costos_evento')
                ->where('evento_id', $eventoId)
                ->whereNull('deleted_at')
                ->update(['deleted_at' => $ahora, 'updated_by' => $usuarioId, 'updated_at' => $ahora]);

            DB::table('costos_evento')->insert(array_map(fn (CostoEvento $costo) => [
                'evento_id' => $eventoId,
                'categoria' => $costo->getCategoria()->value,
                'concepto' => $costo->getConcepto(),
                'monto' => $costo->getMonto(),
                'created_by' => $usuarioId,
                'updated_by' => $usuarioId,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ], $costos));
        });
    }
}
