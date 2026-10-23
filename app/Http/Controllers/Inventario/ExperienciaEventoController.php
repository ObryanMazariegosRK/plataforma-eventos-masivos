<?php
namespace App\Http\Controllers\Inventario;

use App\Application\Abstractions\Inventario\IConsultarDisponibilidadUseCase;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\View\View;

/**
 * Experiencia de un evento: el recorrido por el teatro y, al final, el plano para elegir asientos.
 * Si el evento no está a la venta, el caso de uso lanza EventoNoDisponibleException (→ 404).
 */
class ExperienciaEventoController extends Controller
{
    private const ZONA_HORARIA = 'America/Guatemala';   // la base de datos guarda la hora en UTC

    public function show(int $eventoId, IConsultarDisponibilidadUseCase $consultar): View
    {
        $evento = $consultar->execute($eventoId)['evento'];

        $fecha = $evento['fecha_evento']
            ? Carbon::parse($evento['fecha_evento'], 'UTC')
                ->timezone(self::ZONA_HORARIA)
                ->locale('es')
                ->isoFormat('dddd D [de] MMMM · h:mm a')
            : null;

        return view('eventos.experiencia', [
            'eventoId' => $eventoId,
            'evento'   => $evento,
            'detalle'  => implode(' · ', array_filter([$evento['recinto'], $fecha])),
        ]);
    }
}
