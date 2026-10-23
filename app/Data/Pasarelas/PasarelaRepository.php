<?php
namespace App\Data\Pasarelas;

use App\Domain\Abstractions\Pasarelas\IPasarelaRepository;
use App\Domain\Entities\Pasarelas\Pasarela;
use App\Models\Pasarelas\Pasarela as PasarelaModel;

class PasarelaRepository implements IPasarelaRepository
{
    public function buscarPorId(int $id): ?Pasarela
    {
        $m = PasarelaModel::find($id);

        return $m ? $this->aEntidad($m) : null;
    }

    public function buscarActivaPorCodigo(string $codigo): ?Pasarela
    {
        $m = PasarelaModel::where('codigo', $codigo)->where('activa', true)->first();

        return $m ? $this->aEntidad($m) : null;
    }

    public function listarActivas(): array
    {
        return PasarelaModel::where('activa', true)->orderBy('nombre')->get()
            ->map(fn (PasarelaModel $m) => $this->aEntidad($m))
            ->all();
    }

    private function aEntidad(PasarelaModel $m): Pasarela
    {
        return new Pasarela(
            id: $m->id,
            nombre: $m->nombre,
            codigo: $m->codigo,
            activa: $m->activa,
        );
    }
}
