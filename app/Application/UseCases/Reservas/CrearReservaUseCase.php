<?php
namespace App\Application\UseCases\Reservas;

use App\Application\Abstractions\Reservas\ICrearReservaUseCase;
use App\Application\DTOs\Reservas\CrearReservaDTO;
use App\Domain\Abstractions\Reservas\IReservaRepository;
use App\Domain\Entities\Reservas\Reserva;
use App\Domain\Enums\Reservas\EstadoReserva;
use DateTimeImmutable;
use App\Domain\Enums\Inventario\EstadoAsiento;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class CrearReservaUseCase implements ICrearReservaUseCase
{
    public function __construct(
        private IReservaRepository $reservas,
    ) {}


    public function execute(CrearReservaDTO $dto): Reserva
    {
        $ids = $dto->inventarioIds;

        // Debe seleccionar al menos un elemento de inventario.
        if (empty($ids) || count($ids) !== count(array_unique($ids))) {
            throw ValidationException::withMessages([
                'inventario_ids' => 'Seleccione elementos de inventario válidos y sin repetir.',
            ]);
        }

        // Ordenar los identificadores reduce el riesgo de deadlocks.
        sort($ids, SORT_NUMERIC);

        return DB::transaction(function () use ($dto, $ids): Reserva {

            // 1. Verificar que el evento permita reservas.
            $evento = DB::table('eventos')
                ->where('id', $dto->eventoId)
                ->whereNull('deleted_at')
                ->first();

            if (!$evento || $evento->estado !== 'publicado') {
                throw ValidationException::withMessages([
                    'evento_id' => 'El evento no está disponible para reservas.',
                ]);
            }

            // 2. Bloquear los registros de inventario en MySQL.
            $items = DB::table('inventario')
                ->whereIn('id', $ids)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($items->count() !== count($ids)) {
                throw ValidationException::withMessages([
                    'inventario_ids' => 'Uno o más elementos no existen.',
                ]);
            }

            // 3. Comprobar evento, disponibilidad y eliminación lógica.
            foreach ($items as $item) {
                if (
                    (int) $item->evento_id !== $dto->eventoId ||
                    $item->deleted_at !== null ||
                    $item->estado !== EstadoAsiento::DISPONIBLE->value
                ) {
                    throw ValidationException::withMessages([
                        'inventario_ids' => 'Uno o más asientos no están disponibles.',
                    ]);
                }
            }

            // 4. Obtener los precios oficiales de las localidades.
            $localidadesIds = $items->pluck('localidad_id')->unique()->values();

            $precios = DB::table('precios')
                ->where('evento_id', $dto->eventoId)
                ->whereIn('localidad_id', $localidadesIds)
                ->whereNull('deleted_at')
                ->pluck('precio', 'localidad_id');

            // 5. Calcular el total en centavos para evitar
            // acumulación de errores de decimales.
            $totalCentavos = 0;

            foreach ($items as $item) {
                if (!$precios->has($item->localidad_id)) {
                    throw ValidationException::withMessages([
                        'inventario_ids' => 'No existe un precio configurado para una localidad.',
                    ]);
                }

                $precioCentavos = (int) round(
                    (float) $precios->get($item->localidad_id) * 100
                );

                if ($precioCentavos <= 0) {
                    throw ValidationException::withMessages([
                        'inventario_ids' => 'El precio de una localidad no es válido.',
                    ]);
                }

                $totalCentavos += $precioCentavos;
            }

            // 6. Establecer el vencimiento.
            if ($dto->ttlSegundos <= 0) {
                throw ValidationException::withMessages([
                    'ttl' => 'El tiempo de reserva no es válido.',
                ]);
            }

            $expiraEn = (new DateTimeImmutable())
                ->modify("+{$dto->ttlSegundos} seconds");

            // 7. Crear la reserva dentro de la misma transacción.
            $reserva = $this->reservas->guardar(
                new Reserva(
                    id: null,
                    usuarioId: $dto->usuarioId,
                    eventoId: $dto->eventoId,
                    estado: EstadoReserva::ACTIVA,
                    expiraEn: $expiraEn,
                    total: $totalCentavos / 100
                )
            );

            // 8. Asociar y bloquear los elementos seleccionados.
            $actualizados = DB::table('inventario')
                ->whereIn('id', $ids)
                ->where('estado', EstadoAsiento::DISPONIBLE->value)
                ->whereNull('deleted_at')
                ->update([
                    'estado' => EstadoAsiento::BLOQUEADO->value,
                    'reserva_id' => $reserva->getId(),
                    'bloqueado_hasta' => $expiraEn->format('Y-m-d H:i:s'),
                    'updated_by' => $dto->usuarioId,
                    'updated_at' => now(),
                ]);

            if ($actualizados !== count($ids)) {
                throw ValidationException::withMessages([
                    'inventario_ids' => 'No se pudieron bloquear todos los asientos.',
                ]);
            }

            return $reserva;

        }, 3);
    }

}
