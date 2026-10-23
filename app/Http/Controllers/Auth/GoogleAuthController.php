<?php
namespace App\Http\Controllers\Auth;

use App\Application\Abstractions\Auth\IIniciarSesionConGoogleUseCase;
use App\Application\DTOs\Auth\IniciarSesionConGoogleDTO;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;

/**
 * Rutas WEB (no /api): el navegador va y viene de Google con redirecciones.
 * Sin try/catch: si algo falla en el callback, bootstrap/app.php redirige al login con el mensaje.
 */
class GoogleAuthController extends Controller
{
    // 1) Botón "Continuar con Google" → mandamos el navegador a la pantalla de Google
    public function redirect(): SymfonyRedirect
    {
        return Socialite::driver('google')->redirect();
    }

    // 2) Google regresa aquí con ?code=... (o con ?error=... si la persona canceló)
    public function callback(Request $request, IIniciarSesionConGoogleUseCase $iniciarSesion): RedirectResponse
    {
        // Google regresa con ?error=access_denied tanto si la persona canceló
        // como si su cuenta no tiene acceso (app en modo Prueba y no es usuario de prueba).
        if ($request->has('error')) {
            return redirect('/login?tipo=aviso&mensaje='.urlencode(
                'No se completó el inicio de sesión con Google (lo cancelaste o tu cuenta no tiene acceso).'
            ));
        }

        // Socialite canjea el ?code con Google y obtiene los datos de la persona
        $google = Socialite::driver('google')->user();
        $datos = $google->getRaw();   // respuesta completa de Google (given_name, family_name, email_verified...)

        [$nombre, $apellido] = $this->separarNombre($datos, $google->getName());

        $sesion = $iniciarSesion->execute(new IniciarSesionConGoogleDTO(
            googleId: (string) $google->getId(),
            email: $google->getEmail(),
            emailVerificado: (bool) ($datos['email_verified'] ?? false),
            nombre: $nombre,
            apellido: $apellido,
        ));

        // El token va en el FRAGMENTO (#): el navegador nunca lo envía al servidor,
        // así no queda en logs de nginx. La vista lo guarda y lo borra de la URL.
        return redirect('/oauth/google#token='.urlencode($sesion->token));
    }

    /** @return array{string, string} */
    private function separarNombre(array $datos, ?string $nombreCompleto): array
    {
        $nombre = trim($datos['given_name'] ?? '');
        $apellido = trim($datos['family_name'] ?? '');

        // Algunas cuentas solo traen el nombre completo
        if ($nombre === '') {
            $partes = explode(' ', trim($nombreCompleto ?? ''), 2);
            $nombre = $partes[0] ?: 'Usuario';
            $apellido = $apellido ?: ($partes[1] ?? '');
        }

        // La entidad exige apellido; hay cuentas de Google sin él
        return [$nombre, $apellido !== '' ? $apellido : '-'];
    }
}
