<?php
namespace App\Data\Reservas;

use App\Domain\Abstractions\Reservas\IReservaRepository;
use App\Domain\Entities\Reservas\Reserva;
use App\Models\Reservas\Reserva as ReservaModel;   // alias, para no chocar con la Entidad

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

    public function buscarPorId(int $id): ?Reserva
    {
        $m = ReservaModel::find($id);

        return $m ? $this->aEntidad($m) : null;
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
