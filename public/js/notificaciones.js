'use strict';

// Notificaciones push de Soirée en ESTE dispositivo (public/sw.js las muestra), usado por la
// campana de la tienda y del panel (campana.js):
//   Avisos.push.estado() / activar() / desactivar() / sincronizar()   → /api/push/*
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
            const error = new Error(datos.errors ? Object.values(datos.errors).flat()[0] : (datos.message || 'No se pudieron configurar las notificaciones.'));
            error.status = respuesta.status;
            throw error;
        }
        return datos;
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

    // El dispositivo ya estaba suscrito (quizá con otra cuenta): se vuelve a registrar a nombre de
    // quien inició sesión, así sus avisos llegan aquí y los de la cuenta anterior ya no
    async function sincronizar() {
        if (!soportado() || Notification.permission !== 'granted') return;
        const suscripcion = await suscripcionActual();
        if (suscripcion) await pedir('/push/suscribir', { method: 'POST', body: JSON.stringify(suscripcion) });
    }

    async function desactivar() {
        const suscripcion = await suscripcionActual();
        if (!suscripcion) return;
        await pedir('/push/desuscribir', { method: 'POST', body: JSON.stringify({ endpoint: suscripcion.endpoint }) }).catch(() => {});
        await suscripcion.unsubscribe();
    }

    return { push: { estado, activar, desactivar, sincronizar, porQueNoSoportado } };
})();
