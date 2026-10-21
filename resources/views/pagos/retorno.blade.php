@extends('layouts.autenticacion')

@section('titulo', 'Confirmando pago')
@section('eslogan', 'Estamos confirmando tu pago.')

@section('contenido')
    <div id="alerta" class="alerta" role="alert"></div>

    <div id="cargando">
        <h1>Confirmando tu pago...</h1>
        <p class="subtitulo">No cierres ni recargues esta página.</p>
    </div>

    {{-- Se muestra si el pago se aprobó --}}
    <div id="exito" hidden>
        <h1>¡Pago aprobado!</h1>
        <p class="subtitulo">Tus boletos se están emitiendo y te llegará un correo de confirmación.</p>
        <ul class="datos">
            <li id="filaEvento" hidden><span>Evento</span><span id="evento"></span></li>
            <li id="filaFecha" hidden><span>Fecha</span><span id="fecha"></span></li>
            <li id="filaLugares" hidden><span>Lugares</span><span id="lugares" class="datos__lugares"></span></li>
            <li><span>Monto</span><span id="monto"></span></li>
            <li><span>Referencia</span><span id="referencia"></span></li>
        </ul>
        <a href="{{ route('reservas.mis-compras') }}" class="boton" style="display:block;text-align:center">Ver mis compras</a>
        <p class="pie"><a href="{{ route('catalogo.index') }}">Volver a la cartelera</a></p>
    </div>

    <p class="pie" id="reintentar" hidden><a id="enlaceReintentar" href="{{ route('pagos.pagar') }}">Intentar pagar de nuevo</a></p>
@endsection

@push('scripts')
<script>
    const alerta = document.getElementById('alerta');
    const pagoId = new URLSearchParams(location.search).get('pago');

    async function confirmar() {
        if (!pagoId || !Auth.obtenerToken()) {
            location.href = '/login?tipo=error&mensaje=' + encodeURIComponent('No se encontró el pago a confirmar.');
            return;
        }

        // Paso 3: cobrar. Si se recarga la página, el servidor devuelve el mismo pago (no cobra dos veces).
        const respuesta = await Auth.api(`/pagos/${pagoId}/confirmar`, { conToken: true });
        document.getElementById('cargando').hidden = true;

        if (respuesta.ok) {
            const p = respuesta.data;
            document.getElementById('monto').textContent = `${p.moneda} ${Number(p.monto).toFixed(2)}`;
            document.getElementById('referencia').textContent = p.referencia;
            document.getElementById('exito').hidden = false;
            mostrarCompra(p.reserva_id);
            return;
        }

        Auth.mostrar(alerta, Auth.mensajeDeError(respuesta));
        await mostrarReintento();
    }

    // Evento, fecha y lugares comprados (si falla, la página sigue mostrando el pago)
    async function mostrarCompra(reservaId) {
        const reserva = await Auth.api(`/reservas/${reservaId}`, { metodo: 'GET', conToken: true });
        if (!reserva.ok) return;

        const { evento, lugares } = reserva.data;
        const poner = (fila, id, texto) => {
            document.getElementById(id).textContent = texto;
            document.getElementById(fila).hidden = !texto;
        };

        poner('filaEvento', 'evento', evento.nombre);
        const fecha = new Date(evento.fecha_evento);
        poner('filaFecha', 'fecha', Number.isNaN(fecha.getTime()) ? '' : new Intl.DateTimeFormat('es-GT', {
            weekday: 'short', day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit',
            timeZone: 'America/Guatemala',
        }).format(fecha));

        // "Platea · A5, A6" para numeradas; "General · 2 lugares" para generales
        const porZona = new Map();
        lugares.forEach((l) => {
            if (!porZona.has(l.localidad)) porZona.set(l.localidad, []);
            porZona.get(l.localidad).push(l);
        });
        const texto = [...porZona].map(([zona, items]) => items[0].numero === null
            ? `${zona} · ${items.length} ${items.length === 1 ? 'lugar' : 'lugares'}`
            : `${zona} · ${items.map((l) => `${l.fila}${l.numero}`).join(', ')}`);
        const contenedor = document.getElementById('lugares');
        contenedor.replaceChildren(...texto.map((linea) => Object.assign(document.createElement('span'), { textContent: linea })));
        document.getElementById('filaLugares').hidden = texto.length === 0;
    }

    // Para reintentar hace falta el número de reserva: se consulta el pago
    async function mostrarReintento() {
        const pago = await Auth.api(`/pagos/${pagoId}`, { metodo: 'GET', conToken: true });
        if (pago.ok) {
            document.getElementById('enlaceReintentar').href = `/pagar?reserva=${pago.data.reserva_id}`;
        }
        document.getElementById('reintentar').hidden = false;
    }

    confirmar();
</script>
@endpush
