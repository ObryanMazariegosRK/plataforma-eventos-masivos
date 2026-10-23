'use strict';

// Panel → "Notificaciones" (admin y operador): los avisos de quien inició sesión
// (GET /api/notificaciones vía notificaciones.js), no una copia de Reservas o Pagos:
//   - administradores: solicitudes de reembolso por revisar, devoluciones que la pasarela no hizo
//   - administradores y operadores: eventos casi llenos o agotados
// Leído/no leído se guarda en el servidor (igual en todos los dispositivos). La burbuja del menú
// cuenta los no leídos y se refresca cada minuto. Arriba, el interruptor del push del dispositivo.

document.addEventListener('DOMContentLoaded', () => {
    const panel = document.getElementById('notificaciones-panel');
    if (!panel || !window.Avisos) return;

    const $ = (id) => document.getElementById(id);
    const lista = $('avisos-lista');
    const resumen = $('avisos-resumen');
    const verMas = $('avisos-ver-mas');
    const leerTodas = $('avisos-leer-todas');
    const burbuja = $('so-notif-badge');
    const interruptor = $('avisos-push-interruptor');
    const notaPush = $('avisos-push-nota');
    const fecha = new Intl.DateTimeFormat('es-GT', { timeZone: 'America/Guatemala', dateStyle: 'medium', timeStyle: 'short' });

    const POR_PAGINA = 15;
    let pagina = 0;
    let noLeidas = 0;
    let total = 0;

    const crear = (etiqueta, clase, texto) => {
        const nodo = document.createElement(etiqueta);
        if (clase) nodo.className = clase;
        if (texto !== undefined) nodo.textContent = texto;
        return nodo;
    };

    function pintarConteo(n) {
        noLeidas = n;
        if (burbuja) {
            burbuja.hidden = n === 0;
            burbuja.textContent = n > 99 ? '99+' : String(n);
            burbuja.setAttribute('aria-label', `${n} ${n === 1 ? 'aviso sin leer' : 'avisos sin leer'}`);
        }
        leerTodas.hidden = n === 0;
        resumen.textContent = total === 0
            ? 'Todavía no tienes avisos. Aquí aparecerán las solicitudes de reembolso, las devoluciones pendientes y los eventos que se llenan.'
            : `${total} ${total === 1 ? 'aviso' : 'avisos'}` + (n ? ` · ${n} sin leer` : ' · todo leído');
    }

    // Los enlaces del panel (/admin?seccion=x) cambian de sección sin recargar
    function abrirEnlace(url) {
        const destino = new URL(url, location.origin);
        const seccion = destino.pathname === '/admin' && destino.searchParams.get('seccion');
        const boton = seccion && document.querySelector(`.nav-item[data-section="${seccion}"]`);
        if (boton) boton.click();
        else location.href = destino.href;
    }

    async function marcarLeido(aviso, tarjeta) {
        if (aviso.leida) return;
        aviso.leida = true;
        tarjeta.classList.remove('es-nuevo');
        try {
            pintarConteo(await Avisos.leer(aviso.id));
        } catch {
            // se reintenta en la siguiente carga
        }
    }

    function tarjeta(aviso) {
        const item = crear('li', `aviso aviso--${aviso.tipo || 'general'}${aviso.leida ? '' : ' es-nuevo'}`);
        const icono = crear('span', 'aviso__icono', Avisos.icono(aviso.tipo));
        icono.setAttribute('aria-hidden', 'true');

        const cuerpo = crear('div', 'aviso__cuerpo');
        const cabeza = crear('div', 'aviso__cabeza');
        const cuando = crear('time', 'aviso__hora', Avisos.hace(aviso.fecha));
        cuando.dateTime = aviso.fecha;
        cuando.title = fecha.format(new Date(aviso.fecha));
        cabeza.append(crear('h3', 'aviso__titulo', aviso.titulo), cuando);
        cuerpo.append(cabeza, crear('p', 'aviso__mensaje', aviso.mensaje));

        const acciones = crear('div', 'aviso__acciones');
        if (aviso.url) {
            const abrir = crear('button', 'aviso__boton es-principal', {
                reembolso_solicitado: 'Revisar solicitud',
                devolucion_pendiente: 'Ir a Reembolsos',
                evento_casi_lleno: 'Ver en el catálogo',
                evento_agotado: 'Ver en el catálogo',
            }[aviso.tipo] || 'Abrir');
            abrir.type = 'button';
            abrir.addEventListener('click', async () => {
                await marcarLeido(aviso, item);
                abrirEnlace(aviso.url);
            });
            acciones.append(abrir);
        }
        if (!aviso.leida) {
            const leido = crear('button', 'aviso__boton', 'Marcar como leído');
            leido.type = 'button';
            leido.addEventListener('click', () => {
                marcarLeido(aviso, item);
                leido.remove();
            });
            acciones.append(leido);
        }
        cuerpo.append(acciones);

        item.append(icono, cuerpo);
        return item;
    }

    async function cargar(siguiente = false) {
        verMas.disabled = true;
        if (!siguiente && !lista.children.length) resumen.textContent = 'Cargando avisos…';
        try {
            const datos = await Avisos.listar(siguiente ? pagina + 1 : 1, POR_PAGINA);
            pagina = datos.meta.pagina;
            total = datos.meta.total;
            const tarjetas = datos.avisos.map(tarjeta);
            if (siguiente) lista.append(...tarjetas);
            else lista.replaceChildren(...tarjetas);
            verMas.hidden = !datos.meta.hay_mas;
            pintarConteo(datos.no_leidas);
        } catch (error) {
            resumen.textContent = error.message;
        } finally {
            verMas.disabled = false;
        }
    }

    // Solo el conteo (burbuja del menú), desde cualquier sección
    async function contar() {
        try {
            const datos = await Avisos.listar(1, 1);
            total = datos.meta.total;
            pintarConteo(datos.no_leidas);
        } catch {
            // sin sesión o sin red: la burbuja se queda como está
        }
    }

    // ---- Push en este dispositivo ----
    async function pintarPush() {
        const estado = await Avisos.push.estado();
        interruptor.setAttribute('aria-checked', String(estado === 'activo'));
        interruptor.disabled = estado === 'no_soportado' || estado === 'sin_llave';
        notaPush.textContent = {
            activo: 'Activados: te avisaremos aunque no tengas el panel abierto.',
            bloqueado: 'Bloqueados en este navegador: actívalos desde el candado 🔒 de la barra de direcciones y recarga.',
            no_soportado: Avisos.push.porQueNoSoportado(),
            sin_llave: 'Falta configurar las llaves VAPID en el servidor (php artisan webpush:vapid).',
        }[estado] || 'Recibe los avisos aunque no tengas el panel abierto.';
        $('avisos-push').classList.toggle('es-aviso', estado === 'bloqueado' || estado === 'no_soportado');
    }

    interruptor.addEventListener('click', async () => {
        interruptor.disabled = true;
        try {
            if (interruptor.getAttribute('aria-checked') === 'true') await Avisos.push.desactivar();
            else await Avisos.push.activar();
        } catch (error) {
            notaPush.textContent = error.message;
            $('avisos-push').classList.add('es-aviso');
            interruptor.disabled = false;
            return;
        }
        pintarPush();
    });

    leerTodas.addEventListener('click', async () => {
        try {
            pintarConteo(await Avisos.leerTodas());
            lista.querySelectorAll('.es-nuevo').forEach((item) => item.classList.remove('es-nuevo'));
            lista.querySelectorAll('.aviso__boton:not(.es-principal)').forEach((b) => b.remove());
        } catch (error) {
            resumen.textContent = error.message;
        }
    });
    verMas.addEventListener('click', () => cargar(true));

    let enSeccion = false;
    document.addEventListener('admin:recargar', (evento) => {
        enSeccion = evento.detail?.section === 'notificaciones';
        if (enSeccion) {
            cargar();
            pintarPush();
        }
    });

    contar();
    setInterval(() => {
        if (document.visibilityState !== 'visible') return;
        if (enSeccion) cargar();
        else contar();
    }, 60000);
});
