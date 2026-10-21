<?php
namespace App\Data\Reservas;

use App\Domain\Abstractions\Reservas\IReservaRepository;
use App\Domain\Entities\Reservas\Reserva;
use App\Models\Reservas\Reserva as ReservaModel;   // alias, para no chocar con la Entidad
use Illuminate\Support\Facades\DB;

class ReservaRepository implements IReservaRepository
{
    public function guardar(Reserva $reserva): Reserva
    {
        //Para saber si es una reserva nueva o existente
        $m = $reserva->getId() ? ReservaModel::findOrFail($reserva->getId()) : new ReservaModel();
        //$m es solo el modelo, el objeto que represta una fila de la tabla
        $m->usuario_id = $reserva->getUsuarioId();
        $m->evento_id  = $reserva->getEventoId();
        $m->estado     = $reserva->getEstado();
        $m->expira_en  = $reserva->getExpiraEn();
        $m->total      = $reserva->getTotal();
        //Guardamos el modelo en la base de datos
        $m->save();

        return $this->aEntidad($m);
    }

    public function buscarPorIdParaActualizar(int $id): ?Reserva
    {
        // lockForUpdate: nadie más puede modificar esta reserva hasta que termine la transacción
        // (por ejemplo, el proceso que vence reservas mientras alguien la está pagando)
        $m = ReservaModel::whereKey($id)->lockForUpdate()->first();

        return $m ? $this->aEntidad($m) : null;
    }

    public function buscarPorId(int $id): ?Reserva
    {
        $m = ReservaModel::find($id);

        return $m ? $this->aEntidad($m) : null;
    }

    public function detalle(int $id): ?array
    {
        $reserva = $this->consultaResumen()->where('reservas.id', $id)->first();

        return $reserva ? $this->aResumen($reserva, $this->lugaresDe([$id])[$id] ?? []) : null;
    }

    public function comprasDeUsuario(int $usuarioId): array
    {
        // Pago aprobado de cada reserva (cuándo se pagó y su referencia de cobro)
        $pagos = DB::table('pagos')
            ->where('estado', 'aprobado')
            ->select('reserva_id', DB::raw('MAX(updated_at) as pagado_en'), DB::raw('MAX(referencia_externa) as referencia'))
            ->groupBy('reserva_id');

        $reservas = $this->consultaResumen()
            ->leftJoinSub($pagos, 'pago', 'pago.reserva_id', '=', 'reservas.id')
            ->addSelect('pago.pagado_en', 'pago.referencia')
            ->where('reservas.usuario_id', $usuarioId)
            ->where('reservas.estado', 'confirmada')
            ->get();

        // Todos los lugares en una sola consulta (no una por compra)
        $lugares = $this->lugaresDe($reservas->pluck('id')->all());

        return $reservas->map(fn ($r) => $this->aResumen($r, $lugares[$r->id] ?? []) + [
            'pagado_en'  => $r->pagado_en ? \Illuminate\Support\Carbon::parse($r->pagado_en)->toIso8601String() : null,
            'referencia' => $r->referencia,
        ])->all();
    }

    public function lugaresDePersonaEnEvento(int $usuarioId, int $eventoId, \DateTimeImmutable $ahora, bool $bloquear = false): int
    {
        if ($bloquear) {
            // Candado por persona: la segunda pestaña espera aquí hasta que termine la primera,
            // y entonces ya cuenta los lugares que esa acaba de apartar (SQLite lo ignora)
            DB::table('usuarios')->where('id', $usuarioId)->lockForUpdate()->first();
        }

        return DB::table('inventario')
            ->join('reservas', 'reservas.id', '=', 'inventario.reserva_id')
            ->where('reservas.usuario_id', $usuarioId)
            ->where('reservas.evento_id', $eventoId)
            ->where(fn ($q) => $q
                ->where('reservas.estado', 'confirmada')
                ->orWhere(fn ($q) => $q
                    ->where('reservas.estado', 'activa')
                    ->where('reservas.expira_en', '>', $ahora->format('Y-m-d H:i:s'))))
            ->count();
    }

    private function consultaResumen(): \Illuminate\Database\Query\Builder
    {
        return DB::table('reservas')
            ->join('eventos', 'eventos.id', '=', 'reservas.evento_id')
            ->join('recintos', 'recintos.id', '=', 'eventos.recinto_id')
            ->select(
                'reservas.id', 'reservas.usuario_id', 'reservas.estado', 'reservas.total',
                'eventos.id as evento_id', 'eventos.nombre as evento_nombre', 'eventos.fecha_evento',
                'recintos.nombre as recinto',
            );
    }

    /** @return array<int, list<array>> lugares agrupados por reserva_id */
    private function lugaresDe(array $reservaIds): array
    {
        if ($reservaIds === []) {
            return [];
        }

        // Numeradas: fila y número del asiento; generales: sin asiento (un cupo por fila)
        return DB::table('inventario')
            ->join('localidades', 'localidades.id', '=', 'inventario.localidad_id')
            ->leftJoin('asientos', 'asientos.id', '=', 'inventario.asiento_id')
            ->whereIn('inventario.reserva_id', $reservaIds)
            ->orderBy('localidades.id')->orderBy('asientos.fila')->orderBy('asientos.numero')
            ->get(['inventario.reserva_id', 'localidades.nombre as localidad', 'localidades.tipo', 'asientos.fila', 'asientos.numero'])
            ->groupBy('reserva_id')
            ->map(fn ($grupo) => $grupo->map(fn ($l) => [
                'localidad' => $l->localidad,
                'tipo'      => $l->tipo,
                'fila'      => $l->fila,
                'numero'    => $l->numero === null ? null : (int) $l->numero,
            ])->values()->all())
            ->all();
    }

    private function aResumen(object $reserva, array $lugares): array
    {
        return [
            'id'         => (int) $reserva->id,
            'usuario_id' => (int) $reserva->usuario_id,
            'estado'     => $reserva->estado,
            'total'      => (float) $reserva->total,
            'evento'     => [
                'id'           => (int) $reserva->evento_id,
                'nombre'       => $reserva->evento_nombre,
                // ISO con zona: el navegador la muestra en hora de Guatemala
                'fecha_evento' => \Illuminate\Support\Carbon::parse($reserva->fecha_evento)->toIso8601String(),
                'recinto'      => $reserva->recinto,
            ],
            'lugares'    => $lugares,
        ];
    }

    private function aEntidad(ReservaModel $m): Reserva
    {
        return new Reserva(
            id: $m->id,
            usuarioId: $m->usuario_id,
            eventoId: $m->evento_id,
            estado: $m->estado,
            expiraEn: $m->expira_en->toDateTimeImmutable(),
            total: (float) $m->total,
        );
    }
}
