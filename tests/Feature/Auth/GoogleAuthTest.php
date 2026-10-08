<?php

namespace Tests\Feature\Auth;

use App\Domain\Enums\Auth\EstadoUsuario;
use App\Domain\Enums\Auth\RolUsuario;
use App\Models\Auth\Usuario as UsuarioModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Tests\TestCase;

class GoogleAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirect_manda_a_la_pantalla_de_google(): void
    {
        config(['services.google.client_id' => 'id-de-prueba', 'services.google.client_secret' => 'secreto']);

        $this->get('/auth/google/redirect')->assertRedirectContains('accounts.google.com');
    }

    public function test_callback_crea_la_cuenta_y_entrega_un_token_que_funciona(): void
    {
        $this->simularGoogle();

        $respuesta = $this->get('/auth/google/callback?code=codigo-de-google&state=x');

        $respuesta->assertRedirectContains('/oauth/google#token=');
        $this->assertDatabaseHas('usuarios', ['email' => 'ana@example.com', 'google_id' => 'g-123', 'password' => null]);

        // el token del fragmento sirve para la API
        $token = urldecode(explode('#token=', $respuesta->headers->get('Location'))[1]);
        $this->withToken($token)->getJson('/api/auth/perfil')
            ->assertOk()
            ->assertJsonPath('google', true)
            ->assertJsonPath('verificado', true);
    }

    public function test_si_google_responde_error_regresa_al_login_sin_llamar_a_google(): void
    {
        Socialite::shouldReceive('driver')->never();

        $this->get('/auth/google/callback?error=access_denied')
            ->assertRedirectContains('/login?tipo=aviso');
    }

    public function test_usuario_bloqueado_regresa_al_login_con_el_mensaje(): void
    {
        UsuarioModel::create([
            'nombre' => 'Ana', 'apellido' => 'López', 'email' => 'ana@example.com',
            'password' => Hash::make('Secreto#123'), 'rol' => RolUsuario::CLIENTE,
            'estado' => EstadoUsuario::BLOQUEADO, 'email_verified_at' => now(),
        ]);
        $this->simularGoogle();

        $this->get('/auth/google/callback?code=x&state=x')
            ->assertRedirectContains('/login?tipo=error')
            ->assertRedirectContains(urlencode('bloqueada'));
    }

    public function test_un_error_de_google_regresa_al_login(): void
    {
        Socialite::shouldReceive('driver->user')->andThrow(new \RuntimeException('Google rechazó el código'));

        $this->get('/auth/google/callback?code=x&state=x')
            ->assertRedirectContains('/login?tipo=error');
    }

    // Reemplaza a Google: Socialite::driver('google')->user() devuelve esta persona
    private function simularGoogle(): void
    {
        $persona = (new GoogleUser())
            ->setRaw(['given_name' => 'Ana', 'family_name' => 'López', 'email_verified' => true])
            ->map(['id' => 'g-123', 'name' => 'Ana López', 'email' => 'ana@example.com']);

        Socialite::shouldReceive('driver->user')->andReturn($persona);
    }
}
