<?php

namespace App\Application\UseCases\Notificaciones;

use App\Domain\Abstractions\Notificaciones\INotificacionRepository;
use App\Domain\Abstractions\Reembolsos\IReembolsoRepository;
use App\Domain\Entities\Notificaciones\Notificacion;
use App\Domain\Enums\Notificaciones\CanalNotificacion;
use App\Domain\Enums\Notificaciones\EstadoNotificacion;
use App\Domain\Enums\Reembolsos\EstadoReembolso;
use App\Jobs\Notificaciones\EnviarNotificacionCorreoJob;
use DomainException;

/**
 * Avisa al cliente por correo cuando su reembolso se resuelve: dinero devuelto (procesado)
 * o solicitud rechazada. Una sola notificación por reembolso y estado.
 */
class NotificarResolucionReembolsoUseCase
{
    public function __construct(
        private readonly INotificacionRepository $notificaciones,
        private readonly IReembolsoRepository $reembolsos,
    ) {}

    public function execute(int $reembolsoId): ?Notificacion
    {
        $reembolso = $this->reembolsos->buscarPorId($reembolsoId)
            ?? throw new DomainException('La solicitud de reembolso no existe.');

        $estado = $reembolso->getEstado();
        if (! in_array($estado, [EstadoReembolso::PROCESADO, EstadoReembolso::RECHAZADO], true)) {
            return null;   // aún no hay nada que contar
        }

        $compra = $this->reembolsos->contextoDePago($reembolso->getPagoId())
            ?? throw new DomainException('El pago del reembolso no está disponible.');

        $monto = 'Q'.number_format($reembolso->getMonto(), 2);

        [$asunto, $mensaje] = $estado === EstadoReembolso::PROCESADO
            ? [
                'Reembolso realizado',
                "Te devolvimos {$monto} de tu compra para «{$compra['evento']}».\n\n"
                ."Referencia de la devolución: {$reembolso->getReferenciaExterna()}.\n"
                .'El dinero puede tardar unos días en verse reflejado, según tu banco. '
                .'Los boletos de esta compra quedaron anulados.',
            ]
            : [
                'Solicitud de reembolso rechazada',
                "Revisamos tu solicitud de reembolso para «{$compra['evento']}» y no pudimos aprobarla.\n\n"
                .'Tus boletos siguen siendo válidos para la función. '
                .'Si tienes dudas, responde a este correo.',
            ];

        $notificacion = $this->notificaciones->guardarUnica(
            notificacion: new Notificacion(
                id: null,
                usuarioId: $compra['usuario_id'],
                canal: CanalNotificacion::EMAIL,
                asunto: $asunto,
                mensaje: $mensaje,
                estado: EstadoNotificacion::PENDIENTE,
            ),
            claveIdempotencia: "reembolso:{$reembolsoId}:{$estado->value}",
        );

        if ($notificacion->getEstado() === EstadoNotificacion::PENDIENTE) {
            EnviarNotificacionCorreoJob::dispatch($notificacion->getId())->afterCommit();
        }

        return $notificacion;
    }
}
