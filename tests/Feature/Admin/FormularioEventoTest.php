<?php

namespace Tests\Feature\Admin;

use App\Domain\Enums\Auth\EstadoUsuario;
use App\Domain\Enums\Auth\RolUsuario;
use App\Models\Auth\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// Formulario de evento: fotos subidas (obligatorias), costos obligatorios y plano del recinto
class FormularioEventoTest extends TestCase
{
    use RefreshDatabase;

    private function operador(): Usuario
    {
        return Usuario::create([
            'nombre' => 'Obryan', 'apellido' => 'López', 'email' => 'operador@test.com',
            'password' => bcrypt('Secreto#123'), 'rol' => RolUsuario::ORGANIZADOR, 'estado' => EstadoUsuario::ACTIVO,
            'email_verified_at' => now(),
        ]);
    }

    private function teatro(): array
    {
        $recinto = DB::table('recintos')->insertGetId(['nombre' => 'Teatro Soirée', 'direccion' => 'Z1', 'ciudad' => 'Guatemala', 'estado' => 'activo', 'created_at' => now(), 'updated_at' => now()]);
        $platea = DB::table('localidades')->insertGetId(['recinto_id' => $recinto, 'nombre' => 'Platea', 'tipo' => 'numerada', 'capacidad' => 2, 'created_at' => now(), 'updated_at' => now()]);
        $general = DB::table('localidades')->insertGetId(['recinto_id' => $recinto, 'nombre' => 'General', 'tipo' => 'general', 'capacidad' => 30, 'created_at' => now(), 'updated_at' => now()]);
        foreach ([1, 2] as $numero) {
            DB::table('asientos')->insert(['localidad_id' => $platea, 'fila' => 'A', 'numero' => $numero, 'created_at' => now(), 'updated_at' => now()]);
        }

        return [$recinto, $platea, $general];
    }

    private function evento(int $recinto, array $localidades, array $cambios = []): array
    {
        return array_merge([
            'nombre' => 'Gala de ópera', 'recinto_id' => $recinto, 'fecha_evento' => now()->addMonth()->format('Y-m-d H:i:s'),
            'imagen_url' => '/storage/eventos/portada.webp', 'imagen_url_2' => '/storage/eventos/dos.webp', 'imagen_url_3' => '/storage/eventos/tres.webp',
            'precios' => array_map(fn ($id) => ['localidad_id' => $id, 'precio' => 100], $localidades),
            'costos' => [
                ['categoria' => 'recinto', 'concepto' => 'Alquiler del recinto', 'monto' => 5000],
                ['categoria' => 'artistas', 'concepto' => 'Artistas', 'monto' => 8000],
            ],
        ], $cambios);
    }

    public function test_sube_una_foto_y_devuelve_su_url_en_webp(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->operador());

        $url = $this->post('/api/admin/eventos/imagenes', ['imagen' => UploadedFile::fake()->image('escenario.jpg', 2400, 1600)], ['Accept' => 'application/json'])
            ->assertCreated()->json('data.url');

        $this->assertMatchesRegularExpression('#^/storage/eventos/.+\.webp$#', $url);
        Storage::disk('public')->assertExists(substr($url, strlen('/storage/')));

        $this->post('/api/admin/eventos/imagenes', ['imagen' => UploadedFile::fake()->create('notas.pdf', 10)], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonValidationErrors('imagen');
    }

