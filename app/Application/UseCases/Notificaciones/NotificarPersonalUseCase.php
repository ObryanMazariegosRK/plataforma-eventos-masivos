<?php

namespace App\Application\UseCases\Notificaciones;

use App\Domain\Abstractions\Notificaciones\INotificacionRepository;
use App\Domain\Abstractions\Notificaciones\IPersonalRepository;
use App\Domain\Abstractions\Reembolsos\IReembolsoRepository;
use App\Domain\Entities\Notificaciones\Notificacion;
use App\Domain\Enums\Notificaciones\CanalNotificacion;
use App\Domain\Enums\Notificaciones\EstadoNotificacion;
use App\Jobs\Notificaciones\EnviarNotificacionCorreoJob;

/**
 * Avisos para el personal de Soirée (push en los dispositivos donde lo activó + correo):
 * solo lo que pide atención o conviene saber. Cada aviso se guarda una vez por persona.
 *   - reembolso_solicitado  (administradores) → Reembolsos
 *   - devolucion_pendiente  (administradores) → Reembolsos
 *   - evento_casi_lleno / evento_agotado (administradores y operadores) → Catálogo de eventos
 */
class NotificarPersonalUseCase
{
    private const ADMIN = ['admin'];

    private const PERSONAL = ['admin', 'organizador'];

    public function __construct(
        private readonly INotificacionRepository $notificaciones,
        private readonly IPersonalRepository $personal,
        private readonly IReembolsoRepository $reembolsos,
    ) {}

    public function reembolsoSolicitado(int $reembolsoId): int
    {
        $reembolso = $this->reembolsos->buscarPorId($reembolsoId);
        $compra = $reembolso ? $this->reembolsos->contextoDePago($reembolso->getPagoId()) : null;
        if ($compra === null) {
            return 0;
        }

        $porcentaje = $compra['monto_pagado'] > 0 ? (int) round($reembolso->getMonto() / $compra['monto_pagado'] * 100) : 0;
        $mensaje = "{$this->personal->nombreDeUsuario($compra['usuario_id'])} pidió el reembolso de "
            .$this->q($reembolso->getMonto())." ({$porcentaje} %) de su compra para «{$compra['evento']}»."
            .($reembolso->getMotivo() ? "\n\nMotivo: {$reembolso->getMotivo()}" : '');

        return $this->avisar(self::ADMIN, 'reembolso_solicitado', 'Nueva solicitud de reembolso', $mensaje,
            '/admin?seccion=reembolsos', "reembolso_solicitado:{$reembolsoId}");
    }

    public function devolucionPendiente(int $reembolsoId, string $motivo): int
    {
        $reembolso = $this->reembolsos->buscarPorId($reembolsoId);
        $compra = $reembolso ? $this->reembolsos->contextoDePago($reembolso->getPagoId()) : null;
        if ($compra === null) {
            return 0;
        }

        return $this->avisar(self::ADMIN, 'devolucion_pendiente', 'Devolución pendiente',
            'La pasarela no pudo devolver '.$this->q($reembolso->getMonto())." del reembolso #{$reembolsoId} "
            ."(«{$compra['evento']}»). Reintenta la devolución desde Reembolsos.\n\nDetalle: {$motivo}",
            '/admin?seccion=reembolsos', "devolucion_pendiente:{$reembolsoId}");
    }

    /** Después de cada venta: ¿el evento se llenó o está por llenarse? */
    public function ocupacionTrasVenta(int $pagoId, float $umbralCasiLleno): int
    {
        $ocupacion = $this->personal->ocupacionPorPago($pagoId);
        if ($ocupacion === null || $ocupacion['lugares'] === 0) {
            return 0;
        }

        ['evento_id' => $id, 'evento' => $evento, 'vendidos' => $vendidos, 'lugares' => $lugares] = $ocupacion;

        if ($vendidos >= $lugares) {
            return $this->avisar(self::PERSONAL, 'evento_agotado', "«{$evento}» se agotó",
                "Se vendieron los {$lugares} lugares de «{$evento}».", '/admin?seccion=catalogo', "evento_agotado:{$id}");
        }
        if ($vendidos / $lugares >= $umbralCasiLleno) {
            $quedan = $lugares - $vendidos;

            return $this->avisar(self::PERSONAL, 'evento_casi_lleno', "«{$evento}» está casi lleno",
                "Se vendieron {$vendidos} de {$lugares} lugares: ".($quedan === 1 ? 'queda 1' : "quedan {$quedan}").'.',
                '/admin?seccion=catalogo', "evento_casi_lleno:{$id}");
        }

        return 0;
    }

    private function avisar(array $roles, string $tipo, string $asunto, string $mensaje, string $url, string $clave): int
    {
        $nuevos = 0;
        foreach ($this->personal->idsConRol($roles) as $usuarioId) {
            $guardada = $this->notificaciones->guardarUnica(
                notificacion: new Notificacion(
                    id: null,
                    usuarioId: $usuarioId,
                    canal: CanalNotificacion::EMAIL,
                    asunto: $asunto,
                    mensaje: $mensaje,
                    estado: EstadoNotificacion::PENDIENTE,
                    tipo: $tipo,
                    url: $url,
                ),
                claveIdempotencia: "personal:{$clave}:usuario:{$usuarioId}",
            );

            if ($guardada->getEstado() === EstadoNotificacion::PENDIENTE) {
                EnviarNotificacionCorreoJob::dispatch($guardada->getId())->afterCommit();
                $nuevos++;
            }
        }

        return $nuevos;
    }

    private function q(float $monto): string
    {
        return 'Q'.number_format($monto, 2);
    }
}
