<?php

namespace App\Application\UseCases\Finanzas;

use App\Application\Abstractions\Finanzas\IGuardarCostosEventoUseCase;
use App\Application\Abstractions\Finanzas\IListarCostosEventoUseCase;
use App\Application\DTOs\Finanzas\GuardarCostosEventoDTO;
use App\Domain\Abstractions\Finanzas\ICostoEventoRepository;
use App\Domain\Entities\Finanzas\CostoEvento;
use App\Domain\Enums\Finanzas\CategoriaCosto;
use App\Domain\Exceptions\Finanzas\EventoSinCostosEditablesException;
use DomainException;

class GuardarCostosEventoUseCase implements IGuardarCostosEventoUseCase
{
    public function __construct(
        private ICostoEventoRepository $costos,
        private IListarCostosEventoUseCase $listar,
    ) {}

    public function execute(GuardarCostosEventoDTO $dto): array
    {
        if (! $this->costos->existeEvento($dto->eventoId)) {
            throw new EventoSinCostosEditablesException($dto->eventoId);
        }

        if (count($dto->costos) > $dto->maximo) {
            throw new DomainException("Un evento puede tener hasta {$dto->maximo} costos.");
        }

        // La entidad valida cada línea (concepto y monto) antes de tocar la base
        $entidades = array_map(fn (array $costo) => new CostoEvento(
            id: null,
            eventoId: $dto->eventoId,
            categoria: CategoriaCosto::tryFrom((string) ($costo['categoria'] ?? '')) ?? CategoriaCosto::OTROS,
            concepto: (string) ($costo['concepto'] ?? ''),
            monto: round((float) ($costo['monto'] ?? 0), 2),
        ), array_values($dto->costos));

        $this->costos->reemplazar($dto->eventoId, $entidades, $dto->usuarioId);

        return $this->listar->execute($dto->eventoId);
    }
}