    public function test_las_tres_fotos_y_los_costos_de_recinto_y_artistas_son_obligatorios(): void
    {
        [$recinto, $platea, $general] = $this->teatro();
        Sanctum::actingAs($this->operador());

        $this->postJson('/api/admin/eventos', $this->evento($recinto, [$platea, $general], ['imagen_url_3' => null]))
            ->assertStatus(422)->assertJsonValidationErrors('imagen_url_3');
        $this->postJson('/api/admin/eventos', $this->evento($recinto, [$platea, $general], ['imagen_url' => 'http://inseguro.com/a.jpg']))
            ->assertStatus(422)->assertJsonValidationErrors('imagen_url');
        $this->postJson('/api/admin/eventos', $this->evento($recinto, [$platea, $general], ['costos' => []]))
            ->assertStatus(422)->assertJsonValidationErrors('costos');
        // Sin artistas: se rechaza y no queda nada a medias
        $this->postJson('/api/admin/eventos', $this->evento($recinto, [$platea, $general], ['costos' => [
            ['categoria' => 'recinto', 'concepto' => 'Alquiler del recinto', 'monto' => 5000],
        ]]))->assertStatus(422);
        $this->assertSame(0, DB::table('eventos')->count());

        $this->postJson('/api/admin/eventos', $this->evento($recinto, [$platea, $general]))->assertCreated();
        $this->assertSame(13000.0, (float) DB::table('costos_evento')->sum('monto'));
    }

    public function test_el_plano_del_recinto_trae_sus_localidades_y_butacas_libres(): void
    {
        [$recinto, $platea] = $this->teatro();
        Sanctum::actingAs($this->operador());

        $plano = $this->getJson("/api/admin/eventos/recintos/{$recinto}/plano")->assertOk()->json('data');

        $this->assertSame(['Platea', 'General'], array_column($plano['localidades'], 'nombre'));
        $this->assertCount(2, $plano['localidades'][0]['asientos']);
        $this->assertSame('disponible', $plano['localidades'][0]['asientos'][0]['estado']);
        $this->assertSame(30, $plano['localidades'][1]['disponibles']);
        $this->assertSame(32, $plano['resumen']['total']);

        $this->getJson('/api/admin/eventos/recintos/999/plano')->assertStatus(422);
    }

    public function test_la_experiencia_del_evento_usa_sus_tres_fotos_en_orden(): void
    {
        [$recinto, $platea, $general] = $this->teatro();
        $evento = DB::table('eventos')->insertGetId([
            'recinto_id' => $recinto, 'nombre' => 'Gala', 'estado' => 'publicado', 'fecha_evento' => now()->addMonth(),
            'imagen_url' => '/storage/eventos/portada.webp', 'imagen_url_2' => '/storage/eventos/dos.webp', 'imagen_url_3' => '/storage/eventos/tres.webp',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('inventario')->insert(['evento_id' => $evento, 'localidad_id' => $general, 'estado' => 'disponible', 'created_at' => now(), 'updated_at' => now()]);

        $html = $this->get("/eventos/{$evento}/experiencia")->assertOk()->getContent();

        $portada = strpos($html, 'eventos/portada.webp');
        $this->assertNotFalse($portada);
        $this->assertGreaterThan($portada, strpos($html, 'eventos/dos.webp'));
        $this->assertGreaterThan(strpos($html, 'eventos/dos.webp'), strpos($html, 'eventos/tres.webp'));
    }

    public function test_limpia_las_fotos_subidas_que_ningun_evento_usa(): void
    {
        Storage::fake('public');
        $disco = Storage::disk('public');
        [$recinto] = $this->teatro();
        DB::table('eventos')->insert([
            'recinto_id' => $recinto, 'nombre' => 'Gala', 'estado' => 'borrador', 'fecha_evento' => now()->addMonth(),
            'imagen_url' => '/storage/eventos/usada.webp', 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach (['usada.webp', 'abandonada.webp', 'reciente.webp'] as $archivo) {
            $disco->put("eventos/{$archivo}", 'x');
        }
        touch($disco->path('eventos/usada.webp'), now()->subDays(3)->getTimestamp());
        touch($disco->path('eventos/abandonada.webp'), now()->subDays(3)->getTimestamp());

        $this->artisan('eventos:limpiar-imagenes')->assertSuccessful();

        $disco->assertExists('eventos/usada.webp');
        $disco->assertMissing('eventos/abandonada.webp');
        $disco->assertExists('eventos/reciente.webp');   // pudo subirse hace un rato en un formulario abierto
    }
}
