<?php

namespace App\Data\Reembolsos;

use App\Domain\Abstractions\Reembolsos\IReembolsoRepository;
use App\Domain\Entities\Reembolsos\Reembolso;
use App\Domain\Enums\Reembolsos\EstadoReembolso;
use DomainException;
use Illuminate\Support\Facades\DB;

class ReembolsoRepository implements IReembolsoRepository
{
    public function guardar(
        Reembolso $reembolso,
        ?int $registradoPor = null
    ): Reembolso {
        $datos = [
            'pago_id' => $reembolso->getPagoId(),
            'monto' => $reembolso->getMonto(),
            'estado' => $reembolso->getEstado()->value,
            'motivo' => $reembolso->getMotivo(),
            'referencia_externa' => $reembolso->getReferenciaExterna(),
            'updated_by' => $registradoPor,
            'updated_at' => now(),
        ];

        if ($reembolso->getId() === null) {
            $id = DB::table('reembolsos')->insertGetId([
                ...$datos,
                'created_by' => $registradoPor,
                'created_at' => now(),
            ]);
        } else {
            $id = $reembolso->getId();

            $actualizados = DB::table('reembolsos')
                ->where('id', $id)
                ->whereNull('deleted_at')
                ->update($datos);

            if ($actualizados === 0) {
                throw new DomainException(
                    'El reembolso no existe o no está disponible.'
                );
            }
        }

        return $this->buscarPorId($id)
            ?? throw new DomainException(
                'No se pudo recuperar el reembolso.'
            );
    }

    public function buscarPorId(int $id): ?Reembolso
    {
        $registro = DB::table('reembolsos')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        return $registro
            ? $this->aEntidad($registro)
            : null;
    }

    public function buscarPorPago(int $pagoId): array
    {
        return DB::table('reembolsos')
            ->where('pago_id', $pagoId)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->get()
            ->map(fn ($registro) => $this->aEntidad($registro))
            ->all();
    }

    private function aEntidad(object $registro): Reembolso
    {
        return new Reembolso(
            id: (int) $registro->id,
            pagoId: (int) $registro->pago_id,
            monto: (float) $registro->monto,
            estado: EstadoReembolso::from($registro->estado),
            motivo: $registro->motivo,
            referenciaExterna: $registro->referencia_externa,
        );
    }
}
