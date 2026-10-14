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
}
