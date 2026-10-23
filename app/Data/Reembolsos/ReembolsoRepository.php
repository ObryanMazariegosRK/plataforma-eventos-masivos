<?php

namespace App\Data\Reembolsos;

use App\Domain\Abstractions\Reembolsos\IReembolsoRepository;
use App\Domain\Entities\Reembolsos\Reembolso;
use App\Domain\Enums\Boletos\EstadoBoleto;
use App\Domain\Enums\Inventario\EstadoAsiento;
use App\Domain\Enums\Pagos\EstadoPago;
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

    public function contextoDePago(int $pagoId): ?array
    {
        return $this->contexto(fn ($q) => $q->where('pagos.id', $pagoId));
    }

    public function contextoDeReserva(int $reservaId): ?array
    {
        return $this->contexto(fn ($q) => $q
            ->where('pagos.reserva_id', $reservaId)
            ->where('pagos.estado', EstadoPago::APROBADO->value));
    }

    private function contexto(callable $filtro): ?array
    {
        $fila = DB::table('pagos')
            ->join('reservas', 'reservas.id', '=', 'pagos.reserva_id')
            ->join('eventos', 'eventos.id', '=', 'reservas.evento_id')
            ->leftJoin('pasarelas', 'pasarelas.id', '=', 'pagos.pasarela_id')
            ->whereNull('pagos.deleted_at')
            ->whereNull('reservas.deleted_at')
            ->tap($filtro)
            ->orderByDesc('pagos.id')
            ->selectSub(DB::table('boletos')
                ->selectRaw('COUNT(*)')
                ->whereColumn('boletos.reserva_id', 'reservas.id')
                ->whereIn('boletos.estado', [EstadoBoleto::USADO->value, EstadoBoleto::ANULADO->value])
                ->whereNull('boletos.deleted_at'), 'boletos_no_disponibles')
            ->addSelect([
                'pagos.id as pago_id', 'pagos.monto', 'pagos.estado as pago_estado', 'pagos.referencia_externa',
                'pasarelas.codigo as pasarela', 'reservas.id as reserva_id', 'reservas.estado as reserva_estado',
                'reservas.usuario_id', 'eventos.id as evento_id', 'eventos.nombre as evento',
                'eventos.estado as evento_estado', 'eventos.fecha_evento', 'eventos.reprogramado_en',
            ])
            ->first();

        return $fila === null ? null : [
            'pago_id'          => (int) $fila->pago_id,
            'monto_pagado'     => (float) $fila->monto,
            'pago_estado'      => $fila->pago_estado,
            'referencia_cobro' => $fila->referencia_externa,
            'pasarela'         => $fila->pasarela,
            'reserva_id'       => (int) $fila->reserva_id,
            'reserva_estado'   => $fila->reserva_estado,
            'usuario_id'       => (int) $fila->usuario_id,
            'evento_id'        => (int) $fila->evento_id,
            'evento'           => $fila->evento,
            'evento_estado'    => $fila->evento_estado,
            'fecha_evento'     => (string) $fila->fecha_evento,
            'boletos_no_disponibles' => (int) $fila->boletos_no_disponibles,
            'evento_reprogramado' => $fila->reprogramado_en !== null,
        ];
    }

    public function anularBoletosYLiberarLugares(int $reservaId, int $registradoPor, bool $liberar): array
    {
        $boletos = DB::table('boletos')
            ->where('reserva_id', $reservaId)
            ->where('estado', EstadoBoleto::EMITIDO->value)
            ->whereNull('deleted_at')
            ->update(['estado' => EstadoBoleto::ANULADO->value, 'updated_by' => $registradoPor, 'updated_at' => now()]);

        // El lugar vuelve a estar a la venta; la compra reembolsada lo sigue mostrando a
        // través de su boleto anulado (boletos.inventario_id)
        $lugares = ! $liberar ? 0 : DB::table('inventario')
            ->where('reserva_id', $reservaId)
            ->where('estado', EstadoAsiento::VENDIDO->value)
            ->whereNull('deleted_at')
            ->update([
                'estado' => EstadoAsiento::DISPONIBLE->value, 'reserva_id' => null, 'bloqueado_hasta' => null,
                'updated_by' => $registradoPor, 'updated_at' => now(),
            ]);

        return ['boletos_anulados' => $boletos, 'lugares_liberados' => $lugares];
    }

    public function idsDeEvento(int $eventoId, EstadoReembolso $estado): array
    {
        return DB::table('reembolsos')
            ->join('pagos', 'pagos.id', '=', 'reembolsos.pago_id')
            ->join('reservas', 'reservas.id', '=', 'pagos.reserva_id')
            ->where('reservas.evento_id', $eventoId)
            ->where('reembolsos.estado', $estado->value)
            ->whereNull('reembolsos.deleted_at')
            ->orderBy('reembolsos.id')
            ->pluck('reembolsos.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function listar(?string $estado, ?\DateTimeImmutable $desde, int $pagina, int $porPagina): array
    {
        // El período también acota los conteos por estado
        $base = DB::table('reembolsos')
            ->whereNull('reembolsos.deleted_at')
            ->when($desde, fn ($q) => $q->where('reembolsos.created_at', '>=', $desde->format('Y-m-d H:i:s')));

        $porEstado = (clone $base)
            ->groupBy('estado')
            ->get(['estado', DB::raw('COUNT(*) as total')])
            ->mapWithKeys(fn ($f) => [$f->estado => (int) $f->total])
            ->all();

        $consulta = $base
            ->join('pagos', 'pagos.id', '=', 'reembolsos.pago_id')
            ->join('reservas', 'reservas.id', '=', 'pagos.reserva_id')
            ->join('eventos', 'eventos.id', '=', 'reservas.evento_id')
            ->join('usuarios', 'usuarios.id', '=', 'reservas.usuario_id')
            ->leftJoin('usuarios as resolvio', fn ($j) => $j
                ->on('resolvio.id', '=', 'reembolsos.updated_by')
                ->where('reembolsos.estado', '!=', EstadoReembolso::SOLICITADO->value))
            ->when($estado, fn ($q) => $q->where('reembolsos.estado', $estado));

        $total = (clone $consulta)->count();

        // Lugares de cada compra: por sus boletos (los lugares liberados ya no apuntan a la reserva)
        $lugares = DB::table('boletos')
            ->select('reserva_id', DB::raw('COUNT(*) as lugares'))
            ->whereNull('deleted_at')
            ->groupBy('reserva_id');

        $filas = $consulta
            ->leftJoinSub($lugares, 'lugares', 'lugares.reserva_id', '=', 'reservas.id')
            ->orderByRaw("CASE WHEN reembolsos.estado IN ('solicitado', 'aprobado') THEN 0 ELSE 1 END")
            ->orderByDesc('reembolsos.id')
            ->offset(($pagina - 1) * $porPagina)
            ->limit($porPagina)
            ->get([
                'reembolsos.id', 'reembolsos.estado', 'reembolsos.monto', 'reembolsos.motivo',
                'reembolsos.referencia_externa', 'reembolsos.created_at', 'reembolsos.updated_at',
                'pagos.id as pago_id', 'pagos.monto as monto_pagado', 'pagos.referencia_externa as referencia_cobro',
                'reservas.id as reserva_id', 'eventos.id as evento_id', 'eventos.nombre as evento',
                'eventos.fecha_evento', 'eventos.estado as evento_estado',
                'usuarios.nombre', 'usuarios.apellido', 'usuarios.email',
                'resolvio.nombre as resolvio_nombre', 'resolvio.apellido as resolvio_apellido',
                'lugares.lugares',
            ]);

        $fecha = fn ($valor) => $valor ? \Illuminate\Support\Carbon::parse($valor)->toIso8601String() : null;

        return [
            'reembolsos' => $filas->map(fn ($f) => [
                'id'               => (int) $f->id,
                'estado'           => $f->estado,
                'monto'            => (float) $f->monto,
                'monto_pagado'     => (float) $f->monto_pagado,
                'motivo'           => $f->motivo,
                'referencia'       => $f->referencia_externa,
                'referencia_cobro' => $f->referencia_cobro,
                'solicitado_en'    => $fecha($f->created_at),
                'actualizado_en'   => $fecha($f->updated_at),
                'resuelto_por'     => $f->resolvio_nombre ? trim("{$f->resolvio_nombre} {$f->resolvio_apellido}") : null,
                'pago_id'          => (int) $f->pago_id,
                'reserva_id'       => (int) $f->reserva_id,
                'lugares'          => (int) ($f->lugares ?? 0),
                'cliente'          => ['nombre' => trim("{$f->nombre} {$f->apellido}"), 'email' => $f->email],
                'evento'           => [
                    'id'        => (int) $f->evento_id,
                    'nombre'    => $f->evento,
                    'fecha'     => $fecha($f->fecha_evento),
                    'cancelado' => $f->evento_estado === 'cancelado',
                ],
            ])->all(),
            'total'      => $total,
            'por_estado' => $porEstado,
        ];
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
