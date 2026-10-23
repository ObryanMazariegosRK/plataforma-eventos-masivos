<?php

namespace Tests\Feature\Catalogo;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ListarEventosPaginadoTest extends TestCase
{
    use RefreshDatabase;

    /** @return list<int> ids de 7 eventos publicados, del más próximo al más lejano */
    private function crearEventos(): array
    {
        $recinto = DB::table('recintos')->insertGetId([
            'nombre' => 'Teatro', 'direccion' => 'Zona 1', 'ciudad' => 'Guatemala',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $ids = [];
        foreach (range(1, 7) as $n) {
            $ids[] = DB::table('eventos')->insertGetId([
                'recinto_id' => $recinto, 'nombre' => "Función {$n}", 'fecha_evento' => now()->addDays($n),
                'estado' => 'publicado', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        // uno en borrador: nunca aparece
        DB::table('eventos')->insert([
            'recinto_id' => $recinto, 'nombre' => 'Borrador', 'fecha_evento' => now()->addDays(2),
            'estado' => 'borrador', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $ids;
    }

    public function test_sin_pagina_devuelve_todos_como_antes(): void
    {
        $this->crearEventos();

        $this->getJson('/api/eventos')
            ->assertOk()
            ->assertJsonCount(7, 'data')
            ->assertJsonMissingPath('meta');
    }

    public function test_por_paginas_en_orden_sin_repetir_ni_saltar(): void
    {
        $ids = $this->crearEventos();

        $primera = $this->getJson('/api/eventos?pagina=1&por_pagina=3')->assertOk()
            ->assertJsonPath('meta', ['pagina' => 1, 'por_pagina' => 3, 'total' => 7, 'hay_mas' => true]);
        $segunda = $this->getJson('/api/eventos?pagina=2&por_pagina=3')->assertOk()
            ->assertJsonPath('meta.hay_mas', true);
        $tercera = $this->getJson('/api/eventos?pagina=3&por_pagina=3')->assertOk()
            ->assertJsonPath('meta.hay_mas', false);

        $vistos = array_merge(
            array_column($primera->json('data'), 'id'),
            array_column($segunda->json('data'), 'id'),
            array_column($tercera->json('data'), 'id'),
        );
        $this->assertSame($ids, $vistos);
    }

    public function test_la_pagina_usa_el_tamano_configurado_y_respeta_los_filtros(): void
    {
        $this->crearEventos();
        config(['catalogo.eventos_por_pagina' => 4]);

        $this->getJson('/api/eventos?pagina=1')->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('meta.por_pagina', 4);

        $this->getJson('/api/eventos?pagina=1&nombre=Función 7')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_rechaza_paginas_invalidas(): void
    {
        $this->getJson('/api/eventos?pagina=0')->assertUnprocessable();
        $this->getJson('/api/eventos?pagina=1&por_pagina=500')->assertUnprocessable();
    }
}
