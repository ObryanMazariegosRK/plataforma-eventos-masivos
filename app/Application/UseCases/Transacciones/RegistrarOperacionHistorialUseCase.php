<?php

namespace App\Application\UseCases\Transacciones;

use App\Domain\Enums\Transacciones\TipoOperacionHistorial;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class RegistrarOperacionHistorialUseCase
{
    public function execute(
        TipoOperacionHistorial $tipo,
        string $claveIdempotencia,
        ?int $usuarioId = null,
        ?int $eventoId = null,
        ?int $reservaId = null,
        ?int $inventarioId = null,
        ?int $pagoId = null,
        ?int $registradoPor = null,
        ?string $descripcion = null,
        ?array $datos = null
    ): object {

        $claveIdempotencia = trim($claveIdempotencia);

        if (
            $claveIdempotencia === '' ||
            strlen($claveIdempotencia) > 191
        ) {
            throw new DomainException(
                'La clave de idempotencia no es válida.'
            );
        }

        foreach ([
            $usuarioId,
            $eventoId,
            $reservaId,
            $inventarioId,
            $pagoId,
            $registradoPor
        ] as $id) {
            if ($id !== null && $id <= 0) {
                throw new DomainException(
                    'Los identificadores deben ser positivos.'
                );
            }
        }

        $valores = [
            'tipo' => $tipo->value,
            'usuario_id' => $usuarioId,
            'evento_id' => $eventoId,
            'reserva_id' => $reservaId,
            'inventario_id' => $inventarioId,
            'pago_id' => $pagoId,
            'registrado_por' => $registradoPor,
            'descripcion' => $descripcion,
            'datos' => $datos === null
                ? null
                : json_encode($datos, JSON_THROW_ON_ERROR),
        ];

        try {
            $id = DB::table('historial_operaciones')
                ->insertGetId([
                    ...$valores,
                    'clave_idempotencia' => $claveIdempotencia,
                    'created_at' => now(),
                ]);

            return DB::table('historial_operaciones')
                ->where('id', $id)
                ->first();

        } catch (QueryException $e) {

            // Solo recuperamos errores de clave duplicada.
            $codigo = (int) ($e->errorInfo[1] ?? 0);

            if (!in_array($codigo, [19, 1062], true)) {
                throw $e;
            }

            $existente = DB::table('historial_operaciones')
                ->where(
                    'clave_idempotencia',
                    $claveIdempotencia
                )
                ->first();

            if ($existente === null) {
                throw $e;
            }

            // Una clave reutilizada con otros datos
            // no debe sobrescribir ni validar otra operación.
            foreach ($valores as $campo => $valor) {
                $actual = $existente->{$campo};

                if ($campo === 'datos') {
                    $actual = $actual === null
                        ? null
                        : json_decode($actual, true);

                    $valor = $valor === null
                        ? null
                        : json_decode($valor, true);
                }

                if ($actual != $valor) {
                    throw new DomainException(
                        'La clave pertenece a otra operación.'
                    );
                }
            }

            // Reintento idéntico: devolver el registro original.
            return $existente;
        }
    }
}
