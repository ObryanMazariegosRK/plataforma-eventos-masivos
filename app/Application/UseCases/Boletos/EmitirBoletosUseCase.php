<?php

namespace App\Application\UseCases\Boletos;

use App\Application\Abstractions\Boletos\IEmitirBoletosUseCase;
use App\Domain\Abstractions\Boletos\IBoletoRepository;
use App\Domain\Entities\Boletos\Boleto;
use App\Domain\Enums\Boletos\EstadoBoleto;
use App\Domain\Enums\Inventario\EstadoAsiento;
use App\Domain\Enums\Pagos\EstadoPago;
use App\Domain\Enums\Reservas\EstadoReserva;
use DomainException;
use Illuminate\Support\Facades\DB;

class EmitirBoletosUseCase implements IEmitirBoletosUseCase
{
    public function __construct(
        private IBoletoRepository $boletos
    ) {}

    public function execute(int $pagoId): array
    {
        return DB::transaction(function () use ($pagoId): array {

            // 1. Buscar y bloquear el pago.
            $pago = DB::table('pagos')
                ->where('id', $pagoId)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            if (!$pago || $pago->estado !== EstadoPago::APROBADO->value) {
                throw new DomainException(
                    'El pago no existe o no está aprobado.'
                );
            }

            // 2. Verificar la reserva relacionada.
            $reserva = DB::table('reservas')
                ->where('id', $pago->reserva_id)
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            if (!$reserva || $reserva->estado !== EstadoReserva::CONFIRMADA->value) {
                throw new DomainException(
                    'La reserva no está confirmada.'
                );
            }

            // 3. Verificar el monto del pago.
            if (
                (float) $reserva->total <= 0 ||
                round((float) $pago->monto, 2) < round((float) $reserva->total, 2)
            ) {
                throw new DomainException(
                    'El pago no cubre el total de la reserva.'
                );
            }

            // 4. Obtener el inventario de la reserva.
            $inventario = DB::table('inventario')
                ->where('reserva_id', $reserva->id)
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($inventario->isEmpty()) {
                throw new DomainException(
                    'La reserva no tiene inventario asignado.'
                );
            }

            // 5. Verificar que todo el inventario esté vendido.
            foreach ($inventario as $item) {
                if (
                    (int) $item->evento_id !== (int) $reserva->evento_id ||
                    $item->estado !== EstadoAsiento::VENDIDO->value
                ) {
                    throw new DomainException(
                        'El inventario no está listo para emitir boletos.'
                    );
                }
            }
    
            // 6. Comprobar si los boletos ya fueron emitidos.
            $existentes = [];

            foreach ($inventario as $item) {
                $existente = $this->boletos
                    ->buscarPorInventario((int) $item->id);

                if ($existente === null) {
                    continue;
                }

                // Comprobar que pertenece a la misma reserva
                // y al mismo usuario.
                if (
                    $existente->getReservaId() !== (int) $reserva->id ||
                    $existente->getUsuarioId() !== (int) $reserva->usuario_id
                ) {
                    throw new DomainException(
                        'El boleto existente no pertenece a esta reserva.'
                    );
                }

                // No devolver boletos eliminados lógicamente.
                $eliminado = DB::table('boletos')
                    ->where('id', $existente->getId())
                    ->whereNotNull('deleted_at')
                    ->exists();

                if ($eliminado) {
                    throw new DomainException(
                        'Existe un boleto eliminado para este inventario.'
                    );
                }

                $existentes[] = $existente;
            }

            // Si todos existen, devolver los mismos boletos.
            if (count($existentes) === $inventario->count()) {
                return $existentes;
            }

            // Si solo algunos existen, evitar una emisión parcial.
            if (count($existentes) > 0) {
                throw new DomainException(
                    'La reserva tiene una emisión de boletos incompleta.'
                );
            }

            $emitidos = [];

            // 6. Emitir un boleto por cada registro de inventario.
            foreach ($inventario as $item) {

                // No permitir duplicados.
                $existente = $this->boletos
                    ->buscarPorInventario((int) $item->id);

                if ($existente !== null) {
                    throw new DomainException(
                        'Ya existe un boleto para el inventario ' . $item->id
                    );
                }

                // Generar un identificador seguro y único.
                $codigoQr = bin2hex(random_bytes(32));

                // Crear la entidad de dominio.
                $boleto = new Boleto(
                    id: null,
                    reservaId: (int) $reserva->id,
                    inventarioId: (int) $item->id,
                    usuarioId: (int) $reserva->usuario_id,
                    codigoQr: $codigoQr,
                    estado: EstadoBoleto::EMITIDO,
                );

                // Guardar utilizando el repositorio.
                $emitidos[] = $this->boletos->guardar($boleto);
            }

            return $emitidos;

        }, 3);
    }
}
