<?php
namespace App\Http\Controllers\Pasarelas;

use App\Http\Controllers\Controller;
use App\Infrastructure\Pasarelas\PasarelaSimulada;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Imita la página de una pasarela externa (como la pantalla de PayPal).
 * Solo existe para pruebas: en una pasarela real esta página es del proveedor.
 */
class PasarelaSimuladaController extends Controller
{
    public function mostrar(string $orden, PasarelaSimulada $pasarela): View
    {
        $datos = $pasarela->obtener($orden);
        abort_if($datos === null, 404, 'La orden de pago no existe o venció.');

        return view('pasarelas.simulada', ['orden' => $orden, 'datos' => $datos]);
    }

    public function decidir(Request $request, string $orden, PasarelaSimulada $pasarela): RedirectResponse
    {
        $accion = $request->validate(['accion' => ['required', 'in:aprobar,rechazar,cancelar']])['accion'];

        $destino = $pasarela->decidir($orden, $accion);
        abort_if($destino === null, 409, 'Esta orden ya fue procesada o venció.');

        return redirect()->away($destino);
    }
}
