<?php
namespace App\Application\UseCases\Catalogo;

use App\Application\Abstractions\Catalogo\IActualizarBannerCatalogoUseCase;
use App\Application\DTOs\Catalogo\ActualizarBannerDTO;
use App\Domain\Abstractions\Catalogo\IAlmacenMultimedia;
use App\Domain\Abstractions\Catalogo\IBannerRepository;
use App\Domain\Entities\Catalogo\Banner;
use App\Domain\Enums\Catalogo\TipoBanner;
use App\Domain\Exceptions\Catalogo\ArchivoBannerInvalidoException;

class ActualizarBannerCatalogoUseCase implements IActualizarBannerCatalogoUseCase
{
    private const CARPETA = 'banners';
    private const LADO_MAXIMO = 1920;   // px: suficiente para el fondo a pantalla completa

    public function __construct(
        private IBannerRepository $banners,
        private IAlmacenMultimedia $almacen,
    ) {}

    public function execute(ActualizarBannerDTO $dto): array
    {
        $tipo = TipoBanner::desdeMime($dto->tipoMime)
            ?? throw new ArchivoBannerInvalidoException();

        $anterior = $this->banners->actual();

        // Primero el archivo; si la base falla, se borra para no dejar archivos sueltos
        $ruta = $this->almacen->guardar($dto->archivo, self::CARPETA, self::LADO_MAXIMO);

        try {
            $banner = $this->banners->reemplazar(new Banner(null, $tipo, $ruta, $dto->usuarioId));
        } catch (\Throwable $e) {
            $this->almacen->eliminar($ruta);
            throw $e;
        }

        // Solo cuando el nuevo ya quedó guardado se borra el archivo anterior
        if ($anterior !== null && $anterior->getRuta() !== $ruta) {
            $this->almacen->eliminar($anterior->getRuta());
        }

        return [
            'tipo' => $banner->getTipo()->value,
            'url'  => $this->almacen->url($banner->getRuta()),
        ];
    }
}
