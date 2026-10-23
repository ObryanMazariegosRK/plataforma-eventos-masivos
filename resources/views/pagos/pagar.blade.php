@extends('layouts.autenticacion')

@section('titulo', 'Pagar reserva')
@section('eslogan', 'Último paso para asegurar tus boletos.')

@section('contenido')
    <h1>Pagar reserva</h1>
    <p class="subtitulo">Elige cómo pagar. Serás enviado a la pasarela para aprobar el pago.</p>

    <div id="alerta" class="alerta" role="alert"></div>

    {{-- Con ?reserva=ID y una sola pasarela se va directo a pagarla; el formulario queda para errores o varias pasarelas --}}
    <p id="preparando" class="subtitulo" hidden>Preparando tu pago…</p>

    <form id="formPagar" novalidate>
        <div class="campo">
            <label for="reserva">Número de reserva</label>
            <input type="number" id="reserva" min="1" required>
        </div>

        <div class="campo">
            <label for="pasarela">Pasarela de pago</label>
            <select id="pasarela" required></select>
        </div>

        <button type="submit" id="btnPagar" class="boton">Ir a pagar</button>
    </form>

    <p class="pie"><a href="{{ route('perfil') }}">Volver a mi perfil</a></p>
@endsection

@push('scripts')
<script>
    if (!Auth.obtenerToken()) {
        location.href = '/login?tipo=aviso&mensaje=' + encodeURIComponent('Inicia sesión para pagar tu reserva.');
    }

    const alerta = document.getElementById('alerta');
    const boton = document.getElementById('btnPagar');
    const selectPasarela = document.getElementById('pasarela');

    // /pagar?reserva=12 → se llena solo
    document.getElementById('reserva').value = new URLSearchParams(location.search).get('reserva') || '';

    async function cargarPasarelas() {
        const respuesta = await Auth.api('/pasarelas', { metodo: 'GET' });
        if (!respuesta.ok || respuesta.data.length === 0) {
            Auth.mostrar(alerta, 'No hay pasarelas de pago disponibles.');
            boton.disabled = true;
            return;
        }
        respuesta.data.forEach((p) => selectPasarela.add(new Option(p.nombre, p.codigo)));
    }

    document.getElementById('formPagar').addEventListener('submit', async (e) => {
        e.preventDefault();
        Auth.ocultar(alerta);
        Auth.cargando(boton, true, 'Preparando el pago...');

        const respuesta = await Auth.api('/pagos', {
            conToken: true,
            cuerpo: {
                reserva_id: Number(document.getElementById('reserva').value),
                pasarela: selectPasarela.value,
            },
        });

        if (respuesta.ok) {
            // Paso 2: el navegador va a la página de la pasarela
            location.href = respuesta.data.url_aprobacion;
            return;
        }

        Auth.cargando(boton, false);
        Auth.mostrar(alerta, Auth.mensajeDeError(respuesta));
    });

    // Desde "Intentar de nuevo" (o tras reservar): directo a la pasarela si solo hay una
    const reservaInicial = new URLSearchParams(location.search).get('reserva');
    const formulario = document.getElementById('formPagar');
    const preparando = document.getElementById('preparando');

    (async () => {
        if (reservaInicial && Auth.obtenerToken()) {
            formulario.hidden = true;
            preparando.hidden = false;

            const pasarelas = await Auth.api('/pasarelas', { metodo: 'GET' });
            if (pasarelas.ok && pasarelas.data.length === 1) {
                const error = await Auth.irAPagar(reservaInicial);
                if (!error) return;   // ya va camino a la pasarela
                Auth.mostrar(alerta, Auth.mensajeDeError(error));
            }

            preparando.hidden = true;
            formulario.hidden = false;
        }
        cargarPasarelas();
    })();
</script>
@endpush
