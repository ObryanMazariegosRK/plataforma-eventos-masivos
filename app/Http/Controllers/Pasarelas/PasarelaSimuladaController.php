<?php
namespace App\Http\Controllers\Pasarelas;

use App\Http\Controllers\Controller;
use App\Infrastructure\Pasarelas\PasarelaSimulada;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Response;

/**
 * Imita la página de una pasarela externa (como la pantalla de PayPal).
 * Solo existe para pruebas: en una pasarela real esta página es del proveedor.
 */
class PasarelaSimuladaController extends Controller
{
    public function mostrar(string $orden, PasarelaSimulada $pasarela): Response
    {
        $datos = $pasarela->obtener($orden);

        // Al volver con "Atrás" o abrir un enlace viejo: explicar el estado en vez del formulario
        if ($datos === null) {
            return response()->view('pasarelas.simulada-estado', ['estado' => 'no_existe', 'datos' => null], 404);
        }
        if ($datos['estado'] !== PasarelaSimulada::PENDIENTE) {
            return response()->view('pasarelas.simulada-estado', ['estado' => 'procesada', 'datos' => $datos]);
        }
        if ($pasarela->vencida($datos)) {
            return response()->view('pasarelas.simulada-estado', ['estado' => 'vencida', 'datos' => $datos]);
        }

        return response()->view('pasarelas.simulada', ['orden' => $orden, 'datos' => $datos]);
    }

    public function decidir(Request $request, string $orden, PasarelaSimulada $pasarela): RedirectResponse
    {
        $accion = $request->validate(['accion' => ['required', 'in:pagar,cancelar']])['accion'];

        // Orden ya decidida, vencida o inexistente (p. ej. se reenvió el formulario tras volver
        // con "Atrás"): no se procesa de nuevo; la página explica el estado
        $datos = $pasarela->obtener($orden);
        if ($datos === null || $datos['estado'] !== PasarelaSimulada::PENDIENTE || $pasarela->vencida($datos)) {
            return redirect()->route('pasarela-simulada.mostrar', $orden);
        }

        if ($accion === 'pagar') {
            $tarjeta = $request->validate([
                'titular' => ['required', 'string', 'max:80'],
                'numero'  => ['required', 'string', 'max:25'],
                'vence'   => ['required', 'string', 'max:7'],
                'cvv'     => ['required', 'string', 'max:4'],
            ], [
                'titular.required' => 'Escribe el nombre que aparece en la tarjeta.',
                '*.required'       => 'Completa todos los datos de la tarjeta.',
            ]);

            // Como un banco: datos que no coinciden con una tarjeta → se queda en la página
            $accion = $pasarela->evaluarTarjeta($tarjeta['numero'], $tarjeta['vence'], $tarjeta['cvv'])
                ?? throw ValidationException::withMessages([
                    'tarjeta' => 'Los datos de la tarjeta no son correctos. Revisa el número, la fecha de vencimiento y el CVV.',
                ])->redirectTo(route('pasarela-simulada.mostrar', $orden));
        }

        // null solo si otra pestaña la decidió en este instante: se muestra el estado
        $destino = $pasarela->decidir($orden, $accion);

        return $destino === null
            ? redirect()->route('pasarela-simulada.mostrar', $orden)
            : redirect()->away($destino);
    }
}
