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
        $reserva = DB::table('reservas')
            ->join('eventos', 'eventos.id', '=', 'reservas.evento_id')
            ->join('recintos', 'recintos.id', '=', 'eventos.recinto_id')
            ->where('reservas.id', $id)
            ->select(
                'reservas.id', 'reservas.usuario_id', 'reservas.estado', 'reservas.total',
                'eventos.id as evento_id', 'eventos.nombre as evento_nombre', 'eventos.fecha_evento',
                'recintos.nombre as recinto',
            )
            ->first();

        if ($reserva === null) {
            return null;
        }

        // Numeradas: fila y número del asiento; generales: sin asiento (un cupo por fila)
        $lugares = DB::table('inventario')
            ->join('localidades', 'localidades.id', '=', 'inventario.localidad_id')
            ->leftJoin('asientos', 'asientos.id', '=', 'inventario.asiento_id')
            ->where('inventario.reserva_id', $id)
            ->orderBy('localidades.id')->orderBy('asientos.fila')->orderBy('asientos.numero')
            ->get(['localidades.nombre as localidad', 'localidades.tipo', 'asientos.fila', 'asientos.numero'])
            ->map(fn ($l) => [
                'localidad' => $l->localidad,
                'tipo'      => $l->tipo,
                'fila'      => $l->fila,
                'numero'    => $l->numero === null ? null : (int) $l->numero,
            ])
            ->all();

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
