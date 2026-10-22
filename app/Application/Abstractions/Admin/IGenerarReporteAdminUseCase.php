<?php

namespace App\Application\Abstractions\Admin;

use App\Application\DTOs\Admin\GenerarReporteAdminDTO;

interface IGenerarReporteAdminUseCase
{
    /**
     * Informe listo para dibujar (Excel o PDF):
     * titulo, periodo, generado, generado_por, indicadores [{etiqueta, valor, formato}],
     * desglose {titulo, filas [{etiqueta, cantidad, porcentaje}]},
     * columnas [{clave, titulo, formato, ancho}] y filas [[clave => valor]].
     * formato: texto | entero | moneda | porcentaje | fecha (valor DateTimeImmutable en la zona pedida).
     */
    public function execute(GenerarReporteAdminDTO $dto): array;
}
