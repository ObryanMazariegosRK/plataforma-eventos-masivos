@extends('layouts.autenticacion')

@section('titulo', 'Iniciar sesión')
@section('eslogan', 'Bienvenido de vuelta.')

@section('contenido')
    <h1>Iniciar sesión</h1>
    <p class="subtitulo">Ingresa con tu correo y contraseña.</p>

    <div id="alerta" class="alerta" role="alert"></div>

    <form id="formLogin" novalidate>
        <div class="campo">
            <label for="email">Correo electrónico</label>
            <input type="email" id="email" placeholder="tucorreo@ejemplo.com" autocomplete="email" required>
        </div>

        <div class="campo">
            <label for="password">Contraseña</label>
            <div class="password">
                <input type="password" id="password" placeholder="Tu contraseña" autocomplete="current-password" required>
                <button type="button">Mostrar</button>
            </div>
        </div>

        <div class="opciones">
            <label class="check"><input type="checkbox" id="recordar"> Recordarme 30 días</label>
            <a href="{{ route('olvide-password') }}">¿Olvidaste tu contraseña?</a>
        </div>

        <button type="submit" id="btnIngresar" class="boton">Ingresar</button>
    </form>

    <p class="pie">¿No tienes cuenta? <a href="{{ route('registro') }}">Regístrate</a></p>
@endsection

@push('scripts')
<script>
    // Si ya hay sesión, directo al perfil
    if (Auth.obtenerToken()) window.location.href = '/perfil';

    Auth.activarVerPassword();

    const alerta = document.getElementById('alerta');
    const boton = document.getElementById('btnIngresar');

    // Mensaje que deja otra página: /login?mensaje=...&tipo=exito|aviso|error
    const parametros = new URLSearchParams(location.search);
    if (parametros.get('mensaje')) Auth.mostrar(alerta, parametros.get('mensaje'), parametros.get('tipo') || 'exito');

    document.getElementById('formLogin').addEventListener('submit', async (e) => {
        e.preventDefault();   // evita que el formulario recargue la página
        Auth.ocultar(alerta);
        Auth.cargando(boton, true, 'Ingresando...');

        const email = document.getElementById('email').value.trim();
        const respuesta = await Auth.api('/auth/login', {
            cuerpo: {
                email,
                password: document.getElementById('password').value,
                recordar: document.getElementById('recordar').checked,
            },
        });

        Auth.cargando(boton, false);

        if (respuesta.ok) {
            Auth.guardarToken(respuesta.data.token);
            window.location.href = '/perfil';
            return;
        }

        // Cuenta sin verificar → lo mandamos a ingresar el código
        if (respuesta.data.error === 'correo_no_verificado') {
            Auth.mostrar(alerta, 'Tu correo aún no está verificado. Te llevamos a verificarlo...', 'aviso');
            setTimeout(() => window.location.href = `/verificar?email=${encodeURIComponent(email)}`, 1200);
            return;
        }

        Auth.mostrar(alerta, Auth.mensajeDeError(respuesta));
    });
</script>
@endpush
