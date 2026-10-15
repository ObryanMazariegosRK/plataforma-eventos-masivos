<?php

namespace App\Application\UseCases\Boletos;

use App\Domain\Abstractions\Boletos\IBoletoRepository;
use App\Domain\Entities\Boletos\Boleto;
use App\Domain\Enums\Inventario\EstadoAsiento;
use App\Domain\Enums\Reservas\EstadoReserva;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

class ValidarBoletoUseCase
{
    public function __construct(
        private readonly IBoletoRepository $boletos
    ) {}

    public function execute(string $codigoQr, int $eventoId): Boleto
    {
        $codigoQr = trim($codigoQr);

        if ($codigoQr === '' || $eventoId <= 0) {
            throw new DomainException(
                'Los datos de validación son incorrectos.'
            );
        }

        return DB::transaction(function () use ($codigoQr, $eventoId) {

            // 1. Buscar el boleto y bloquear su registro.
            $boleto = $this->boletos
                ->buscarPorCodigoQrBloqueado($codigoQr);

            if ($boleto === null) {
                throw new DomainException(
                    'El boleto no existe o no está disponible.'
                );
            }

            // 2. Comprobar que el boleto corresponde al evento.
            $reserva = DB::table('reservas')
                ->where('id', $boleto->getReservaId())
                ->whereNull('deleted_at')
                ->first();

            $inventario = DB::table('inventario')
                ->where('id', $boleto->getInventarioId())
                ->whereNull('deleted_at')
                ->first();

            if (
                $reserva === null ||
                $inventario === null ||
                (int) $reserva->evento_id !== $eventoId ||
                (int) $inventario->evento_id !== $eventoId ||
                (int) $inventario->reserva_id !== $boleto->getReservaId()
            ) {
                throw new DomainException(
                    'El boleto no corresponde a este evento.'
                );
            }

            // 3. Verificar que la compra continúa siendo válida.
            if (
                $reserva->estado !== EstadoReserva::CONFIRMADA->value ||
                $inventario->estado !== EstadoAsiento::VENDIDO->value
            ) {
                throw new DomainException(
                    'La reserva o el asiento no están confirmados.'
                );
            }

            // 4. Marcar como usado; rechaza boletos usados o anulados.
            $boleto->usar(new DateTimeImmutable());

            // 5. Guardar el nuevo estado y la fecha de uso.
            return $this->boletos->guardar($boleto);

        }, 3);
    }
}
