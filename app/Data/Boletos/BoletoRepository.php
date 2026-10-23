<?php

namespace App\Data\Boletos;

use App\Domain\Enums\Boletos\EstadoBoleto;
use App\Domain\Abstractions\Boletos\IBoletoRepository;
use App\Domain\Entities\Boletos\Boleto;
use App\Models\Boletos\Boleto as BoletoModel;

class BoletoRepository implements IBoletoRepository
{
    public function guardar(Boleto $boleto): Boleto
    {
        // Buscar un boleto existente o crear uno nuevo.
        $modelo = $boleto->getId() !== null
            ? BoletoModel::findOrFail($boleto->getId())
            : new BoletoModel();

        // Asignar los datos de la entidad al modelo.
        $modelo->reserva_id = $boleto->getReservaId();
        $modelo->inventario_id = $boleto->getInventarioId();
        $modelo->usuario_id = $boleto->getUsuarioId();
        $modelo->codigo_qr = $boleto->getCodigoQr();
        $modelo->estado = $boleto->getEstado();
        $modelo->usado_en = $boleto->getUsadoEn();

        // Guardar en MySQL.
        $modelo->save();

        return $this->aEntidad($modelo);
    }

    public function buscarPorInventario(int $inventarioId): ?Boleto
    {
        // Incluir eliminados lógicamente porque
        // la restricción UNIQUE sigue vigente.
        // Un boleto anulado por reembolso ya no ocupa el lugar:
        // ese lugar se pudo volver a vender en otra compra.
        $modelo = BoletoModel::withTrashed()
            ->where('inventario_id', $inventarioId)
            ->where('estado', '!=', EstadoBoleto::ANULADO->value)
            ->first();

        return $modelo ? $this->aEntidad($modelo) : null;
    }
    
    public function buscarPorId(int $boletoId): ?Boleto
    {
        // Buscar un boleto activo mediante su ID.
        $modelo = BoletoModel::find($boletoId);

        // Si no existe, devolver null.
        if ($modelo === null) {
            return null;
        }

        // Convertir el modelo en entidad de dominio.
        return $this->aEntidad($modelo);
    }

    public function buscarPorCodigoQrBloqueado(string $codigoQr): ?Boleto
    {
        $modelo = BoletoModel::query()
            ->where('codigo_qr', $codigoQr)
            ->lockForUpdate()
            ->first();

        return $modelo ? $this->aEntidad($modelo) : null;
    }

    private function aEntidad(BoletoModel $modelo): Boleto
    {
        return new Boleto(
            id: $modelo->id,
            reservaId: $modelo->reserva_id,
            inventarioId: $modelo->inventario_id,
            usuarioId: $modelo->usuario_id,
            codigoQr: $modelo->codigo_qr,
            estado: $modelo->estado,
            usadoEn: $modelo->usado_en?->toDateTimeImmutable(),
        );
    }
}
