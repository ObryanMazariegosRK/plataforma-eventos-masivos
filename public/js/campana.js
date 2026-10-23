'use strict';

// Campana de notificaciones (tienda y panel): un solo botón que activa o quita las notificaciones
// push en ESTE dispositivo (notificaciones.js → public/sw.js). No hay bandeja ni contador: los avisos
// llegan como notificación del sistema (y por correo). Campana encendida = activadas; tachada = no.
// Al tocarla aparece una nota breve con el resultado o con cómo desbloquearlas.
//   Tienda: catalogo.js avisa con "tienda:sesion" y la campana se coloca junto al menú de la cuenta.
//   Panel: se monta en el elemento [data-campana] de la barra superior.

(() => {
    const ICONO_ACTIVA = '<svg viewBox="0 0 24 24" aria-hidden="true"><path class="campana__relleno" d="M18 8a6 6 0 0 0-12 0c0 7-3 8-3 10h18c0-2-3-3-3-10"/><path d="M10 21a2 2 0 0 0 4 0"/></svg>';
    const ICONO_APAGADA = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8.7 3.6A6 6 0 0 1 18 8c0 3.4.7 5.3 1.5 6.6M17 18H3c0-2 3-3 3-10 0-.5.06-1 .17-1.46"/><path d="M10 21a2 2 0 0 0 4 0"/><path d="M3 3l18 18"/></svg>';

    const TEXTOS = {
        activo: 'Notificaciones activadas en este dispositivo',
        inactivo: 'Activar notificaciones en este dispositivo',
        bloqueado: 'Notificaciones bloqueadas en este navegador',
        no_soportado: 'Notificaciones no disponibles aquí',
        sin_llave: 'Notificaciones no disponibles aquí',
    };

    function montar(contenedor, { esTienda }) {
        if (!window.Avisos || contenedor.querySelector('.campana__boton')) return;
        contenedor.classList.add('campana');

        const boton = document.createElement('button');
        boton.type = 'button';
        boton.className = 'campana__boton';
        const nota = document.createElement('p');
        nota.className = 'campana__nota';
        nota.setAttribute('role', 'status');
        nota.hidden = true;
        contenedor.append(boton, nota);

        let estado = 'inactivo';
        let ocupada = false;
        let temporizador = null;

        function mostrarNota(texto, segundos = 4) {
            clearTimeout(temporizador);
            nota.textContent = texto;
            nota.hidden = false;
            temporizador = setTimeout(() => { nota.hidden = true; }, segundos * 1000);
        }

        async function pintar() {
            estado = await Avisos.push.estado();
            const activa = estado === 'activo';
            boton.innerHTML = activa ? ICONO_ACTIVA : ICONO_APAGADA;
            boton.classList.toggle('es-activa', activa);
            boton.classList.toggle('es-bloqueada', !activa && estado !== 'inactivo');
            boton.setAttribute('aria-pressed', String(activa));
            boton.setAttribute('aria-label', TEXTOS[estado]);
            boton.title = TEXTOS[estado];
        }

        boton.addEventListener('click', async () => {
            if (ocupada) return;
            if (estado === 'no_soportado') return mostrarNota(Avisos.push.porQueNoSoportado(), 8);
            if (estado === 'sin_llave') return mostrarNota('Las notificaciones aún no están configuradas en el servidor.', 6);

            ocupada = true;
            boton.classList.add('esta-ocupada');
            try {
                if (estado === 'activo') {
                    await Avisos.push.desactivar();
                    mostrarNota('Listo: ya no recibirás notificaciones en este dispositivo.');
                } else {
                    await Avisos.push.activar();
                    mostrarNota(esTienda
                        ? 'Listo: te avisaremos aquí de tus boletos, recordatorios y cambios en tus funciones.'
                        : 'Listo: te avisaremos aquí de reembolsos por revisar y eventos que se llenan.');
                }
            } catch (error) {
                mostrarNota(error.message, 10);
            } finally {
                ocupada = false;
                boton.classList.remove('esta-ocupada');
                await pintar();
            }
        });

        document.addEventListener('click', (e) => {
            if (!nota.hidden && !contenedor.contains(e.target)) nota.hidden = true;
        });
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') nota.hidden = true;
        });

        pintar();
        Avisos.push.sincronizar().catch(() => {});
    }

    // Tienda: después de validar la sesión
    document.addEventListener('tienda:sesion', (evento) => {
        const cuenta = evento.detail?.cuenta;
        if (!cuenta || document.querySelector('.campana')) return;
        const contenedor = document.createElement('div');
        cuenta.before(contenedor);
        montar(contenedor, { esTienda: true });
    });

    // Panel: lugar fijo en la barra superior
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('[data-campana]').forEach((contenedor) => montar(contenedor, { esTienda: false }));
    });
})();
