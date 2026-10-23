{{-- Login de la tienda (/login) y acceso del personal (/admin/login, $personal = true).
     El personal entra solo con correo y contraseña: sin Google ni registro. --}}
@php($personal = $personal ?? false)
@extends('layouts.autenticacion')

@section('titulo', $personal ? 'Acceso del personal' : 'Iniciar sesión')
@section('eslogan', 'Bienvenido de vuelta.')

@section('contenido')
    @if ($personal)
        <p class="etiqueta-acceso">Panel de administración</p>
        <h1>Acceso del personal</h1>
        <p class="subtitulo">Solo administradores y operadores de {{ config('marca.nombre') }}.</p>
    @else
        <h1>Iniciar sesión</h1>
        <p class="subtitulo">Ingresa con tu correo y contraseña.</p>
    @endif

    <div id="alerta" class="alerta" role="alert"></div>

    <form id="formLogin" novalidate>
        <div class="campo">
            <label for="email">Correo electrónico</label>
            <input type="email" id="email" placeholder="{{ $personal ? 'tucorreo@soiree.com' : 'tucorreo@ejemplo.com' }}" autocomplete="email" required>
        </div>

        <div class="campo">
            <label for="password">Contraseña</label>
            <div class="password">
                <input type="password" id="password" placeholder="Tu contraseña" autocomplete="current-password" required>
                <button type="button">Mostrar</button>
            </div>
        </div>

        <div class="opciones">
            <a href="{{ route($personal ? 'admin.olvide-password' : 'olvide-password') }}">¿Olvidaste tu contraseña?</a>
        </div>

        <button type="submit" id="btnIngresar" class="boton">Ingresar</button>
    </form>

    @if ($personal)
        <p class="pie">¿Eres cliente? <a href="{{ route('login') }}">Inicia sesión en la tienda</a></p>
    @else
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

        <p class="pie">¿No tienes cuenta? <a href="{{ route('registro') }}">Regístrate</a></p>
    @endif
@endsection

@push('scripts')
<script>
    const PERSONAL = @json($personal);

    // Si ya hay una sesión de esta puerta, directo a su inicio; si el token venció, se queda aquí
    if (Auth.obtenerToken()) Auth.entrarConSesion(PERSONAL);

    Auth.activarVerPassword();

    const alerta = document.getElementById('alerta');
    const boton = document.getElementById('btnIngresar');
    const inputEmail = document.getElementById('email');

    // Mensaje que deja otra página: ?mensaje=...&tipo=exito|aviso|error  (y ?email= para no reescribirlo)
    const parametros = new URLSearchParams(location.search);
    if (parametros.get('mensaje')) Auth.mostrar(alerta, parametros.get('mensaje'), parametros.get('tipo') || 'exito');
    if (parametros.get('email')) inputEmail.value = parametros.get('email');

    document.getElementById('formLogin').addEventListener('submit', async (e) => {
        e.preventDefault();   // evita que el formulario recargue la página
        Auth.ocultar(alerta);
        Auth.cargando(boton, true, 'Ingresando...');

        const email = inputEmail.value.trim();
        const respuesta = await Auth.api(PERSONAL ? '/auth/login-personal' : '/auth/login', {
            cuerpo: { email, password: document.getElementById('password').value },
        });

        Auth.cargando(boton, false);

        if (respuesta.ok) {
            if (!Auth.procesarInicioSesion(respuesta.data)) {
                Auth.mostrar(alerta, 'No se pudo identificar el rol de tu cuenta. Contacta al administrador.', 'error');
            }
            return;
        }

        // Cuenta sin verificar → lo mandamos a ingresar el código
        if (respuesta.data.error === 'correo_no_verificado') {
            Auth.mostrar(alerta, 'Tu correo aún no está verificado. Te llevamos a verificarlo...', 'aviso');
            setTimeout(() => window.location.href = `/verificar?email=${encodeURIComponent(email)}`, 1200);
            return;
        }

        // Puerta equivocada: se lleva a la correcta con el correo ya escrito
        const otraPuerta = { acceso_personal: '/admin/login', sin_acceso_panel: '/login' }[respuesta.data.error];
        if (otraPuerta) {
            Auth.mostrar(alerta, Auth.mensajeDeError(respuesta) + ' Te llevamos ahí...', 'aviso');
            setTimeout(() => window.location.href = `${otraPuerta}?email=${encodeURIComponent(email)}`, 1800);
            return;
        }

        Auth.mostrar(alerta, Auth.mensajeDeError(respuesta));
    });
</script>
@endpush
