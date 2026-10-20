<?php
namespace App\Data\Catalogo;

use App\Domain\Abstractions\Catalogo\IBannerRepository;
use App\Domain\Entities\Catalogo\Banner;
use App\Domain\Enums\Catalogo\TipoBanner;
use App\Models\Catalogo\Banner as BannerModel;
use Illuminate\Support\Facades\DB;

class BannerRepository implements IBannerRepository
{
    public function actual(): ?Banner
    {
        $modelo = BannerModel::query()->latest('id')->first();

        return $modelo ? $this->aEntidad($modelo) : null;
    }

    public function reemplazar(Banner $banner): Banner
    {
        // Solo existe un banner: se borra el anterior y se crea el nuevo en la misma transacción
        $modelo = DB::transaction(function () use ($banner) {
            BannerModel::query()->delete();

            return BannerModel::create([
                'tipo'       => $banner->getTipo()->value,
                'ruta'       => $banner->getRuta(),
                'created_by' => $banner->getActualizadoPor(),
                'updated_by' => $banner->getActualizadoPor(),
            ]);
        });

        return $this->aEntidad($modelo);
    }

    public function eliminar(): void
    {
        BannerModel::query()->delete();
    }

    private function aEntidad(BannerModel $modelo): Banner
    {
        return new Banner(
            $modelo->id,
            TipoBanner::from($modelo->tipo),
            $modelo->ruta,
            $modelo->updated_by,
        );
    }
}
