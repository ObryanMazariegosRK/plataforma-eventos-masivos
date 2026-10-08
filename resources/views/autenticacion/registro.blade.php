@extends('layouts.autenticacion')

@section('titulo', 'Crear cuenta')
@section('eslogan', 'Crea tu cuenta y no te pierdas ningún evento.')

@section('contenido')
    <h1>Crear cuenta</h1>
    <p class="subtitulo">Te enviaremos un código a tu correo para verificarla.</p>

    <div id="alerta" class="alerta" role="alert"></div>

    <form id="formRegistro" novalidate>
        <div class="fila">
            <div class="campo">
                <label for="nombre">Nombre</label>
                <input type="text" id="nombre" placeholder="Ana" autocomplete="given-name" required>
            </div>
            <div class="campo">
                <label for="apellido">Apellido</label>
                <input type="text" id="apellido" placeholder="López" autocomplete="family-name" required>
            </div>
        </div>

        <div class="campo">
            <label for="email">Correo electrónico</label>
            <input type="email" id="email" placeholder="tucorreo@ejemplo.com" autocomplete="email" required>
        </div>

        <div class="campo">
            <label for="telefono">Teléfono <span class="opcional">(opcional)</span></label>
            <input type="tel" id="telefono" placeholder="55551234" inputmode="numeric" autocomplete="tel">
        </div>

        <div class="campo">
            <label for="password">Contraseña</label>
            <div class="password">
                <input type="password" id="password" placeholder="Crea una contraseña" autocomplete="new-password" required>
                <button type="button">Mostrar</button>
            </div>
            <p class="ayuda">Mínimo 8 caracteres, con mayúsculas, minúsculas, un número y un símbolo.</p>
        </div>

        <button type="submit" id="btnRegistrar" class="boton">Crear cuenta</button>
    </form>

    <div class="separador"><span>o</span></div>

    <a href="{{ route('auth.google.redirect') }}" class="boton boton--google">
        <svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true">
            <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
            <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/>
            <path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/>
            <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
        </svg>
        Continuar con Google
    </a>

    <p class="pie">¿Ya tienes cuenta? <a href="{{ route('login') }}">Inicia sesión</a></p>
@endsection

@push('scripts')
<script>
    Auth.activarVerPassword();

    const alerta = document.getElementById('alerta');
    const boton = document.getElementById('btnRegistrar');

    document.getElementById('formRegistro').addEventListener('submit', async (e) => {
        e.preventDefault();
        Auth.ocultar(alerta);
        Auth.cargando(boton, true, 'Creando cuenta...');

        const email = document.getElementById('email').value.trim();
        const telefono = document.getElementById('telefono').value.trim();

        const respuesta = await Auth.api('/auth/registro', {
            cuerpo: {
                nombre: document.getElementById('nombre').value.trim(),
                apellido: document.getElementById('apellido').value.trim(),
                email,
                password: document.getElementById('password').value,
                telefono: telefono || null,   // vacío → null (es opcional)
            },
        });

        if (respuesta.ok) {
            Auth.mostrar(alerta, '¡Cuenta creada! Revisa tu correo, te llevamos a verificarla...', 'exito');
            setTimeout(() => window.location.href = `/verificar?email=${encodeURIComponent(email)}`, 1200);
            return;
        }

        Auth.cargando(boton, false);
        Auth.mostrar(alerta, Auth.mensajeDeError(respuesta));
    });
</script>
@endpush
