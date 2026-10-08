@extends('layouts.autenticacion')

@section('titulo', 'Nueva contraseña')
@section('eslogan', 'Crea una contraseña nueva y segura.')

@section('contenido')
    <h1>Crea tu nueva contraseña</h1>
    <p class="subtitulo">Escribe el código que enviamos a tu correo (vence en 15 minutos) y tu nueva contraseña.</p>

    <div id="alerta" class="alerta" role="alert"></div>

    <form id="formRestablecer" novalidate>
        <div class="campo">
            <label for="email">Correo electrónico</label>
            <input type="email" id="email" placeholder="tucorreo@ejemplo.com" required>
        </div>

        <div class="campo">
            <label>Código de recuperación</label>
        </div>
        <div class="codigo" id="codigo">
            @for ($i = 0; $i < 6; $i++)
                <input type="text" inputmode="numeric" maxlength="1" autocomplete="one-time-code" aria-label="Dígito {{ $i + 1 }}">
            @endfor
        </div>

        <div class="campo">
            <label for="password">Nueva contraseña</label>
            <div class="password">
                <input type="password" id="password" placeholder="Nueva contraseña" autocomplete="new-password" required>
                <button type="button">Mostrar</button>
            </div>
            <p class="ayuda">Mínimo 8 caracteres, con mayúsculas, minúsculas, un número y un símbolo.</p>
        </div>

        <div class="campo">
            <label for="confirmacion">Confirma la contraseña</label>
            <div class="password">
                <input type="password" id="confirmacion" placeholder="Repite la contraseña" autocomplete="new-password" required>
                <button type="button">Mostrar</button>
            </div>
        </div>

        <button type="submit" id="btnGuardar" class="boton">Guardar contraseña</button>
    </form>

    <p class="pie">¿No te llegó el código? <a href="{{ route('olvide-password') }}">Pedir otro</a></p>
@endsection

@push('scripts')
<script>
    Auth.activarVerPassword();

    const alerta = document.getElementById('alerta');
    const inputEmail = document.getElementById('email');
    const codigo = Auth.casillasCodigo(document.getElementById('codigo'));
    const boton = document.getElementById('btnGuardar');

    const emailUrl = new URLSearchParams(location.search).get('email');
    if (emailUrl) {
        inputEmail.value = emailUrl;
        inputEmail.readOnly = true;
        codigo.enfocar();
    } else {
        inputEmail.focus();
    }

    document.getElementById('formRestablecer').addEventListener('submit', async (e) => {
        e.preventDefault();
        Auth.ocultar(alerta);

        if (codigo.valor().length !== 6) {
            Auth.mostrar(alerta, 'Escribe los 6 dígitos del código.');
            return;
        }

        Auth.cargando(boton, true, 'Guardando...');
        const respuesta = await Auth.api('/auth/restablecer-password', {
            cuerpo: {
                email: inputEmail.value.trim(),
                codigo: codigo.valor(),
                password: document.getElementById('password').value,
                password_confirmation: document.getElementById('confirmacion').value,
            },
        });

        if (respuesta.ok) {
            Auth.borrarToken();   // el servidor cerró todas las sesiones; la local tampoco sirve
            window.location.href = '/login?mensaje=' + encodeURIComponent(respuesta.data.message);
            return;
        }

        Auth.cargando(boton, false);
        Auth.mostrar(alerta, Auth.mensajeDeError(respuesta));

        // Código agotado → hay que pedir uno nuevo
        if (respuesta.data.error === 'intentos_agotados') {
            codigo.limpiar();
        }
    });
</script>
@endpush
