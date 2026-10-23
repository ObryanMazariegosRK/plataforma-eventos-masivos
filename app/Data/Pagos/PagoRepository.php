<?php
namespace App\Data\Pagos;

use App\Domain\Abstractions\Pagos\IPagoRepository;
use App\Domain\Entities\Pagos\Pago;
use App\Domain\Enums\Pagos\EstadoPago;
use App\Models\Pagos\Pago as PagoModel;   // alias, para no chocar con la Entidad

class PagoRepository implements IPagoRepository
{
    public function guardar(Pago $pago): Pago
    {
        $m = $pago->getId() ? PagoModel::findOrFail($pago->getId()) : new PagoModel();
        $m->reserva_id         = $pago->getReservaId();
        $m->monto              = $pago->getMonto();
        $m->estado             = $pago->getEstado();
        $m->intento            = $pago->getIntento();
        $m->pasarela_id        = $pago->getPasarelaId();
        $m->orden_externa      = $pago->getOrdenExterna();
        $m->referencia_externa = $pago->getReferenciaExterna();
        $m->save();

        return $this->aEntidad($m);
    }

    public function buscarPorId(int $id): ?Pago
    {
        $m = PagoModel::find($id);

        return $m ? $this->aEntidad($m) : null;
    }

    public function buscarPorIdParaActualizar(int $id): ?Pago
    {
        $m = PagoModel::whereKey($id)->lockForUpdate()->first();

        return $m ? $this->aEntidad($m) : null;
    }

    public function contarPorReserva(int $reservaId): int
    {
        return PagoModel::withTrashed()->where('reserva_id', $reservaId)->count();
    }

    public function existeAprobadoParaReserva(int $reservaId): bool
    {
        return PagoModel::where('reserva_id', $reservaId)->where('estado', EstadoPago::APROBADO)->exists();
    }

    private function aEntidad(PagoModel $m): Pago
    {
        return new Pago(
            id: $m->id,
            reservaId: $m->reserva_id,
            monto: (float) $m->monto,
            estado: $m->estado,
            intento: $m->intento,
            pasarelaId: $m->pasarela_id,
            referenciaExterna: $m->referencia_externa,
            ordenExterna: $m->orden_externa,
        );
    }
}
