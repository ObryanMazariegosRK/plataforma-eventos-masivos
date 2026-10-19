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
            <li><span>Pago</span><span id="pagoId"></span></li>
            <li><span>Reserva</span><span id="reservaId"></span></li>
            <li><span>Monto</span><span id="monto"></span></li>
            <li><span>Referencia</span><span id="referencia"></span></li>
        </ul>
        <a href="{{ route('perfil') }}" class="boton" style="display:block;text-align:center">Ir a mi perfil</a>
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
            document.getElementById('pagoId').textContent = '#' + p.id;
            document.getElementById('reservaId').textContent = '#' + p.reserva_id;
            document.getElementById('monto').textContent = `${p.moneda} ${Number(p.monto).toFixed(2)}`;
            document.getElementById('referencia').textContent = p.referencia;
            document.getElementById('exito').hidden = false;
            return;
        }

        Auth.mostrar(alerta, Auth.mensajeDeError(respuesta));
        await mostrarReintento();
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
