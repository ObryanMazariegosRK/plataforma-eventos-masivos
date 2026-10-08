<?php
namespace App\Http\Controllers\Auth;

use App\Application\Abstractions\Auth\ICambiarPasswordUseCase;
use App\Application\Abstractions\Auth\ICerrarSesionUseCase;
use App\Application\Abstractions\Auth\ICerrarTodasLasSesionesUseCase;
use App\Application\Abstractions\Auth\IIniciarSesionUseCase;
use App\Application\Abstractions\Auth\IObtenerPerfilUseCase;
use App\Application\Abstractions\Auth\IReenviarCodigoUseCase;
use App\Application\Abstractions\Auth\IRegistrarUsuarioUseCase;
use App\Application\Abstractions\Auth\IRestablecerPasswordUseCase;
use App\Application\Abstractions\Auth\ISolicitarRecuperacionUseCase;
use App\Application\Abstractions\Auth\IVerificarCorreoUseCase;
use App\Application\DTOs\Auth\CambiarPasswordDTO;
use App\Application\DTOs\Auth\IniciarSesionDTO;
use App\Application\DTOs\Auth\ReenviarCodigoDTO;
use App\Application\DTOs\Auth\RegistrarUsuarioDTO;
use App\Application\DTOs\Auth\RestablecerPasswordDTO;
use App\Application\DTOs\Auth\SesionDTO;
use App\Application\DTOs\Auth\SolicitarRecuperacionDTO;
use App\Application\DTOs\Auth\VerificarCorreoDTO;
use App\Domain\Entities\Auth\Usuario;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\CambiarPasswordRequest;
use App\Http\Requests\Auth\IniciarSesionRequest;
use App\Http\Requests\Auth\ReenviarCodigoRequest;
use App\Http\Requests\Auth\RegistrarUsuarioRequest;
use App\Http\Requests\Auth\RestablecerPasswordRequest;
use App\Http\Requests\Auth\SolicitarRecuperacionRequest;
use App\Http\Requests\Auth\VerificarCorreoRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// Sin try/catch: las excepciones de negocio se convierten a JSON en bootstrap/app.php
class AuthController extends Controller
{
    public function registro(RegistrarUsuarioRequest $request, IRegistrarUsuarioUseCase $registrar): JsonResponse
    {
        $usuario = $registrar->execute(new RegistrarUsuarioDTO(
            nombre: $request->validated('nombre'),
            apellido: $request->validated('apellido'),
            email: $request->validated('email'),
            password: $request->validated('password'),
            minutosValidezCodigo: config('autenticacion.codigo_verificacion_minutos'),
            telefono: $request->validated('telefono'),
        ));

        return response()->json([
            'message' => 'Usuario registrado. Te enviamos un código de verificación a tu correo.',
            'usuario' => $this->usuarioJson($usuario),
        ], 201);
    }

    public function verificarCorreo(VerificarCorreoRequest $request, IVerificarCorreoUseCase $verificar): JsonResponse
    {
        $sesion = $verificar->execute(new VerificarCorreoDTO(
            email: $request->validated('email'),
            codigo: $request->validated('codigo'),
        ));

        return $this->sesionJson('Correo verificado con éxito.', $sesion);
    }

    public function reenviarCodigo(ReenviarCodigoRequest $request, IReenviarCodigoUseCase $reenviar): JsonResponse
    {
        $reenviar->execute(new ReenviarCodigoDTO(
            email: $request->validated('email'),
            minutosValidezCodigo: config('autenticacion.codigo_verificacion_minutos'),
        ));

        // Misma respuesta exista o no el correo (no revela qué correos están registrados).
        return response()->json([
            'message' => 'Si el correo está registrado y pendiente de verificar, te enviamos un nuevo código.',
        ]);
    }

    public function login(IniciarSesionRequest $request, IIniciarSesionUseCase $iniciarSesion): JsonResponse
    {
        $sesion = $iniciarSesion->execute(new IniciarSesionDTO(
            email: $request->validated('email'),
            password: $request->validated('password'),
            recordar: $request->boolean('recordar'),
        ));

        return $this->sesionJson('Inicio de sesión exitoso.', $sesion);
    }

    public function logout(Request $request, ICerrarSesionUseCase $cerrarSesion): JsonResponse
    {
        // auth:sanctum ya dejó en $request el usuario y el token con el que llegó la petición
        $cerrarSesion->execute($request->user()->currentAccessToken()->id);

        return response()->json(['message' => 'Sesión cerrada.']);
    }

    public function logoutTodos(Request $request, ICerrarTodasLasSesionesUseCase $cerrarTodas): JsonResponse
    {
        $cerrarTodas->execute($request->user()->id);

        return response()->json(['message' => 'Se cerró la sesión en todos tus dispositivos.']);
    }

    public function cambiarPassword(CambiarPasswordRequest $request, ICambiarPasswordUseCase $cambiar): JsonResponse
    {
        $cambiar->execute(new CambiarPasswordDTO(
            usuarioId: $request->user()->id,
            passwordActual: $request->validated('password_actual'),
            passwordNueva: $request->validated('password'),
            tokenActualId: $request->user()->currentAccessToken()->id,
        ));

        return response()->json(['message' => 'Contraseña actualizada. Se cerró la sesión en tus otros dispositivos.']);
    }

    public function perfil(Request $request, IObtenerPerfilUseCase $obtenerPerfil): JsonResponse
    {
        $usuario = $obtenerPerfil->execute($request->user()->id);

        return response()->json($this->usuarioJson($usuario));
    }

    public function solicitarRecuperacion(SolicitarRecuperacionRequest $request, ISolicitarRecuperacionUseCase $solicitar): JsonResponse
    {
        $solicitar->execute(new SolicitarRecuperacionDTO(
            email: $request->validated('email'),
            minutosValidezCodigo: config('autenticacion.codigo_recuperacion_minutos'),
        ));

        // Misma respuesta exista o no el correo.
        return response()->json([
            'message' => 'Si el correo está registrado, te enviamos un código para restablecer tu contraseña.',
        ]);
    }

    public function restablecerPassword(RestablecerPasswordRequest $request, IRestablecerPasswordUseCase $restablecer): JsonResponse
    {
        $restablecer->execute(new RestablecerPasswordDTO(
            email: $request->validated('email'),
            codigo: $request->validated('codigo'),
            password: $request->validated('password'),
        ));

        return response()->json([
            'message' => 'Contraseña actualizada. Inicia sesión con tu nueva contraseña.',
        ]);
    }

    private function sesionJson(string $mensaje, SesionDTO $sesion): JsonResponse
    {
        return response()->json([
            'message' => $mensaje,
            'token'   => $sesion->token,
            'usuario' => $this->usuarioJson($sesion->usuario),
        ]);
    }

    // Solo datos seguros: nunca password ni código de verificación.
    private function usuarioJson(Usuario $u): array
    {
        return [
            'id'          => $u->getId(),
            'nombre'      => $u->getNombre(),
            'apellido'    => $u->getApellido(),
            'email'       => $u->getEmail(),
            'telefono'    => $u->getTelefono(),
            'rol'         => $u->getRol()->value,
            'verificado'  => $u->estaVerificado(),
        ];
    }
}
