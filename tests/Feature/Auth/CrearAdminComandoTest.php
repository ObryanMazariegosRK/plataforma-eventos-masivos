<?php

namespace Tests\Feature\Auth;

use App\Domain\Enums\Auth\EstadoUsuario;
use App\Domain\Enums\Auth\RolUsuario;
use App\Models\Auth\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CrearAdminComandoTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_un_admin_verificado_y_activo(): void
    {
        $this->artisan('usuario:crear-admin', ['--email' => 'Ana@Correo.com', '--nombre' => 'Ana', '--apellido' => 'López'])
            ->expectsQuestion('Contraseña', 'Secreto#123')
            ->expectsQuestion('Repite la contraseña', 'Secreto#123')
            ->assertSuccessful();

        $admin = Usuario::where('email', 'ana@correo.com')->firstOrFail();
        $this->assertSame(RolUsuario::ADMIN, $admin->rol);
        $this->assertSame(EstadoUsuario::ACTIVO, $admin->estado);
        $this->assertNotNull($admin->email_verified_at);
        $this->assertTrue(Hash::check('Secreto#123', $admin->password));

        // y puede iniciar sesión
        $this->postJson('/api/auth/login', ['email' => 'ana@correo.com', 'password' => 'Secreto#123'])
            ->assertOk()
            ->assertJsonPath('usuario.rol', 'admin');
    }

    public function test_rechaza_una_contrasena_debil(): void
    {
        $this->artisan('usuario:crear-admin', ['--email' => 'ana@correo.com', '--nombre' => 'Ana', '--apellido' => 'López'])
            ->expectsQuestion('Contraseña', '123')
            ->expectsQuestion('Repite la contraseña', '123')
            ->assertFailed();

        $this->assertDatabaseCount('usuarios', 0);
    }

    public function test_promueve_una_cuenta_existente_sin_cambiar_su_contrasena(): void
    {
        $cliente = Usuario::create([
            'nombre' => 'Luis', 'apellido' => 'Pérez', 'email' => 'luis@correo.com',
            'password' => Hash::make('Original#123'), 'rol' => RolUsuario::CLIENTE,
            'estado' => EstadoUsuario::ACTIVO,
        ]);

        $this->artisan('usuario:crear-admin', ['--email' => 'luis@correo.com'])
            ->expectsConfirmation('¿Convertirla en administrador, verificada y activa?', 'yes')
            ->expectsConfirmation('¿Cambiarle también la contraseña?', 'no')
            ->assertSuccessful();

        $cliente->refresh();
        $this->assertSame(RolUsuario::ADMIN, $cliente->rol);
        $this->assertNotNull($cliente->email_verified_at);
        $this->assertTrue(Hash::check('Original#123', $cliente->password));
    }
}
