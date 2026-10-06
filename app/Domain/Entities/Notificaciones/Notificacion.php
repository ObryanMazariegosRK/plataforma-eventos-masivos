<?php
namespace App\Domain\Entities\Notificaciones;

use App\Domain\Enums\Notificaciones\CanalNotificacion;
use App\Domain\Enums\Notificaciones\EstadoNotificacion;
use DateTimeImmutable;
use DomainException;

class Notificacion
{
    public function __construct(
        private ?int $id,
        private int $usuarioId,
        private CanalNotificacion $canal,
        private string $asunto,
        private string $mensaje,
        private EstadoNotificacion $estado,
        private ?DateTimeImmutable $enviadaEn = null,
    ) {
        $this->validar();
    }

    private function validar(): void
    {
        if (trim($this->asunto) === '') {
            throw new DomainException('La notificación debe tener un asunto.');
        }
        if (trim($this->mensaje) === '') {
            throw new DomainException('La notificación debe tener un mensaje.');
        }
    }

    // Comportamiento
    public function marcarEnviada(DateTimeImmutable $momento): void
    {
        $this->estado = EstadoNotificacion::ENVIADA;
        $this->enviadaEn = $momento;
    }

    public function marcarFallida(): void
    {
        $this->estado = EstadoNotificacion::FALLIDA;
    }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getUsuarioId(): int { return $this->usuarioId; }
    public function getCanal(): CanalNotificacion { return $this->canal; }
    public function getAsunto(): string { return $this->asunto; }
    public function getMensaje(): string { return $this->mensaje; }
    public function getEstado(): EstadoNotificacion { return $this->estado; }
    public function getEnviadaEn(): ?DateTimeImmutable { return $this->enviadaEn; }
}