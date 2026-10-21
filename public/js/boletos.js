'use strict';

// Boletos de una compra: uno por asiento, con su QR.
// Datos: GET /api/reservas/{id} (lugares con su boleto) y GET /api/boletos/{id}/qr (SVG del
// módulo Boletos). El QR pide el token, así que se descarga con fetch y se muestra como imagen.

document.addEventListener('DOMContentLoaded', async () => {
    const pagina = document.getElementById('paginaBoletos');
    const reservaId = pagina.dataset.reserva;
    const aviso = document.getElementById('boletosAviso');
    const lista = document.getElementById('boletosLista');
    const MARCA = document.body.dataset.marca || '';

    if (!Auth.obtenerToken()) {
        const volver = encodeURIComponent(location.pathname);
        location.href = `/login?volver=${volver}&tipo=aviso&mensaje=${encodeURIComponent('Inicia sesión para ver tus boletos.')}`;
        return;
    }

    const zona = { timeZone: 'America/Guatemala' };
    const fechaEvento = new Intl.DateTimeFormat('es-GT', { ...zona, weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    const horaEvento = new Intl.DateTimeFormat('es-GT', { ...zona, hour: 'numeric', minute: '2-digit' });
    const fechaCorta = new Intl.DateTimeFormat('es-GT', { ...zona, day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' });

    const crear = (etiqueta, clase, texto) => {
        const nodo = document.createElement(etiqueta);
        if (clase) nodo.className = clase;
        if (texto !== undefined) nodo.textContent = texto;
        return nodo;
    };
    const numeroBoleto = (id) => `Nº ${String(id).padStart(6, '0')}`;

    // ---- Datos de la compra (espera si los boletos aún se están emitiendo) ----
    async function cargarCompra() {
        const respuesta = await Auth.api(`/reservas/${reservaId}`, { metodo: 'GET', conToken: true });

        if (respuesta.status === 401) {
            location.href = `/login?volver=${encodeURIComponent(location.pathname)}&tipo=aviso&mensaje=${encodeURIComponent('Tu sesión expiró. Inicia sesión de nuevo.')}`;
            return null;
        }
        if (!respuesta.ok) {
            aviso.textContent = respuesta.status === 404
                ? 'No encontramos esta compra en tu cuenta.'
                : 'No pudimos cargar tus boletos. Inténtalo de nuevo.';
            document.getElementById('boletosEvento').textContent = 'Tus boletos';
            return null;
        }
        return respuesta.data;
    }

    let compra = await cargarCompra();
    if (!compra) return;

    // Tras pagar, el worker emite los boletos en unos segundos: se consulta de nuevo un rato
    for (let intento = 0; compra.lugares.some((l) => !l.boleto) && intento < 20; intento++) {
        aviso.textContent = 'Estamos emitiendo tus boletos… esto toma unos segundos.';
        await new Promise((listo) => setTimeout(listo, 3000));
        compra = await cargarCompra();
        if (!compra) return;
    }

    const { evento, lugares } = compra;
    const fecha = new Date(evento.fecha_evento);
    const boletos = lugares.filter((l) => l.boleto);

    document.title = `Boletos · ${evento.nombre} · ${MARCA}`;
    document.getElementById('boletosEvento').textContent = evento.nombre;
    document.getElementById('boletosDatos').textContent =
        `${evento.recinto} · ${fechaEvento.format(fecha)}, ${horaEvento.format(fecha)}`;
    document.getElementById('boletosCantidad').textContent =
        boletos.length === 1 ? '1 boleto' : `${boletos.length} boletos`;

    if (boletos.length === 0) {
        aviso.textContent = 'Tus boletos todavía no están listos. Recarga la página en un momento.';
        return;
    }
    aviso.hidden = boletos.length === lugares.length;
    if (!aviso.hidden) {
        aviso.textContent = 'Algunos boletos todavía se están emitiendo. Recarga la página en un momento.';
    }

    // ---- Un boleto por lugar ----
    function boleto(lugar, indice) {
        const articulo = crear('article', 'boleto');
        articulo.style.setProperty('--color-zona', lugar.color || '#c8b48c');
        articulo.style.animationDelay = `${Math.min(indice, 6) * 80}ms`;
        const activo = lugar.boleto.estado === 'emitido';
        if (!activo) articulo.classList.add('boleto--inactivo');

        // Parte principal
        const cuerpo = crear('div', 'boleto__cuerpo');
        const arriba = crear('div', 'boleto__arriba');
        arriba.append(crear('span', 'boleto__marca', MARCA), crear('span', 'boleto__numero', `Boleto ${indice + 1} de ${boletos.length}`));

        const cuando = crear('p', 'boleto__fecha');
        cuando.append(`${fechaEvento.format(fecha)} · `, crear('strong', '', horaEvento.format(fecha)));

        const lugarEl = crear('div', 'boleto__lugar');
        const dato = (titulo, valor) => {
            const caja = crear('div', 'boleto__dato');
            caja.append(crear('small', '', titulo), crear('span', '', valor));
            return caja;
        };
        lugarEl.append(dato('Zona', lugar.localidad));
        if (lugar.numero === null) {
            lugarEl.append(dato('Entrada', 'Admisión general'));
        } else {
            lugarEl.append(dato('Fila', lugar.fila), dato('Asiento', String(lugar.numero)));
        }

        cuerpo.append(arriba, crear('h2', 'boleto__evento', evento.nombre), cuando, lugarEl, crear('p', 'boleto__recinto', evento.recinto));

        // Talón con el QR
        const talon = crear('div', 'boleto__talon');
        const qr = crear('div', 'boleto__qr');
        qr.append(crear('span', 'cargando'));
        const estado = crear('span', 'boleto__estado',
            activo ? 'Válido para una persona'
                : lugar.boleto.estado === 'usado'
                    ? `Usado${lugar.boleto.usado_en ? ` el ${fechaCorta.format(new Date(lugar.boleto.usado_en))}` : ''}`
                    : 'Anulado');
        talon.append(crear('span', 'boleto__muesca boleto__muesca--a'), crear('span', 'boleto__muesca boleto__muesca--b'),
            qr, crear('span', 'boleto__codigo', numeroBoleto(lugar.boleto.id)), estado);

        if (activo) {
            cargarQr(lugar.boleto.id, qr);
        } else {
            qr.replaceChildren(crear('span', 'boleto__sello', lugar.boleto.estado === 'usado' ? 'Usado' : 'Anulado'));
        }

        articulo.append(cuerpo, talon);
        return articulo;
    }

    // El endpoint del QR pide el token: se descarga y se muestra como imagen (blob)
    async function cargarQr(boletoId, contenedor) {
        try {
            const respuesta = await fetch(`/api/boletos/${boletoId}/qr`, {
                headers: { Accept: 'image/svg+xml', Authorization: `Bearer ${Auth.obtenerToken()}` },
            });
            if (!respuesta.ok) throw new Error(String(respuesta.status));

            const imagen = new Image();
            imagen.alt = `Código QR del boleto ${numeroBoleto(boletoId)}`;
            imagen.src = URL.createObjectURL(await respuesta.blob());
            await imagen.decode().catch(() => {});
            contenedor.replaceChildren(imagen);
        } catch {
            contenedor.replaceChildren(crear('p', 'boleto__error', 'No se pudo cargar el código. Recarga la página.'));
        }
    }

    lista.append(...boletos.map(boleto));

    document.getElementById('boletosConsejo').hidden = false;
    const imprimir = document.getElementById('btnImprimir');
    imprimir.hidden = false;
    imprimir.addEventListener('click', () => window.print());
});
