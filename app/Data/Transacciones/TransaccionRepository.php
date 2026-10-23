<?php

namespace App\Data\Transacciones;

use App\Domain\Abstractions\Transacciones\ITransaccionRepository;
use App\Domain\Entities\Transacciones\Transaccion;
use App\Domain\Enums\Transacciones\TipoTransaccion;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class TransaccionRepository implements ITransaccionRepository
{
    public function registrar(
        Transaccion $transaccion,
        ?int $registradoPor = null
    ): Transaccion {

        if ($transaccion->getId() !== null) {
            throw new DomainException(
                'No se puede volver a registrar una transacción existente.'
            );
        }

        $referencia = trim($transaccion->getReferencia() ?? '');

        if ($referencia === '' || strlen($referencia) > 255) {
            throw new DomainException(
                'La referencia de la transacción es obligatoria y debe ser válida.'
            );
        }

        $tipo = $transaccion->getTipo()->value;

        // Revisar si la operación ya fue registrada.
        $existente = $this->buscarPorReferencia($tipo, $referencia);

        if ($existente !== null) {
            return $this->resolverReintento(
                $existente,
                $transaccion,
                $registradoPor
            );
        }

        try {
            $id = DB::table('transacciones')->insertGetId([
                'tipo' => $tipo,
                'monto' => $transaccion->getMonto(),
                'usuario_id' => $transaccion->getUsuarioId(),
                'reserva_id' => $transaccion->getReservaId(),
                'pago_id' => $transaccion->getPagoId(),
                'referencia' => $referencia,
                'descripcion' => $transaccion->getDescripcion(),
                'created_by' => $registradoPor,
                'created_at' => now(),
            ]);

        } catch (QueryException $e) {

            // Gestionar una inserción concurrente con la misma
            // referencia cuando exista el índice UNIQUE.
            $codigo = (int) ($e->errorInfo[1] ?? 0);

            if (!in_array($codigo, [19, 1062], true)) {
                throw $e;
            }

            $existente = $this->buscarPorReferencia(
                $tipo,
                $referencia
            );

            if ($existente === null) {
                throw $e;
            }

            return $this->resolverReintento(
                $existente,
                $transaccion,
                $registradoPor
            );
        }

        return new Transaccion(
            id: $id,
            tipo: $transaccion->getTipo(),
            monto: $transaccion->getMonto(),
            usuarioId: $transaccion->getUsuarioId(),
            reservaId: $transaccion->getReservaId(),
            pagoId: $transaccion->getPagoId(),
            referencia: $referencia,
            descripcion: $transaccion->getDescripcion(),
        );
    }

    private function buscarPorReferencia(
        string $tipo,
        string $referencia
    ): ?object {
        return DB::table('transacciones')
            ->where('tipo', $tipo)
            ->where('referencia', $referencia)
            ->first();
    }

    private function resolverReintento(
        object $existente,
        Transaccion $transaccion,
        ?int $registradoPor
    ): Transaccion {

        $coincide =
            $existente->tipo === $transaccion->getTipo()->value
            && round((float) $existente->monto, 2)
                === round($transaccion->getMonto(), 2)
            && (int) $existente->usuario_id
                === $transaccion->getUsuarioId()
            && ($existente->reserva_id === null
                ? null : (int) $existente->reserva_id)
                === $transaccion->getReservaId()
            && ($existente->pago_id === null
                ? null : (int) $existente->pago_id)
                === $transaccion->getPagoId()
            && $existente->descripcion
                === $transaccion->getDescripcion()
            && ($existente->created_by === null
                ? null : (int) $existente->created_by)
                === $registradoPor;

        if (!$coincide) {
            throw new DomainException(
                'La referencia pertenece a otra transacción.'
            );
        }

        // Reintento idéntico: devolver el registro original.
        return new Transaccion(
            id: (int) $existente->id,
            tipo: TipoTransaccion::from($existente->tipo),
            monto: (float) $existente->monto,
            usuarioId: (int) $existente->usuario_id,
            reservaId: $existente->reserva_id === null
                ? null : (int) $existente->reserva_id,
            pagoId: $existente->pago_id === null
                ? null : (int) $existente->pago_id,
            referencia: $existente->referencia,
            descripcion: $existente->descripcion,
        );
    }
}
