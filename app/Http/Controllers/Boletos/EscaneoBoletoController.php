<?php

namespace App\Http\Controllers\Boletos;

use App\Application\Abstractions\Boletos\IConsultarBoletoEscaneadoUseCase;
use App\Application\DTOs\Boletos\ConsultarBoletoEscaneadoDTO;
use App\Domain\Enums\Auth\RolUsuario;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// Página /b (QR escaneado) y Panel → Control de acceso: ¿de quién es y puede entrar?
// Registrar la entrada es otra petición: POST /api/boletos/validar (nunca al solo escanear).
class EscaneoBoletoController extends Controller
{
    public function consultar(Request $request, IConsultarBoletoEscaneadoUseCase $consultar): JsonResponse
    {
        // POST y no GET: el código es secreto y no debe quedar en una URL ni en los registros
        $datos = $request->validate(['codigo' => ['required', 'string', 'max:500']]);
        $usuario = $request->user();

        return response()->json(['data' => $consultar->execute(new ConsultarBoletoEscaneadoDTO(
            codigo: $datos['codigo'],
            usuarioId: (int) $usuario->id,
            esPersonal: in_array($usuario->rol, [RolUsuario::ADMIN, RolUsuario::ORGANIZADOR], true),
            ahora: now()->toDateTimeImmutable(),
            zonaHoraria: config('reportes.zona_horaria'),
        ))]);
    }
}
