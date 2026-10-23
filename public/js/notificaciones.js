'use strict';

// Avisos de Soirée para quien inició sesión, compartido por la campana de la tienda (campana.js)
// y la sección Notificaciones del panel (admin-notificaciones.js):
//   Avisos.listar(pagina) / leer(id) / leerTodas()   → /api/notificaciones
//   Avisos.push.estado() / activar() / desactivar()   → push en ESTE dispositivo (public/sw.js)
// La llave pública VAPID viene en <meta name="vapid-public-key">. Como en la tienda, se explican
// los casos difíciles: permiso bloqueado, Brave y iPhone (solo con Soirée en la pantalla de inicio).

window.Avisos = (() => {
    const token = () => localStorage.getItem('auth_token');

    async function pedir(ruta, opciones = {}) {
        const respuesta = await fetch(`/api${ruta}`, {
            ...opciones,
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                Authorization: `Bearer ${token()}`,
            },
        });
        const datos = await respuesta.json().catch(() => ({}));
        if (!respuesta.ok) {
            const error = new Error(datos.errors ? Object.values(datos.errors).flat()[0] : (datos.message || 'No se pudieron consultar tus avisos.'));
            error.status = respuesta.status;
            throw error;
        }
        return datos;
    }

    const listar = async (pagina = 1, porPagina = 10) => (await pedir(`/notificaciones?pagina=${pagina}&por_pagina=${porPagina}`)).data;
    const leer = async (id) => (await pedir(`/notificaciones/${id}/leer`, { method: 'POST' })).data.no_leidas;
    const leerTodas = async () => (await pedir('/notificaciones/leer-todas', { method: 'POST' })).data.no_leidas;

    // Ícono discreto por tipo de aviso
    const ICONOS = {
        compra_confirmada: '✦', recordatorio: '◷', cambio_fecha: '↻', evento_cancelado: '✕',
        reembolso_procesado: '↺', reembolso_rechazado: '✕', reembolso_solicitado: '↺',
        devolucion_pendiente: '!', evento_casi_lleno: '◐', evento_agotado: '●',
    };
    const icono = (tipo) => ICONOS[tipo] || '•';

    // "hace 5 min", "ayer", "12 oct"
    const relativo = new Intl.RelativeTimeFormat('es', { numeric: 'auto' });
    const diaCorto = new Intl.DateTimeFormat('es-GT', { timeZone: 'America/Guatemala', day: 'numeric', month: 'short' });
    function hace(fechaIso) {
        const segundos = (new Date(fechaIso) - Date.now()) / 1000;
        const abs = Math.abs(segundos);
        if (abs < 60) return 'ahora';
        if (abs < 3600) return relativo.format(Math.round(segundos / 60), 'minute');
        if (abs < 86400) return relativo.format(Math.round(segundos / 3600), 'hour');
        if (abs < 6 * 86400) return relativo.format(Math.round(segundos / 86400), 'day');
        return diaCorto.format(new Date(fechaIso));
    }

    // ---------------- Push en este dispositivo ----------------
    const BLOQUEADAS = 'Tu navegador tiene bloqueadas las notificaciones de Soirée. Toca el candado 🔒 junto a la dirección, '
        + 'cambia "Notificaciones" a "Permitir" y recarga la página.';
    const esIPhone = () => /iPhone|iPad|iPod/.test(navigator.userAgent);
    const soportado = () => 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;

    function llaveVapid() {
        const base64 = document.querySelector('meta[name="vapid-public-key"]')?.content || '';
        const relleno = '='.repeat((4 - (base64.length % 4)) % 4);
        const binario = atob((base64 + relleno).replace(/-/g, '+').replace(/_/g, '/'));
        return Uint8Array.from([...binario].map((c) => c.charCodeAt(0)));
    }

    async function suscripcionActual() {
        if (!soportado()) return null;
        const registro = await navigator.serviceWorker.getRegistration('/');
        return registro ? registro.pushManager.getSubscription() : null;
    }

    /** 'activo' | 'inactivo' | 'bloqueado' | 'no_soportado' | 'sin_llave' */
    async function estado() {
        if (!soportado()) return 'no_soportado';
        if (!document.querySelector('meta[name="vapid-public-key"]')?.content) return 'sin_llave';
        if (Notification.permission === 'denied') return 'bloqueado';
        return (await suscripcionActual()) ? 'activo' : 'inactivo';
    }

    function porQueNoSoportado() {
        if (!window.isSecureContext) return 'Las notificaciones solo funcionan con https (o en localhost).';
        return esIPhone()
            ? 'En iPhone, primero agrega Soirée a tu pantalla de inicio (Compartir → "Agregar a inicio") y actívalas desde ahí.'
            : 'Tu navegador no admite notificaciones.';
    }

    async function activar() {
        if (!soportado()) throw new Error(porQueNoSoportado());
        if (Notification.permission === 'denied') throw new Error(BLOQUEADAS);

        const permiso = await Notification.requestPermission();
        if (permiso === 'denied') throw new Error(BLOQUEADAS);
        if (permiso !== 'granted') throw new Error('Cerraste el aviso sin elegir. Vuelve a intentarlo y toca "Permitir".');

        await navigator.serviceWorker.register('/sw.js', { scope: '/' });
        const registro = await navigator.serviceWorker.ready;

        let suscripcion;
        try {
            suscripcion = await registro.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: llaveVapid() });
        } catch (error) {
            // Brave bloquea por defecto el servicio de push de Google
            if (/push service error/i.test(error?.message || '')) {
                throw new Error('Tu navegador está bloqueando las notificaciones. En Brave, activa "Usar los servicios de Google para la mensajería push" en brave://settings/privacy, o usa Chrome.');
            }
            throw error;
        }

        await pedir('/push/suscribir', { method: 'POST', body: JSON.stringify(suscripcion) });
    }

    async function desactivar() {
        const suscripcion = await suscripcionActual();
        if (!suscripcion) return;
        await pedir('/push/desuscribir', { method: 'POST', body: JSON.stringify({ endpoint: suscripcion.endpoint }) }).catch(() => {});
        await suscripcion.unsubscribe();
    }

    return { listar, leer, leerTodas, icono, hace, push: { estado, activar, desactivar, porQueNoSoportado } };
})();
