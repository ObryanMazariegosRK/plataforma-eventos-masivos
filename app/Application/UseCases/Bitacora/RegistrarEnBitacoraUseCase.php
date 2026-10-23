<?php

namespace App\Application\UseCases\Bitacora;

use App\Application\Abstractions\Bitacora\IRegistrarEnBitacoraUseCase;
use App\Application\DTOs\Bitacora\RegistrarEnBitacoraDTO;
use App\Domain\Abstractions\Bitacora\IBitacoraRepository;
use App\Domain\Entities\Bitacora\RegistroBitacora;
use App\Domain\Enums\Bitacora\ResultadoBitacora;
use DateTimeImmutable;
use DateTimeZone;

class RegistrarEnBitacoraUseCase implements IRegistrarEnBitacoraUseCase
{
    public function __construct(private IBitacoraRepository $bitacora) {}

    public function execute(RegistrarEnBitacoraDTO $dto): void
    {
        $this->bitacora->registrar(new RegistroBitacora(
            ocurridoEn: new DateTimeImmutable('now', new DateTimeZone('UTC')),
            categoria: $dto->categoria,
            accion: $dto->accion,
            resultado: $dto->exito ? ResultadoBitacora::EXITO : ResultadoBitacora::FALLIDO,
            descripcion: $this->recortar($dto->descripcion, 500),
            usuarioId: $dto->usuarioId,
            usuarioNombre: $this->recortar($dto->usuarioNombre, 150),
            usuarioCorreo: $this->recortar($dto->usuarioCorreo, 150),
            usuarioRol: $dto->usuarioRol,
            entidad: $dto->entidad,
            entidadId: $dto->entidadId,
            datosAntes: $dto->datosAntes ?: null,
            datosDespues: $dto->datosDespues ?: null,
            ip: $dto->ip,
            userAgent: $this->recortar($dto->userAgent, 300),
        ));
    }

    private function recortar(?string $texto, int $maximo): ?string
    {
        return $texto === null ? null : mb_substr($texto, 0, $maximo);
    }
}
