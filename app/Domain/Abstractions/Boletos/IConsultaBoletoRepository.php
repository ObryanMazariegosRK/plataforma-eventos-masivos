<?php

namespace App\Domain\Abstractions\Boletos;

interface IConsultaBoletoRepository
{
    /**
     * Lo que se ve al escanear un boleto: el boleto, su compra, su lugar y su función.
     *
     * @return array{boleto_id: int, estado: string, usado_en: ?string, usuario_id: int, titular: string,
     *               reserva_id: int, reserva_estado: string, lugar_vendido: bool,
     *               evento: array{id: int, nombre: string, fecha: string, estado: string, recinto: string},
     *               lugar: array{localidad: string, color: ?string, fila: ?string, numero: ?int}}|null
     */
    public function porCodigo(string $codigo): ?array;
}
