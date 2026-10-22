<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_la_raiz_lleva_a_la_cartelera(): void
    {
        $this->get('/')->assertRedirect('/catalogo');
    }
}
