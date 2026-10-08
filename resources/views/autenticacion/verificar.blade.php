@extends('layouts.autenticacion')

@section('titulo', 'Verificar correo')
@section('eslogan', 'Un paso más y estás dentro.')

@section('contenido')
    <h1>Verifica tu correo</h1>
    <p class="subtitulo">Escribe el código de 6 dígitos que enviamos a tu correo. Vence en 15 minutos.</p>

    <div id="alerta" class="alerta" role="alert"></div>

    <form id="formVerificar" novalidate>
        <div class="campo">
            <label for="email">Correo electrónico</label>
            <input type="email" id="email" placeholder="tucorreo@ejemplo.com" required>
        </div>

        <div class="campo">
            <label>Código de verificación</label>
        </div>
        <div class="codigo" id="codigo">
            @for ($i = 0; $i < 6; $i++)
                <input type="text" inputmode="numeric" maxlength="1" autocomplete="one-time-code" aria-label="Dígito {{ $i + 1 }}">
            @endfor
        </div>

        <button type="submit" id="btnVerificar" class="boton">Verificar</button>
    </form>

    <p class="pie">
        ¿No te llegó o ya venció?
        <button type="button" id="btnReenviar" class="enlace">Reenviar código</button>
    </p>
    <p class="pie"><a href="{{ route('login') }}">Volver a iniciar sesión</a></p>
@endsection

@push('scripts')
<script>
    const alerta = document.getElementById('alerta');
    const inputEmail = document.getElementById('email');
    const codigo = Auth.casillasCodigo(document.getElementById('codigo'));
    const btnVerificar = document.getElementById('btnVerificar');
    const btnReenviar = document.getElementById('btnReenviar');

    // El correo llega en la URL desde el registro o el login: /verificar?email=...
    const emailUrl = new URLSearchParams(location.search).get('email');
    if (emailUrl) {
        inputEmail.value = emailUrl;
        inputEmail.readOnly = true;
        codigo.enfocar();
    } else {
        inputEmail.focus();
    }

    // ---- Verificar ----
    document.getElementById('formVerificar').addEventListener('submit', async (e) => {
        e.preventDefault();
        Auth.ocultar(alerta);

        if (codigo.valor().length !== 6) {
            Auth.mostrar(alerta, 'Escribe los 6 dígitos del código.');
            return;
        }

        Auth.cargando(btnVerificar, true, 'Verificando...');
        const respuesta = await Auth.api('/auth/verificar-correo', {
            cuerpo: { email: inputEmail.value.trim(), codigo: codigo.valor() },
        });

        if (respuesta.ok) {
            Auth.guardarToken(respuesta.data.token);   // la verificación ya inicia sesión
            Auth.mostrar(alerta, '¡Correo verificado! Entrando...', 'exito');
            setTimeout(() => window.location.href = '/perfil', 1000);
            return;
        }

        Auth.cargando(btnVerificar, false);

        // Ya estaba verificado → que inicie sesión normalmente
        if (respuesta.data.error === 'correo_ya_verificado') {
            Auth.mostrar(alerta, 'Este correo ya está verificado. Inicia sesión.', 'aviso');
            setTimeout(() => window.location.href = '/login', 1500);
            return;
        }

        Auth.mostrar(alerta, Auth.mensajeDeError(respuesta));
        codigo.limpiar();
        codigo.enfocar();
    });

    // ---- Reenviar (con espera de 60 s entre envíos) ----
    btnReenviar.addEventListener('click', async () => {
        Auth.ocultar(alerta);
        if (!inputEmail.value.trim()) {
            Auth.mostrar(alerta, 'Escribe tu correo primero.');
            return;
        }

        btnReenviar.disabled = true;
        const respuesta = await Auth.api('/auth/reenviar-codigo', {
            cuerpo: { email: inputEmail.value.trim() },
        });

        if (!respuesta.ok) {
            btnReenviar.disabled = false;
            Auth.mostrar(alerta, Auth.mensajeDeError(respuesta));
            return;
        }

        Auth.mostrar(alerta, respuesta.data.message, 'exito');

        let segundos = 60;
        const intervalo = setInterval(() => {
            btnReenviar.textContent = `Reenviar código (${--segundos}s)`;
            if (segundos <= 0) {
                clearInterval(intervalo);
                btnReenviar.textContent = 'Reenviar código';
                btnReenviar.disabled = false;
            }
        }, 1000);
    });
</script>
@endpush
