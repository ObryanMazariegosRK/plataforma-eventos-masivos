<?php

namespace App\Application\UseCases\Admin;

use App\Application\Abstractions\Admin\ISubirImagenEventoUseCase;
use App\Domain\Abstractions\Catalogo\IAlmacenMultimedia;
use SplFileInfo;

// Las fotos de un evento se suben apenas se eligen en el formulario (así el borrador las
// recuerda); las que nunca se usan las borra la tarea diaria eventos:limpiar-imagenes.
class SubirImagenEventoUseCase implements ISubirImagenEventoUseCase
{
    public const CARPETA = 'eventos';

    public function __construct(private IAlmacenMultimedia $almacen) {}

    public function execute(SplFileInfo $archivo): string
    {
        return $this->almacen->url($this->almacen->guardar($archivo, self::CARPETA, 1920));
    }
}
