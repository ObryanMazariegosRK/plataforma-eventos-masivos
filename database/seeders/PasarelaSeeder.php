<?php

namespace Database\Seeders;

use App\Models\Pasarelas\Pasarela;
use Illuminate\Database\Seeder;

class PasarelaSeeder extends Seeder
{
    /**
     * Pasarelas disponibles. updateOrCreate: se puede correr varias veces sin duplicar.
     */
    public function run(): void
    {
        Pasarela::updateOrCreate(
            ['codigo' => 'simulada'],
            ['nombre' => 'Pasarela simulada (pruebas)', 'activa' => true],
        );
    }
}
