<?php

namespace App\Application\UseCases\Bitacora;

use App\Application\Abstractions\Bitacora\IBuscarEnBitacoraUseCase;
use App\Application\DTOs\Bitacora\BuscarEnBitacoraDTO;
use App\Domain\Abstractions\Bitacora\IBitacoraRepository;
use DateTimeImmutable;
use DateTimeZone;

class BuscarEnBitacoraUseCase implements IBuscarEnBitacoraUseCase
{
    public function __construct(private IBitacoraRepository $bitacora) {}

    public function execute(BuscarEnBitacoraDTO $dto): array
    {
        $filtros = array_filter([
            'categoria' => $dto->categoria,
            'resultado' => $dto->resultado,
            'rol' => $dto->rol,
            'usuario' => $dto->usuario !== null ? trim($dto->usuario) : null,
            // El día elegido en Guatemala va de sus 00:00 a las 00:00 del día siguiente, en UTC
            'desde' => $dto->desde ? $this->inicioDelDiaEnUtc($dto->desde, $dto->zonaHoraria) : null,
            'hasta' => $dto->hasta ? $this->inicioDelDiaEnUtc($dto->hasta, $dto->zonaHoraria, '+1 day') : null,
        ], fn ($valor) => $valor !== null && $valor !== '');

        return [
            'registros' => $this->bitacora->buscar($filtros, $dto->porPagina, ($dto->pagina - 1) * $dto->porPagina),
            'total' => $this->bitacora->contar($filtros),
        ];
    }

    private function inicioDelDiaEnUtc(string $dia, string $zona, string $desplazamiento = '+0 day'): string
    {
        return (new DateTimeImmutable($dia, new DateTimeZone($zona)))
            ->modify($desplazamiento)
            ->setTime(0, 0)
            ->setTimezone(new DateTimeZone('UTC'))
            ->format('Y-m-d H:i:s');
    }
}
