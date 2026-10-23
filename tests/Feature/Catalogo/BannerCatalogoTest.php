<?php

namespace Tests\Feature\Catalogo;

use App\Domain\Enums\Auth\EstadoUsuario;
use App\Domain\Enums\Auth\RolUsuario;
use App\Models\Auth\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BannerCatalogoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function usuario(RolUsuario $rol): Usuario
    {
        return Usuario::create([
            'nombre' => 'Usuario',
            'apellido' => 'Prueba',
            'email' => $rol->value . '-banner@test.com',
            'password' => bcrypt('Secreto#123'),
            'rol' => $rol,
            'estado' => EstadoUsuario::ACTIVO,
            'email_verified_at' => now(),
        ]);
    }

    private function subir(UploadedFile $archivo)
    {
        return $this->post('/api/admin/banner', ['archivo' => $archivo], ['Accept' => 'application/json']);
    }

    public function test_sin_banner_el_catalogo_usa_la_foto_por_defecto(): void
    {
        $this->getJson('/api/catalogo/banner')->assertOk()->assertExactJson(['data' => null]);
    }

    public function test_el_admin_sube_una_imagen_y_el_catalogo_la_muestra(): void
    {
        Sanctum::actingAs($this->usuario(RolUsuario::ADMIN));

        $respuesta = $this->subir(UploadedFile::fake()->create('teatro.jpg', 300, 'image/jpeg'))
            ->assertOk()
            ->assertJsonPath('data.tipo', 'imagen');

        $ruta = str_replace('/storage/', '', $respuesta->json('data.url'));
        Storage::disk('public')->assertExists($ruta);

        $this->getJson('/api/catalogo/banner')
            ->assertOk()
            ->assertJsonPath('data.tipo', 'imagen')
            ->assertJsonPath('data.url', $respuesta->json('data.url'));
    }

    public function test_cambiar_a_video_reemplaza_el_banner_y_borra_la_imagen(): void
    {
        Sanctum::actingAs($this->usuario(RolUsuario::ADMIN));

        $imagen = $this->subir(UploadedFile::fake()->create('teatro.jpg', 300, 'image/jpeg'))->json('data.url');
        $video = $this->subir(UploadedFile::fake()->create('telon.mp4', 2000, 'video/mp4'))
            ->assertOk()
            ->assertJsonPath('data.tipo', 'video')
            ->json('data.url');

        Storage::disk('public')->assertMissing(str_replace('/storage/', '', $imagen));
        Storage::disk('public')->assertExists(str_replace('/storage/', '', $video));
        $this->assertDatabaseCount('banners', 1);
    }

    public function test_rechaza_archivos_que_no_son_imagen_ni_video(): void
    {
        Sanctum::actingAs($this->usuario(RolUsuario::ADMIN));

        $this->subir(UploadedFile::fake()->create('contrato.pdf', 100, 'application/pdf'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('archivo');

        $this->assertDatabaseCount('banners', 0);
    }

    public function test_rechaza_archivos_de_mas_de_15_mb(): void
    {
        Sanctum::actingAs($this->usuario(RolUsuario::ADMIN));

        $this->subir(UploadedFile::fake()->create('largo.mp4', 16 * 1024, 'video/mp4'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('archivo');
    }

    public function test_un_cliente_no_puede_cambiar_el_banner(): void
    {
        Sanctum::actingAs($this->usuario(RolUsuario::CLIENTE));

        $this->subir(UploadedFile::fake()->create('teatro.jpg', 300, 'image/jpeg'))->assertForbidden();
    }

    public function test_quitar_el_banner_vuelve_a_la_foto_por_defecto(): void
    {
        Sanctum::actingAs($this->usuario(RolUsuario::ADMIN));
        $url = $this->subir(UploadedFile::fake()->create('teatro.jpg', 300, 'image/jpeg'))->json('data.url');

        $this->deleteJson('/api/admin/banner')->assertOk()->assertJsonPath('data', null);

        Storage::disk('public')->assertMissing(str_replace('/storage/', '', $url));
        $this->getJson('/api/catalogo/banner')->assertExactJson(['data' => null]);
    }
}
