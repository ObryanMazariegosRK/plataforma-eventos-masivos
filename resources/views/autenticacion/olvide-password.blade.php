@extends('layouts.autenticacion')

@section('titulo', 'Recuperar contraseña')
@section('eslogan', 'Te ayudamos a recuperar tu cuenta.')

@section('contenido')
    <h1>¿Olvidaste tu contraseña?</h1>
    <p class="subtitulo">Escribe tu correo y te enviaremos un código de 6 dígitos para crear una nueva.</p>

    <div id="alerta" class="alerta" role="alert"></div>

    <form id="formOlvide" novalidate>
        <div class="campo">
            <label for="email">Correo electrónico</label>
            <input type="email" id="email" placeholder="tucorreo@ejemplo.com" autocomplete="email" required>
        </div>

        <button type="submit" id="btnEnviar" class="boton">Enviar código</button>
    </form>

    <p class="pie"><a href="{{ route('login') }}">Volver a iniciar sesión</a></p>
@endsection

@push('scripts')
<script>
    const alerta = document.getElementById('alerta');
    const boton = document.getElementById('btnEnviar');

    document.getElementById('formOlvide').addEventListener('submit', async (e) => {
        e.preventDefault();
        Auth.ocultar(alerta);
        Auth.cargando(boton, true, 'Enviando...');

        const email = document.getElementById('email').value.trim();
        const respuesta = await Auth.api('/auth/olvide-password', { cuerpo: { email } });

        if (respuesta.ok) {
            // La API responde lo mismo exista o no el correo; seguimos al siguiente paso igual.
            Auth.mostrar(alerta, respuesta.data.message, 'exito');
            setTimeout(() => window.location.href = `/restablecer-password?email=${encodeURIComponent(email)}`, 1500);
            return;
        }

        Auth.cargando(boton, false);
        Auth.mostrar(alerta, Auth.mensajeDeError(respuesta));
    });
</script>
@endpush
