<?php

namespace App\Data\Notificaciones;

use App\Domain\Abstractions\Notificaciones\INotificacionRepository;
use App\Domain\Entities\Notificaciones\Notificacion;
use App\Domain\Enums\Notificaciones\CanalNotificacion;
use App\Domain\Enums\Notificaciones\EstadoNotificacion;
use DateTimeImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;

use Illuminate\Database\QueryException;

class NotificacionRepository implements INotificacionRepository
{
    public function guardar(
        Notificacion $notificacion,
        ?int $registradoPor = null
    ): Notificacion {

        $datos = [
            'usuario_id' => $notificacion->getUsuarioId(),
            'canal' => $notificacion->getCanal()->value,
            'asunto' => $notificacion->getAsunto(),
            'mensaje' => $notificacion->getMensaje(),
            'estado' => $notificacion->getEstado()->value,
            'enviada_en' => $notificacion->getEnviadaEn()
                ?->format('Y-m-d H:i:s'),
            'updated_by' => $registradoPor,
            'updated_at' => now(),
        ];

        if ($notificacion->getId() === null) {

            // Guardar una notificacion nueva.
            $id = DB::table('notificaciones')->insertGetId([
                ...$datos,
                'created_by' => $registradoPor,
                'created_at' => now(),
            ]);

        } else {

            // Actualizar una notificacion existente.
            $id = $notificacion->getId();

            $actualizados = DB::table('notificaciones')
                ->where('id', $id)
                ->whereNull('deleted_at')
                ->update($datos);

            if ($actualizados === 0) {
                throw new DomainException(
                    'La notificación no existe o no está disponible.'
                );
            }
        }

        return $this->buscarPorId($id)
            ?? throw new DomainException(
                'No se pudo recuperar la notificación.'
            );
    }

    public function buscarPorId(int $id): ?Notificacion
    {
        $registro = DB::table('notificaciones')
            ->where('id', $id)
            ->whereNull('deleted_at')
            ->first();

        if ($registro === null) {
            return null;
        }

        return new Notificacion(
            id: (int) $registro->id,
            usuarioId: (int) $registro->usuario_id,
            canal: CanalNotificacion::from($registro->canal),
            asunto: $registro->asunto,
            mensaje: $registro->mensaje,
            estado: EstadoNotificacion::from($registro->estado),
            enviadaEn: $registro->enviada_en
                ? new DateTimeImmutable($registro->enviada_en)
                : null
        );
    }

    public function guardarUnica(
        Notificacion $notificacion,
        string $claveIdempotencia,
        ?int $registradoPor = null
    ): Notificacion {

        $claveIdempotencia = trim($claveIdempotencia);

        if (
            $claveIdempotencia === '' ||
            strlen($claveIdempotencia) > 191
        ) {
            throw new DomainException(
                'La clave de idempotencia no es válida.'
            );
        }

        if ($notificacion->getId() !== null) {
            throw new DomainException(
                'Solo se pueden registrar notificaciones nuevas.'
            );
        }

        $datos = [
            'usuario_id' => $notificacion->getUsuarioId(),
            'canal' => $notificacion->getCanal()->value,
            'asunto' => $notificacion->getAsunto(),
            'mensaje' => $notificacion->getMensaje(),
            'estado' => $notificacion->getEstado()->value,
        ];

        try {

            // Crear una notificacion con clave unica.
            $id = DB::table('notificaciones')->insertGetId([
                ...$datos,
                'clave_idempotencia' => $claveIdempotencia,
                'enviada_en' => $notificacion->getEnviadaEn()
                    ?->format('Y-m-d H:i:s'),
                'created_by' => $registradoPor,
                'updated_by' => $registradoPor,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $this->buscarPorId($id)
                ?? throw new DomainException(
                    'No se pudo recuperar la notificación.'
                );

        } catch (QueryException $e) {

            // Solo recuperar errores de clave duplicada.
            $codigo = (int) ($e->errorInfo[1] ?? 0);

            if (!in_array($codigo, [19, 1062], true)) {
                throw $e;
            }

            $existente = DB::table('notificaciones')
                ->where('clave_idempotencia', $claveIdempotencia)
                ->first();

            if ($existente === null) {
                throw $e;
            }

            // No permitir que una clave se reutilice
            // para otra notificacion diferente.
            foreach ([
                'usuario_id',
                'canal',
                'asunto',
                'mensaje'
            ] as $campo) {

                if ($existente->{$campo} != $datos[$campo]) {
                    throw new DomainException(
                        'La clave pertenece a otra notificación.'
                    );
                }
            }


            if ($existente->deleted_at !== null) {
                throw new DomainException(
                    'La notificación original no está disponible.'
                );
            }

            return $this->buscarPorId((int) $existente->id)
                ?? throw new DomainException(
                    'No se pudo recuperar la notificación.'
                );
        }
    }

}
