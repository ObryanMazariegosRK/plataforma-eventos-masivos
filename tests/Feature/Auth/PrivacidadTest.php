<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PrivacidadTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_politica_de_privacidad_se_enlaza_desde_el_pie_y_el_registro(): void
    {
        $this->get('/privacidad')->assertOk()
            ->assertSee('Política de <em>privacidad</em>', false)
            ->assertSee('No pedimos');

        $this->get('/catalogo')->assertOk()->assertSee(route('legal.privacidad'), false);
        $this->get('/registro')->assertOk()->assertSee(route('legal.privacidad'), false);
    }

    public function test_el_registro_ya_no_guarda_el_telefono(): void
    {
        Mail::fake();

        $this->postJson('/api/auth/registro', [
            'nombre' => 'Ana', 'apellido' => 'López', 'email' => 'ana@example.com',
            'password' => 'Secreto#123', 'telefono' => '55551234',   // se ignora
        ])->assertCreated()->assertJsonMissingPath('usuario.telefono');

        $this->assertNull(DB::table('usuarios')->where('email', 'ana@example.com')->value('telefono'));
    }
}
