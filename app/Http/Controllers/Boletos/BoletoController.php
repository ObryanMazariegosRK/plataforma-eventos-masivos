<?php

namespace App\Http\Controllers\Boletos;

use App\Application\Abstractions\Boletos\IObtenerQrBoletoUseCase;
use App\Http\Controllers\Controller;
use DomainException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BoletoController extends Controller
{
    public function qr(
        Request $request,
        IObtenerQrBoletoUseCase $obtenerQr,
        int $id
    ): Response {

        // 1. Obtener el usuario autenticado.
        $usuarioId = (int) $request->user()->id;

        try {

            // 2. Consultar el boleto y generar su QR.
            $svg = $obtenerQr->execute(
                $id,
                $usuarioId
            );

            // 3. Devolver la imagen QR en formato SVG.
            return response($svg, 200, [
                'Content-Type' => 'image/svg+xml; charset=UTF-8',
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]);

        } catch (DomainException $e) {

            // No revelar información de boletos ajenos.
            return response()->json([
                'message' => 'Boleto no disponible.'
            ], 404);
        }
    }

    public function validar(
        \Illuminate\Http\Request $request,
        \App\Application\UseCases\Boletos\ValidarBoletoUseCase $validarBoleto
    ): \Illuminate\Http\JsonResponse {

        // 1. Verificar que el usuario sea administrador.
        $usuario = $request->user();

        if (
            $usuario === null ||
            $usuario->rol !== \App\Domain\Enums\Auth\RolUsuario::ADMIN
        ) {
            return response()->json([
                'mensaje' => 'No tienes permisos para validar boletos.'
            ], 403);
        }

        // 2. Validar los datos recibidos.
        $datos = $request->validate([
            'codigo_qr' => [
                'required',
                'string',
                'size:64',
                'regex:/\A[a-f0-9]{64}\z/'
            ],
            'evento_id' => [
                'required',
                'integer',
                'min:1'
            ],
        ]);

        try {
            // 3. Validar el boleto y marcarlo como USADO.
            $boleto = $validarBoleto->execute(
                $datos['codigo_qr'],
                (int) $datos['evento_id']
            );

            return response()->json([
                'mensaje' => 'Boleto validado. Acceso permitido.',
                'boleto' => [
                    'id' => $boleto->getId(),
                    'estado' => $boleto->getEstado()->value,
                    'usado_en' => $boleto->getUsadoEn()?->format(DATE_ATOM),
                ]
            ], 200);

        } catch (\DomainException $e) {
            return response()->json([
                'mensaje' => 'Boleto no valido o no disponible para el acceso.'
            ], 409);
        }
    }

}
