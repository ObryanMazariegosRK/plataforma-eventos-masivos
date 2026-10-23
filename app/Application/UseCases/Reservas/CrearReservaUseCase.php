<?php
namespace App\Application\UseCases\Reservas;

use App\Application\Abstractions\Reservas\ICrearReservaUseCase;
use App\Application\DTOs\Reservas\CrearReservaDTO;
use App\Domain\Abstractions\Reservas\IReservaRepository;
use App\Domain\Entities\Reservas\Reserva;
use App\Domain\Enums\Reservas\EstadoReserva;
use App\Domain\Exceptions\Reservas\LimitePorPersonaException;
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
        $cupos = $dto->cupos;

        // Debe seleccionar al menos un asiento o un cupo, y los asientos sin repetir.
        if ((empty($ids) && empty($cupos)) || count($ids) !== count(array_unique($ids))) {
            throw ValidationException::withMessages([
                'inventario_ids' => 'Seleccione elementos de inventario válidos y sin repetir.',
            ]);
        }

        // Límite por persona y evento. Primero lo obvio (sin tocar la base)...
        $cantidadTotal = count($ids) + array_sum(array_column($cupos, 'cantidad'));
        if ($cantidadTotal > $dto->maxPorPersona) {
            throw new LimitePorPersonaException($dto->maxPorPersona, 0);
        }

        return DB::transaction(function () use ($dto, $ids, $cupos, $cantidadTotal): Reserva {

            // ...y dentro de la transacción, con la fila del usuario bloqueada, lo que ya tiene en
            // el evento. Sin el bloqueo, dos pestañas a la vez leerían "tiene 0" y ambas pasarían.
            $yaTiene = $this->reservas->lugaresDePersonaEnEvento(
                $dto->usuarioId, $dto->eventoId, new DateTimeImmutable(), bloquear: true
            );
            if ($yaTiene + $cantidadTotal > $dto->maxPorPersona) {
                throw new LimitePorPersonaException($dto->maxPorPersona, $yaTiene);
            }

            // 0. Localidades generales: el cliente pide una CANTIDAD y aquí se eligen los lugares.
            //    SKIP LOCKED salta las filas que otra transacción está tomando en este instante:
            //    con muchos compradores a la vez, cada uno se lleva lugares distintos sin esperar
            //    ni chocar (sin él, todos intentarían los mismos "primeros libres").
            foreach ($cupos as $cupo) {
                $libres = DB::table('inventario')
                    ->where('evento_id', $dto->eventoId)
                    ->where('localidad_id', $cupo['localidad_id'])
                    ->whereNull('asiento_id')   // solo localidades generales; las numeradas se eligen por asiento
                    ->where('estado', EstadoAsiento::DISPONIBLE->value)
                    ->whereNull('deleted_at')
                    ->orderBy('id')
                    ->limit($cupo['cantidad'])
                    ->lock('for update skip locked')
                    ->pluck('id')
                    ->all();

                if (count($libres) < $cupo['cantidad']) {
                    throw ValidationException::withMessages([
                        'cupos' => 'No hay suficientes lugares disponibles en la localidad seleccionada.',
                    ]);
                }

                $ids = array_merge($ids, $libres);
            }

            // Ordenar los identificadores reduce el riesgo de deadlocks.
            sort($ids, SORT_NUMERIC);

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

            // Un evento que ya empezó no vende más boletos (aunque siga "publicado")
            if ($evento->fecha_evento !== null
                && new DateTimeImmutable($evento->fecha_evento, new \DateTimeZone('UTC')) <= new DateTimeImmutable()) {
                throw ValidationException::withMessages([
                    'evento_id' => 'Este evento ya ocurrió: ya no se venden boletos.',
                ]);
            }
            
            // Verificar autorización de Cola Virtual.
            // Los eventos sin cola conservan su funcionamiento normal.
            if ((bool) $evento->cola_virtual_activa) {

                $turno = DB::table('cola_virtual')
                    ->where('evento_id', $dto->eventoId)
                    ->where('usuario_id', $dto->usuarioId)
                    ->where('estado', 'habilitado')
                    ->where('habilitado_hasta', '>', now())
                    ->whereNull('deleted_at')
                    ->lockForUpdate()
                    ->first();

                if (!$turno) {
                    throw ValidationException::withMessages([
                        'cola_virtual' => 'Debes esperar a que tu turno de Cola Virtual esté habilitado y vigente para reservar.',
                    ]);
                }
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
            
            // Consumir el turno de Cola Virtual después de crear
            // correctamente la reserva y bloquear el inventario.
            if ((bool) $evento->cola_virtual_activa) {

                $turnosActualizados = DB::table('cola_virtual')
                    ->where('id', $turno->id)
                    ->where('evento_id', $dto->eventoId)
                    ->where('usuario_id', $dto->usuarioId)
                    ->where('estado', 'habilitado')
                    ->where('habilitado_hasta', '>', now())
                    ->update([
                        'estado' => 'atendido',
                        'updated_at' => now(),
                    ]);

                if ($turnosActualizados !== 1) {
                    throw ValidationException::withMessages([
                        'cola_virtual' => 'El turno ya no está disponible para reservar.',
                    ]);
                }
            }

            return $reserva;

        }, 3);
    }

}
