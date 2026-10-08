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
