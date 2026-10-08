<?php

namespace Tests\Feature\Auth;

use App\Domain\Enums\Auth\EstadoUsuario;
use App\Domain\Enums\Auth\RolUsuario;
use App\Infrastructure\Auth\Mail\CodigoRecuperacionMail;
use App\Infrastructure\Auth\Mail\CodigoVerificacionMail;
use App\Models\Auth\Usuario as UsuarioModel;
use App\Domain\Exceptions\Auth\CredencialesInvalidasException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_flujo_completo_registro_verificacion_perfil_y_logout(): void
    {
        Mail::fake();

        // 1) registro → 201 y se envía el correo con el código
        $this->postJson('/api/auth/registro', [
            'nombre'   => 'Ana',
            'apellido' => 'López',
            'email'    => 'ana@example.com',
            'password' => 'Secreto#123',
        ])->assertCreated()
            ->assertJsonPath('usuario.rol', 'cliente')
            ->assertJsonPath('usuario.verificado', false)
            ->assertJsonMissingPath('usuario.password');

        $codigo = UsuarioModel::where('email', 'ana@example.com')->value('codigo_verificacion');
        Mail::assertQueued(CodigoVerificacionMail::class, fn ($mail) => $mail->hasTo('ana@example.com') && $mail->codigo === $codigo);

        // 2) login antes de verificar → 403
        $this->postJson('/api/auth/login', ['email' => 'ana@example.com', 'password' => 'Secreto#123'])
            ->assertForbidden()
            ->assertJsonPath('error', 'correo_no_verificado');

        // 3) verificar → 200 con token
        $token = $this->postJson('/api/auth/verificar-correo', ['email' => 'ana@example.com', 'codigo' => $codigo])
            ->assertOk()
            ->assertJsonPath('usuario.verificado', true)
            ->json('token');

        // 4) perfil con el token
        $this->withToken($token)->getJson('/api/auth/perfil')
            ->assertOk()
            ->assertJsonPath('email', 'ana@example.com');

        // 5) logout → el token deja de servir
        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();
        $this->app['auth']->forgetGuards();   // Laravel cachea el usuario entre peticiones de una misma prueba

        $this->withToken($token)->getJson('/api/auth/perfil')->assertUnauthorized();
    }

    public function test_login_exitoso_devuelve_token(): void
    {
        $this->crearUsuario();

        $this->postJson('/api/auth/login', ['email' => 'ana@example.com', 'password' => 'Secreto#123'])
            ->assertOk()
            ->assertJsonStructure(['message', 'token', 'usuario' => ['id', 'nombre', 'email', 'rol']]);
    }

    public function test_login_con_contrasena_incorrecta_responde_401_sin_ensuciar_el_log(): void
    {
        Exceptions::fake();
        $this->crearUsuario();

        $this->postJson('/api/auth/login', ['email' => 'ana@example.com', 'password' => 'Incorrecta#1'])
            ->assertUnauthorized()
            ->assertJsonPath('error', 'credenciales_invalidas');

        // es una respuesta esperada, no un fallo: no debe escribirse en laravel.log
        Exceptions::assertNotReported(CredencialesInvalidasException::class);
    }

    public function test_usuario_bloqueado_responde_403(): void
    {
        $this->crearUsuario(estado: EstadoUsuario::BLOQUEADO);

        $this->postJson('/api/auth/login', ['email' => 'ana@example.com', 'password' => 'Secreto#123'])
            ->assertForbidden()
            ->assertJsonPath('error', 'usuario_bloqueado');
    }

    public function test_registro_con_correo_repetido_responde_409(): void
    {
        Mail::fake();
        $this->crearUsuario();

        $this->postJson('/api/auth/registro', [
            'nombre'   => 'Otra',
            'apellido' => 'Persona',
            'email'    => 'ana@example.com',
            'password' => 'Secreto#123',
        ])->assertStatus(409)
            ->assertJsonPath('error', 'correo_ya_registrado');
    }

    public function test_registro_valida_los_datos_de_entrada(): void
    {
        $this->postJson('/api/auth/registro', ['email' => 'no-es-correo', 'password' => 'corta'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['nombre', 'apellido', 'email', 'password']);
    }

    public function test_reenviar_codigo_responde_igual_aunque_el_correo_no_exista(): void
    {
        Mail::fake();

        $this->postJson('/api/auth/reenviar-codigo', ['email' => 'nadie@example.com'])->assertOk();

        Mail::assertNothingOutgoing();   // ni enviado ni encolado
    }

    public function test_perfil_sin_token_responde_401(): void
    {
        $this->getJson('/api/auth/perfil')->assertUnauthorized();
    }

    public function test_middleware_de_rol(): void
    {
        Route::middleware(['auth:sanctum', 'rol:admin'])->get('/api/prueba-rol', fn () => response()->json(['ok' => true]));

        $cliente = $this->crearUsuario();
        $this->withToken($cliente->createToken('t')->plainTextToken)->getJson('/api/prueba-rol')->assertForbidden();

        $this->app['auth']->forgetGuards();

        $admin = $this->crearUsuario(email: 'admin@example.com', rol: RolUsuario::ADMIN);
        $this->withToken($admin->createToken('t')->plainTextToken)->getJson('/api/prueba-rol')->assertOk();
    }

    public function test_usuario_bloqueado_pierde_el_acceso_aunque_su_token_siga_vigente(): void
    {
        $usuario = $this->crearUsuario();
        $token = $usuario->createToken('t')->plainTextToken;

        $this->withToken($token)->getJson('/api/auth/perfil')->assertOk();

        // un admin lo bloquea (simulado directo en la BD)
        $usuario->update(['estado' => EstadoUsuario::BLOQUEADO]);
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/auth/perfil')
            ->assertForbidden()
            ->assertJsonPath('error', 'usuario_bloqueado');
    }

    public function test_flujo_completo_de_recuperacion_de_password(): void
    {
        Mail::fake();
        $usuario = $this->crearUsuario();
        $tokenViejo = $usuario->createToken('t')->plainTextToken;

        // 1) pedir el código
        $this->postJson('/api/auth/olvide-password', ['email' => 'ana@example.com'])->assertOk();

        $codigo = UsuarioModel::where('email', 'ana@example.com')->value('codigo_recuperacion');
        Mail::assertQueued(CodigoRecuperacionMail::class, fn ($mail) => $mail->hasTo('ana@example.com') && $mail->codigo === $codigo);

        // 2) confirmación distinta → 422
        $this->postJson('/api/auth/restablecer-password', [
            'email' => 'ana@example.com', 'codigo' => $codigo,
            'password' => 'Nueva#456', 'password_confirmation' => 'Otra#456',
        ])->assertUnprocessable()->assertJsonValidationErrors(['password']);

        // 3) restablecer
        $this->postJson('/api/auth/restablecer-password', [
            'email' => 'ana@example.com', 'codigo' => $codigo,
            'password' => 'Nueva#456', 'password_confirmation' => 'Nueva#456',
        ])->assertOk();

        // 4) las sesiones anteriores quedaron cerradas
        $this->withToken($tokenViejo)->getJson('/api/auth/perfil')->assertUnauthorized();

        // 5) la contraseña vieja ya no sirve, la nueva sí
        $this->postJson('/api/auth/login', ['email' => 'ana@example.com', 'password' => 'Secreto#123'])->assertUnauthorized();
        $this->postJson('/api/auth/login', ['email' => 'ana@example.com', 'password' => 'Nueva#456'])->assertOk();

        // 6) el código ya se usó
        $this->postJson('/api/auth/restablecer-password', [
            'email' => 'ana@example.com', 'codigo' => $codigo,
            'password' => 'Otra#789', 'password_confirmation' => 'Otra#789',
        ])->assertUnprocessable()->assertJsonPath('error', 'codigo_recuperacion_invalido');
    }

    public function test_olvide_password_responde_igual_aunque_el_correo_no_exista(): void
    {
        Mail::fake();

        $this->postJson('/api/auth/olvide-password', ['email' => 'nadie@example.com'])->assertOk();

        Mail::assertNothingOutgoing();   // ni enviado ni encolado
    }

    public function test_cambiar_password_conserva_esta_sesion_y_cierra_las_demas(): void
    {
        $usuario = $this->crearUsuario();
        $tokenCelular = $usuario->createToken('celular')->plainTextToken;
        $tokenLaptop = $usuario->createToken('laptop')->plainTextToken;

        // contraseña actual incorrecta → 422 (no 401: la sesión sigue siendo válida)
        $this->withToken($tokenLaptop)->postJson('/api/auth/cambiar-password', [
            'password_actual' => 'Equivocada#1',
            'password' => 'Nueva#456', 'password_confirmation' => 'Nueva#456',
        ])->assertUnprocessable()->assertJsonPath('error', 'password_actual_incorrecta');

        // cambio correcto desde la laptop
        $this->withToken($tokenLaptop)->postJson('/api/auth/cambiar-password', [
            'password_actual' => 'Secreto#123',
            'password' => 'Nueva#456', 'password_confirmation' => 'Nueva#456',
        ])->assertOk();
        $this->app['auth']->forgetGuards();

        $this->withToken($tokenLaptop)->getJson('/api/auth/perfil')->assertOk();            // sigue dentro
        $this->app['auth']->forgetGuards();
        $this->withToken($tokenCelular)->getJson('/api/auth/perfil')->assertUnauthorized();  // quedó fuera

        $this->postJson('/api/auth/login', ['email' => 'ana@example.com', 'password' => 'Nueva#456'])->assertOk();
    }

    public function test_logout_todos_cierra_todas_las_sesiones_incluida_la_actual(): void
    {
        $usuario = $this->crearUsuario();
        $tokenA = $usuario->createToken('a')->plainTextToken;
        $tokenB = $usuario->createToken('b')->plainTextToken;

        $this->withToken($tokenA)->postJson('/api/auth/logout-todos')->assertOk();
        $this->app['auth']->forgetGuards();

        $this->withToken($tokenA)->getJson('/api/auth/perfil')->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->withToken($tokenB)->getJson('/api/auth/perfil')->assertUnauthorized();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_las_vistas_de_autenticacion_cargan(): void
    {
        foreach (['/login', '/registro', '/verificar', '/perfil', '/olvide-password', '/restablecer-password'] as $ruta) {
            $this->get($ruta)->assertOk();
        }
    }

    private function crearUsuario(
        string $email = 'ana@example.com',
        RolUsuario $rol = RolUsuario::CLIENTE,
        EstadoUsuario $estado = EstadoUsuario::ACTIVO,
    ): UsuarioModel {
        return UsuarioModel::create([
            'nombre'            => 'Ana',
            'apellido'          => 'López',
            'email'             => $email,
            'password'          => Hash::make('Secreto#123'),
            'rol'               => $rol,
            'estado'            => $estado,
            'email_verified_at' => now(),
        ]);
    }
}
