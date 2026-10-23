'use strict';

// Panel → "Reembolsos" (solo administrador): solicitudes de los clientes desde Mis compras
// (GET /api/admin/reembolsos), primero las que esperan una decisión.
//   Aprobar  → PATCH /api/reembolsos/{id}/resolver {decision: 'aprobar'}: anula los boletos, libera
//              los lugares y devuelve el dinero en la pasarela (queda "procesado").
//   Rechazar → la misma ruta con 'rechazar': los boletos siguen válidos.
//   Si la pasarela falla, queda "aprobado" y "Reintentar devolución" llama a
//   POST /api/admin/reembolsos/{id}/procesar.
// admin.js muestra la sección y avisa con "admin:recargar" cada vez que se entra a ella.

document.addEventListener('DOMContentLoaded', () => {
    const panel = document.getElementById('reembolsos-panel');
    const estados = document.getElementById('reembolsos-estados');
    const lista = document.getElementById('reembolsos-lista');
    const resumen = document.getElementById('reembolsos-resumen');
    const verMas = document.getElementById('reembolsos-ver-mas');

    if (!panel || !lista) {
        return;
    }

    const zona = { timeZone: 'America/Guatemala' };
    const fechaHora = new Intl.DateTimeFormat('es-GT', { ...zona, day: 'numeric', month: 'short', year: 'numeric', hour: 'numeric', minute: '2-digit' });
    const fechaEvento = new Intl.DateTimeFormat('es-GT', { ...zona, weekday: 'short', day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' });
    const moneda = new Intl.NumberFormat('es-GT', { style: 'currency', currency: 'GTQ' });

    const ETIQUETAS = {
        solicitado: 'Por revisar',
        aprobado: 'Devolución pendiente',
        procesado: 'Devuelto',
        rechazado: 'Rechazado',
    };

    let filtro = '';
    let pagina = 0;
    let mostrados = 0;
    let controlador = null;

    const crear = (etiqueta, clase, texto) => {
        const nodo = document.createElement(etiqueta);
        if (clase) nodo.className = clase;
        if (texto !== undefined) nodo.textContent = texto;
        return nodo;
    };

    async function pedir(ruta, opciones = {}) {
        const respuesta = await fetch(`/api${ruta}`, {
            ...opciones,
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                Authorization: `Bearer ${localStorage.getItem('auth_token')}`,
            },
        });
        const datos = await respuesta.json().catch(() => ({}));
        if (!respuesta.ok) {
            const detalle = datos.errors ? Object.values(datos.errors).flat()[0] : (datos.message || datos.mensaje);
            throw new Error(detalle || 'No se pudieron consultar los reembolsos. Inténtalo de nuevo.');
        }
        return datos;
    }

    // Mismo modal centrado que el formulario de eventos (.evento-modal en admin-tema.css)
    function dialogo({ icono, titulo, texto, botones }) {
        const modal = crear('dialog', 'evento-modal');
        modal.setAttribute('aria-labelledby', 'reembolso-modal-titulo');
        const marca = crear('span', 'evento-modal__icono', icono);
        marca.setAttribute('aria-hidden', 'true');
        const titular = crear('h2', 'evento-modal__titulo', titulo);
        titular.id = 'reembolso-modal-titulo';
        const acciones = crear('div', 'evento-modal__acciones');
        botones.forEach(({ texto: etiqueta, principal, alElegir }) => {
            const boton = crear('button', principal ? 'evento-modal__boton es-principal' : 'evento-modal__boton', etiqueta);
            boton.type = 'button';
            boton.addEventListener('click', () => {
                modal.close();
                alElegir?.();
            });
            acciones.append(boton);
        });
        modal.append(marca, titular, crear('p', 'evento-modal__texto', texto), acciones);
        modal.addEventListener('close', () => setTimeout(() => modal.remove(), 250));
        modal.addEventListener('click', (evento) => {
            if (evento.target === modal) modal.close();
        });
        document.body.append(modal);
        modal.showModal();
        acciones.querySelector('.es-principal')?.focus();
    }

    // ---- Filtro por estado: una "píldora" por estado con su conteo ----
    function pintarEstados(porEstado) {
        const total = porEstado.reduce((suma, e) => suma + e.total, 0);
        const opciones = [{ estado: '', etiqueta: 'Todos', total }, ...porEstado];
        estados.replaceChildren(...opciones.map((opcion) => {
            const boton = crear('button', `reembolsos-estado reembolsos-estado--${opcion.estado || 'todos'}`);
            boton.type = 'button';
            boton.setAttribute('aria-pressed', String(opcion.estado === filtro));
            boton.append(crear('span', '', opcion.etiqueta), crear('strong', '', String(opcion.total)));
            boton.addEventListener('click', () => {
                if (filtro === opcion.estado) return;
                filtro = opcion.estado;
                buscar();
            });
            return boton;
        }));
    }

    function dato(etiqueta, valor, nota) {
        const fila = crear('div', 'reembolso-dato');
        const dd = crear('dd', '', valor);
        if (nota) dd.append(crear('small', '', nota));
        fila.append(crear('dt', '', etiqueta), dd);
        return fila;
    }

    function tarjeta(r) {
        const item = crear('li', `reembolso-item es-${r.estado}`);

        const cabeza = crear('div', 'reembolso-item__cabeza');
        const titulo = crear('div');
        titulo.append(
            crear('span', 'reembolso-item__numero', `Solicitud #${r.id} · ${fechaHora.format(new Date(r.solicitado_en))}`),
            crear('h3', 'reembolso-item__evento', r.evento.nombre)
        );
        const montos = crear('div', 'reembolso-item__montos');
        const porcentaje = r.monto_pagado > 0 ? Math.round((r.monto / r.monto_pagado) * 100) : 0;
        montos.append(
            crear('strong', '', moneda.format(r.monto)),
            crear('span', '', `${porcentaje} % de ${moneda.format(r.monto_pagado)}`)
        );
        cabeza.append(titulo, montos);

        const datos = crear('dl', 'reembolso-item__datos');
        datos.append(
            dato('Cliente', r.cliente.nombre, r.cliente.email),
            dato('Función', `${fechaEvento.format(new Date(r.evento.fecha))}${r.evento.cancelado ? ' · cancelada' : ''}`),
            dato('Compra', `#${r.reserva_id} · ${r.lugares} ${r.lugares === 1 ? 'boleto' : 'boletos'}`),
            dato('Motivo', r.motivo || 'Sin motivo'),
        );
        if (r.resuelto_por) datos.append(dato('Resolvió', r.resuelto_por));
        if (r.referencia) datos.append(dato('Devolución', r.referencia));

        const pie = crear('div', 'reembolso-item__pie');
        pie.append(crear('span', `reembolso-chip es-${r.estado}`, ETIQUETAS[r.estado] || r.estado));

        const acciones = crear('div', 'reembolso-item__acciones');
        if (r.estado === 'solicitado') {
            acciones.append(
                boton('Rechazar', 'es-secundario', () => confirmarRechazo(r)),
                boton('Aprobar y devolver', 'es-principal', () => confirmarAprobacion(r))
            );
        } else if (r.estado === 'aprobado') {
            acciones.append(boton('Reintentar devolución', 'es-principal', (b) => reintentar(r, b)));
        }
        pie.append(acciones);

        item.append(cabeza, datos, pie);
        return item;
    }

    function boton(texto, clase, alHacerClic) {
        const nodo = crear('button', `reembolso-boton ${clase}`, texto);
        nodo.type = 'button';
        nodo.addEventListener('click', () => alHacerClic(nodo));
        return nodo;
    }

    function confirmarAprobacion(r) {
        dialogo({
            icono: '↺',
            titulo: `¿Devolver ${moneda.format(r.monto)}?`,
            texto: `Se anularán ${r.lugares === 1 ? 'el boleto' : `los ${r.lugares} boletos`} de ${r.cliente.nombre}, `
                + (r.evento.cancelado ? '' : 'sus lugares volverán a la venta ')
                + 'y la pasarela devolverá el dinero. No se puede deshacer.',
            botones: [
                { texto: 'Volver' },
                { texto: 'Aprobar y devolver', principal: true, alElegir: () => resolver(r, 'aprobar') },
            ],
        });
    }

    function confirmarRechazo(r) {
        dialogo({
            icono: '✕',
            titulo: '¿Rechazar la solicitud?',
            texto: `${r.cliente.nombre} conservará sus boletos y recibirá un correo avisándole. No podrá volver a pedir el reembolso de esta compra.`,
            botones: [
                { texto: 'Volver' },
                { texto: 'Rechazar', principal: true, alElegir: () => resolver(r, 'rechazar') },
            ],
        });
    }

    async function resolver(r, decision) {
        resumen.textContent = decision === 'aprobar' ? 'Devolviendo el dinero…' : 'Rechazando…';
        try {
            const datos = await pedir(`/reembolsos/${r.id}/resolver`, {
                method: 'PATCH',
                body: JSON.stringify({ decision }),
            });
            const pendiente = datos.devolucion_pendiente;
            dialogo({
                icono: pendiente ? '!' : '✓',
                titulo: decision === 'rechazar'
                    ? 'Solicitud rechazada'
                    : (pendiente ? 'Aprobado, devolución pendiente' : `Devolviste ${moneda.format(r.monto)}`),
                texto: decision === 'rechazar'
                    ? 'Le avisamos al cliente por correo.'
                    : (pendiente ? datos.mensaje : 'Los boletos quedaron anulados y le avisamos al cliente por correo.'),
                botones: [{ texto: 'Entendido', principal: true }],
            });
        } catch (error) {
            dialogo({ icono: '!', titulo: 'No se pudo resolver', texto: error.message, botones: [{ texto: 'Entendido', principal: true }] });
        }
        buscar();
    }

    async function reintentar(r, nodo) {
        nodo.disabled = true;
        nodo.textContent = 'Devolviendo…';
        try {
            await pedir(`/admin/reembolsos/${r.id}/procesar`, { method: 'POST' });
            dialogo({ icono: '✓', titulo: `Devolviste ${moneda.format(r.monto)}`, texto: 'Le avisamos al cliente por correo.', botones: [{ texto: 'Entendido', principal: true }] });
        } catch (error) {
            dialogo({ icono: '!', titulo: 'La devolución sigue pendiente', texto: error.message, botones: [{ texto: 'Entendido', principal: true }] });
        }
        buscar();
    }

    async function buscar(siguiente = false) {
        controlador?.abort();
        controlador = new AbortController();
        const señal = controlador.signal;

        if (!siguiente) {
            pagina = 0;
            mostrados = 0;
            if (!lista.children.length) resumen.textContent = 'Buscando…';
        }
        verMas.disabled = true;

        const parametros = new URLSearchParams({ pagina: String(pagina + 1) });
        if (filtro) parametros.set('estado', filtro);

        try {
            const respuesta = await fetch(`/api/admin/reembolsos?${parametros}`, {
                headers: { Accept: 'application/json', Authorization: `Bearer ${localStorage.getItem('auth_token')}` },
                signal: señal,
            });
            const cuerpo = await respuesta.json().catch(() => ({}));
            if (!respuesta.ok) throw new Error(cuerpo.message || 'No se pudieron consultar los reembolsos. Inténtalo de nuevo.');

            const { reembolsos, por_estado: porEstado, meta } = cuerpo.data;
            pagina = meta.pagina;
            pintarEstados(porEstado);

            const tarjetas = reembolsos.map(tarjeta);
            if (siguiente) lista.append(...tarjetas);
            else lista.replaceChildren(...tarjetas);
            mostrados = (siguiente ? mostrados : 0) + reembolsos.length;

            const pendientes = porEstado.find((e) => e.estado === 'solicitado')?.total ?? 0;
            resumen.textContent = meta.total === 0
                ? (filtro ? `No hay solicitudes «${ETIQUETAS[filtro]}».` : 'Todavía no hay solicitudes de reembolso.')
                : `${meta.total} ${meta.total === 1 ? 'solicitud' : 'solicitudes'}`
                    + (pendientes ? ` · ${pendientes} por revisar` : '');

            const quedan = meta.total - mostrados;
            verMas.hidden = quedan <= 0;
            verMas.textContent = `Ver más solicitudes · quedan ${quedan}`;
        } catch (error) {
            if (error.name === 'AbortError') return;
            resumen.textContent = error.message;
            verMas.hidden = true;
        } finally {
            if (!señal.aborted) verMas.disabled = false;
        }
    }

    verMas.addEventListener('click', () => buscar(true));

    document.addEventListener('admin:recargar', (evento) => {
        if (evento.detail?.section !== 'reembolsos') return;
        buscar();
    });
});
