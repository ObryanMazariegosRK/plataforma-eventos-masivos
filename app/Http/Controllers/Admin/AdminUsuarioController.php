<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Auth\Usuario;
use App\Domain\Enums\Auth\RolUsuario;
use App\Domain\Enums\Auth\EstadoUsuario;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminUsuarioController extends Controller
{
    // =====================================================
    // RESPUESTA SEGURA DEL USUARIO
    // =====================================================

    private function datos(Usuario $usuario): array
    {
        return $usuario->only([
            'id',
            'nombre',
            'apellido',
            'email',
            'telefono',
            'rol',
            'estado',
            'email_verified_at',
            'created_at',
            'updated_at',
        ]);
    }

    // =====================================================
    // PROTECCION DE ADMINISTRADORES
    // =====================================================

    private function protegerAdministrador(
        Usuario $usuario,
        ?string $nuevoRol = null,
        ?string $nuevoEstado = null,
        bool $eliminar = false,
        int $administradoresActivos = 0
    ): void {
        if (
            $usuario->rol !== RolUsuario::ADMIN ||
            $usuario->estado !== EstadoUsuario::ACTIVO
        ) {
            return;
        }

        $pierdeAcceso = $eliminar
            || (
                $nuevoRol !== null &&
                $nuevoRol !== RolUsuario::ADMIN->value
            )
            || (
                $nuevoEstado === EstadoUsuario::BLOQUEADO->value
            );

        if (! $pierdeAcceso) {
            return;
        }

        if ($administradoresActivos <= 1) {
            throw ValidationException::withMessages([
                'rol' => [
                    'No puedes quitar el acceso al último administrador activo.'
                ],
            ]);
        }
    }

    /**
     * Bloquea las cuentas administradoras activas antes de
     * modificar roles, estados o eliminar cuentas.
     *
     * Debe llamarse dentro de DB::transaction().
     */
    private function bloquearAdministradores(): int
    {
        return Usuario::query()
            ->where('rol', RolUsuario::ADMIN->value)
            ->where('estado', EstadoUsuario::ACTIVO->value)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['id'])
            ->count();
    }

    private function verificarCuentaPropia(
        Request $request,
        Usuario $usuario
    ): void {
        if ((int) $usuario->id === (int) $request->user()->id) {
            throw ValidationException::withMessages([
                'usuario' => [
                    'No puedes modificar los permisos, bloquear ni eliminar tu propia cuenta.'
                ],
            ]);
        }
    }

    // =====================================================
    // CONSULTAR USUARIO
    // =====================================================

    public function mostrar(Usuario $usuario): JsonResponse
    {
        return response()->json([
            'data' => $this->datos($usuario),
        ]);
    }

    // =====================================================
    // CREAR USUARIO
    // =====================================================

    public function crear(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'apellido' => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('usuarios', 'email'),
            ],
            'telefono' => ['nullable', 'string', 'max:30'],
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
            ],
            'rol' => [
                'required',
                Rule::in(['cliente', 'organizador', 'admin']),
            ],
        ]);

        $usuario = DB::transaction(function () use ($datos, $request) {
            $usuario = new Usuario();

            $usuario->nombre = $datos['nombre'];
            $usuario->apellido = $datos['apellido'];
            $usuario->email = $datos['email'];
            $usuario->telefono = $datos['telefono'] ?? null;

            $usuario->password = Hash::make($datos['password']);

            $usuario->rol = RolUsuario::from($datos['rol']);
            $usuario->estado = EstadoUsuario::ACTIVO;

            // Alta realizada directamente por un administrador.
            $usuario->email_verified_at = now();

            $usuario->created_by = $request->user()->id;
            $usuario->updated_by = $request->user()->id;

            $usuario->save();

            return $usuario;
        });

        return response()->json([
            'message' => 'Usuario creado correctamente.',
            'data' => $this->datos($usuario),
        ], 201);
    }

    // =====================================================
    // EDITAR INFORMACION
    // =====================================================

    public function actualizar(
        Request $request,
        Usuario $usuario
    ): JsonResponse {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'apellido' => ['required', 'string', 'max:100'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('usuarios', 'email')
                    ->ignore($usuario->id),
            ],
            'telefono' => ['nullable', 'string', 'max:30'],
        ]);

        $usuario->nombre = $datos['nombre'];
        $usuario->apellido = $datos['apellido'];
        $usuario->email = $datos['email'];
        $usuario->telefono = $datos['telefono'] ?? null;

        $usuario->updated_by = $request->user()->id;
        $usuario->save();

        return response()->json([
            'message' => 'Usuario actualizado correctamente.',
            'data' => $this->datos($usuario),
        ]);
    }

    // =====================================================
    // CAMBIAR ROL
    // =====================================================

    public function cambiarRol(
        Request $request,
        Usuario $usuario
    ): JsonResponse {
        $datos = $request->validate([
            'rol' => [
                'required',
                Rule::in(['cliente', 'organizador', 'admin']),
            ],
        ]);

        $this->verificarCuentaPropia($request, $usuario);

        DB::transaction(function () use ($usuario, $datos, $request) {
            $totalAdmins = $this->bloquearAdministradores();

            $cuenta = Usuario::query()
                ->whereKey($usuario->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->verificarCuentaPropia($request, $cuenta);

            $this->protegerAdministrador(
                $cuenta,
                nuevoRol: $datos['rol'],
                administradoresActivos: $totalAdmins
            );

            $nuevoRol = RolUsuario::from($datos['rol']);

            if ($cuenta->rol === $nuevoRol) {
                return;
            }

            $cuenta->rol = $nuevoRol;
            $cuenta->updated_by = $request->user()->id;
            $cuenta->save();

            // Invalidar los tokens de sesiones anteriores.
            $cuenta->tokens()->delete();
        }, 3);

        return response()->json([
            'message' => 'Rol actualizado correctamente. Las sesiones anteriores fueron cerradas.',
        ]);
    }

    // =====================================================
    // ACTIVAR O BLOQUEAR
    // =====================================================

    public function cambiarEstado(
        Request $request,
        Usuario $usuario
    ): JsonResponse {
        $datos = $request->validate([
            'estado' => [
                'required',
                Rule::in(['activo', 'bloqueado']),
            ],
        ]);

        $this->verificarCuentaPropia($request, $usuario);

        DB::transaction(function () use ($usuario, $datos, $request) {
            $totalAdmins = $this->bloquearAdministradores();

            $cuenta = Usuario::query()
                ->whereKey($usuario->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->verificarCuentaPropia($request, $cuenta);

            $this->protegerAdministrador(
                $cuenta,
                nuevoEstado: $datos['estado'],
                administradoresActivos: $totalAdmins
            );

            $nuevoEstado = EstadoUsuario::from($datos['estado']);

            if ($cuenta->estado === $nuevoEstado) {
                return;
            }

            $cuenta->estado = $nuevoEstado;
            $cuenta->updated_by = $request->user()->id;
            $cuenta->save();

            if ($nuevoEstado === EstadoUsuario::BLOQUEADO) {
                $cuenta->tokens()->delete();
            }
        }, 3);

        return response()->json([
            'message' => 'Estado actualizado correctamente.',
        ]);
    }

    // =====================================================
    // ELIMINACION LOGICA
    // =====================================================

    public function eliminar(
        Request $request,
        Usuario $usuario
    ): JsonResponse {
        $this->verificarCuentaPropia($request, $usuario);

        DB::transaction(function () use ($usuario, $request) {
            $totalAdmins = $this->bloquearAdministradores();

            $cuenta = Usuario::query()
                ->whereKey($usuario->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->verificarCuentaPropia($request, $cuenta);

            $this->protegerAdministrador(
                $cuenta,
                eliminar: true,
                administradoresActivos: $totalAdmins
            );

            $cuenta->updated_by = $request->user()->id;
            $cuenta->save();

            // Revocar accesos.
            $cuenta->tokens()->delete();

            // SoftDeletes: conserva los registros relacionados.
            $cuenta->delete();
        }, 3);

        return response()->json([
            'message' => 'Usuario eliminado de forma lógica.',
        ]);
    }
}
