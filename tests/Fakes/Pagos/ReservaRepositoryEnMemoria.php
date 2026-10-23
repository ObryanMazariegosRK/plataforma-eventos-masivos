<?php

namespace Tests\Fakes\Pagos;

use App\Domain\Abstractions\Reservas\IReservaRepository;
use App\Domain\Entities\Reservas\Reserva;

class ReservaRepositoryEnMemoria extends RepositorioEnMemoria implements IReservaRepository
{
    public function guardar(Reserva $reserva): Reserva { return $this->insertarOActualizar($reserva); }
    public function buscarPorId(int $id): ?Reserva { return $this->obtener($id); }
    public function buscarPorIdParaActualizar(int $id): ?Reserva { return $this->obtener($id); }

    /** @var array<int, array> detalles preparados por la prueba */
    public array $detalles = [];
    public function detalle(int $id): ?array { return $this->detalles[$id] ?? null; }
    /** @var array<string, int> "usuario:evento" → lugares que ya tiene */
    public array $lugaresPorPersona = [];
    public function lugaresDePersonaEnEvento(int $usuarioId, int $eventoId, \DateTimeImmutable $ahora, bool $bloquear = false): int
    {
        return $this->lugaresPorPersona["{$usuarioId}:{$eventoId}"] ?? 0;
    }

    public function apartadasSinPagar(int $usuarioId, int $eventoId, \DateTimeImmutable $ahora): array
    {
        return [];
    }

    public function comprasDeUsuario(int $usuarioId): array
    {
        return array_values(array_filter($this->detalles, fn ($d) => $d['usuario_id'] === $usuarioId && $d['estado'] === 'confirmada'));
    }
}
