'use strict';

// Service worker de Soirée: solo notificaciones push (no guarda nada en caché).
// El servidor (EnviarNotificacionCorreoJob → EnviadorPushWeb) manda {title, body, icon, data: {url}}.

self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', (evento) => evento.waitUntil(self.clients.claim()));

self.addEventListener('push', (evento) => {
    let datos = {};
    try {
        datos = evento.data ? evento.data.json() : {};
    } catch {
        datos = { body: evento.data ? evento.data.text() : '' };
    }

    evento.waitUntil(self.registration.showNotification(datos.title || 'Soirée', {
        body: datos.body || '',
        icon: datos.icon || '/img/notificacion.png',
        badge: datos.badge || '/img/notificacion-insignia.png',
        data: datos.data || {},
        lang: 'es',
    }));
});

// Al tocarla: si Soirée ya está abierta en una pestaña, se usa esa; si no, se abre una nueva
self.addEventListener('notificationclick', (evento) => {
    evento.notification.close();
    const destino = new URL((evento.notification.data && evento.notification.data.url) || '/', self.location.origin).href;

    evento.waitUntil((async () => {
        const pestanas = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
        const abierta = pestanas.find((pestana) => new URL(pestana.url).origin === self.location.origin);
        if (abierta) {
            await abierta.focus();
            return abierta.navigate(destino).catch(() => self.clients.openWindow(destino));
        }
        return self.clients.openWindow(destino);
    })());
});
