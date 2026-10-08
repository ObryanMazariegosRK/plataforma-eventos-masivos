@extends('layouts.autenticacion')

@section('titulo', 'Mi perfil')
@section('eslogan', 'Sesión iniciada.')

@section('contenido')
    <div id="alerta" class="alerta" role="alert"></div>

    {{-- Se llena con JavaScript cuando responde GET /api/auth/perfil --}}
    <div id="perfil" hidden>
        <div class="perfil__avatar" id="avatar"></div>
        <h1 id="nombreCompleto"></h1>
        <p class="subtitulo">Estos datos vienen de <code>GET /api/auth/perfil</code> usando tu token.</p>

        <ul class="datos">
            <li><span>Correo</span><span id="email"></span></li>
            <li><span>Teléfono</span><span id="telefono"></span></li>
            <li><span>Rol</span><span><span class="etiqueta" id="rol"></span></span></li>
            <li><span>Correo verificado</span><span><span class="etiqueta etiqueta--ok" id="verificado"></span></span></li>
        </ul>

        <p class="ayuda">Tu token (se guarda en el navegador):</p>
        <div class="token" id="token"></div>

        {{-- <details> se abre/cierra solo, sin JavaScript --}}
        <details class="seccion">
            <summary>Cambiar contraseña</summary>

            <div id="alertaPassword" class="alerta" role="alert"></div>

            <form id="formPassword" novalidate>
                <div class="campo">
                    <label for="passwordActual">Contraseña actual</label>
                    <div class="password">
                        <input type="password" id="passwordActual" autocomplete="current-password" required>
                        <button type="button">Mostrar</button>
                    </div>
                </div>
                <div class="campo">
                    <label for="passwordNueva">Nueva contraseña</label>
                    <div class="password">
                        <input type="password" id="passwordNueva" autocomplete="new-password" required>
                        <button type="button">Mostrar</button>
                    </div>
                    <p class="ayuda">Mínimo 8 caracteres, con mayúsculas, minúsculas, un número y un símbolo.</p>
                </div>
                <div class="campo">
                    <label for="passwordConfirmacion">Confirma la nueva contraseña</label>
                    <div class="password">
                        <input type="password" id="passwordConfirmacion" autocomplete="new-password" required>
                        <button type="button">Mostrar</button>
                    </div>
                </div>
                <button type="submit" id="btnPassword" class="boton">Guardar nueva contraseña</button>
            </form>
        </details>

        <div class="acciones">
            <button type="button" id="btnSalir" class="boton boton--secundario">Cerrar sesión</button>
            <button type="button" id="btnSalirTodos" class="enlace">Cerrar sesión en todos mis dispositivos</button>
        </div>
    </div>

    <p id="cargando" class="subtitulo">Cargando tu perfil...</p>
@endsection

@push('scripts')
<script>
    const alerta = document.getElementById('alerta');

    // Sin token no hay nada que mostrar
    if (!Auth.obtenerToken()) window.location.href = '/login';

    async function cargarPerfil() {
        const respuesta = await Auth.api('/auth/perfil', { metodo: 'GET', conToken: true });
        document.getElementById('cargando').hidden = true;

        // 401 = token inválido, vencido o revocado (Auth.api ya borró el token)
        if (respuesta.status === 401) {
            window.location.href = '/login?tipo=aviso&mensaje=' + encodeURIComponent('Tu sesión expiró. Vuelve a iniciar sesión.');
            return;
        }

        // 403 por cuenta bloqueada (Auth.api ya borró el token)
        if (respuesta.data.error === 'usuario_bloqueado') {
            window.location.href = '/login?tipo=error&mensaje=' + encodeURIComponent(respuesta.data.message);
            return;
        }

        if (!respuesta.ok) {
            Auth.mostrar(alerta, Auth.mensajeDeError(respuesta));
            return;
        }

        const u = respuesta.data;
        document.getElementById('avatar').textContent = (u.nombre[0] + u.apellido[0]).toUpperCase();
        document.getElementById('nombreCompleto').textContent = `${u.nombre} ${u.apellido}`;
        document.getElementById('email').textContent = u.email;
        document.getElementById('telefono').textContent = u.telefono || '—';
        document.getElementById('rol').textContent = u.rol;
        document.getElementById('verificado').textContent = u.verificado ? 'Sí' : 'No';
        document.getElementById('token').textContent = Auth.obtenerToken();
        document.getElementById('perfil').hidden = false;
    }

    document.getElementById('btnSalir').addEventListener('click', async (e) => {
        Auth.cargando(e.target, true, 'Cerrando sesión...');
        await Auth.api('/auth/logout', { conToken: true });   // revoca el token en el servidor
        Auth.borrarToken();                                    // y lo borra del navegador
        window.location.href = '/login?mensaje=' + encodeURIComponent('Cerraste sesión correctamente.');
    });

    // ---- Cambiar contraseña ----
    Auth.activarVerPassword();
    const alertaPassword = document.getElementById('alertaPassword');
    const btnPassword = document.getElementById('btnPassword');

    document.getElementById('formPassword').addEventListener('submit', async (e) => {
        e.preventDefault();
        Auth.ocultar(alertaPassword);
        Auth.cargando(btnPassword, true, 'Guardando...');

        const respuesta = await Auth.api('/auth/cambiar-password', {
            conToken: true,
            cuerpo: {
                password_actual: document.getElementById('passwordActual').value,
                password: document.getElementById('passwordNueva').value,
                password_confirmation: document.getElementById('passwordConfirmacion').value,
            },
        });

        Auth.cargando(btnPassword, false);

        if (respuesta.ok) {
            e.target.reset();   // limpia los tres campos
            Auth.mostrar(alertaPassword, respuesta.data.message, 'exito');
            return;
        }

        Auth.mostrar(alertaPassword, Auth.mensajeDeError(respuesta));
    });

    // ---- Cerrar sesión en todos los dispositivos ----
    document.getElementById('btnSalirTodos').addEventListener('click', async (e) => {
        if (!confirm('¿Cerrar la sesión en TODOS tus dispositivos, incluido este?')) return;

        e.target.disabled = true;
        await Auth.api('/auth/logout-todos', { conToken: true });
        Auth.borrarToken();
        window.location.href = '/login?mensaje=' + encodeURIComponent('Se cerró la sesión en todos tus dispositivos.');
    });

    cargarPerfil();
</script>
@endpush
