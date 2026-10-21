'use strict';

// Mis compras: reservas pagadas del usuario (GET /api/mis-compras).
// Cada compra enlaza a sus boletos con QR: /mis-compras/{reserva}/boletos (boletos.js).

document.addEventListener('DOMContentLoaded', async () => {
    const aviso = document.getElementById('comprasAviso');
    const vacio = document.getElementById('comprasVacio');

    if (!Auth.obtenerToken()) {
        const mensaje = encodeURIComponent('Inicia sesión para ver tus compras.');
        location.href = `/login?volver=${encodeURIComponent('/mis-compras')}&tipo=aviso&mensaje=${mensaje}`;
        return;
    }

    const zona = { timeZone: 'America/Guatemala' };
    const fechaLarga = new Intl.DateTimeFormat('es-GT', { ...zona, weekday: 'short', day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' });
    const fechaCorta = new Intl.DateTimeFormat('es-GT', { ...zona, day: 'numeric', month: 'short', year: 'numeric' });
    const dia = new Intl.DateTimeFormat('es-GT', { ...zona, day: '2-digit' });
    const mes = new Intl.DateTimeFormat('es-GT', { ...zona, month: 'short' });
    const moneda = new Intl.NumberFormat('es-GT', { style: 'currency', currency: 'GTQ' });

    const crear = (etiqueta, clase, texto) => {
        const nodo = document.createElement(etiqueta);
        if (clase) nodo.className = clase;
        if (texto !== undefined) nodo.textContent = texto;
        return nodo;
    };

    // "Platea · A3, A4" para numeradas; "General · 2 lugares" para generales
    function lugaresPorZona(lugares) {
        const porZona = new Map();
        lugares.forEach((l) => {
            if (!porZona.has(l.localidad)) porZona.set(l.localidad, []);
            porZona.get(l.localidad).push(l);
        });

        return [...porZona].map(([zona, items]) => {
            const li = crear('li');
            li.append(crear('span', '', `${zona} · `));
            li.append(items[0].numero === null
                ? `${items.length} ${items.length === 1 ? 'lugar' : 'lugares'}`
                : items.map((l) => `${l.fila}${l.numero}`).join(', '));
            return li;
        });
    }

    function tarjeta(compra, indice) {
        const fecha = new Date(compra.evento.fecha_evento);
        const articulo = crear('article', 'compra');
        articulo.style.animationDelay = `${Math.min(indice, 8) * 70}ms`;

        const bloqueFecha = crear('div', 'compra__fecha');
        bloqueFecha.append(
            crear('span', 'compra__dia', dia.format(fecha)),
            crear('span', 'compra__mes', mes.format(fecha).replace('.', ''))
        );

        const info = crear('div', 'compra__info');
        info.append(
            crear('span', 'compra__lugar', `${compra.evento.recinto} · ${fechaLarga.format(fecha)}`),
            crear('h3', 'compra__nombre', compra.evento.nombre)
        );
        const lista = crear('ul', 'compra__lugares');
        lista.append(...lugaresPorZona(compra.lugares));
        info.append(lista);

        const lado = crear('div', 'compra__lado');
        lado.append(crear('strong', 'compra__total', moneda.format(compra.total)));
        if (compra.pagado_en) {
            lado.append(crear('span', 'compra__pagado', `Pagado el ${fechaCorta.format(new Date(compra.pagado_en))}`));
        }

        // Boletos con su QR (uno por lugar)
        const boletos = crear('div', 'compra__boletos');
        const enlace = crear('a', '', compra.lugares.length === 1 ? 'Ver boleto' : `Ver ${compra.lugares.length} boletos`);
        enlace.href = `/mis-compras/${compra.id}/boletos`;
        boletos.append(enlace);
        lado.append(boletos);

        articulo.append(bloqueFecha, info, lado);
        return articulo;
    }

    const respuesta = await Auth.api('/mis-compras', { metodo: 'GET', conToken: true });

    if (respuesta.status === 401) {
        location.href = `/login?volver=${encodeURIComponent('/mis-compras')}&tipo=aviso&mensaje=${encodeURIComponent('Tu sesión expiró. Inicia sesión de nuevo.')}`;
        return;
    }
    if (!respuesta.ok) {
        aviso.textContent = 'No pudimos cargar tus compras. Inténtalo de nuevo.';
        return;
    }

    const grupos = respuesta.data;   // { proximas: [...], pasadas: [...] }
    aviso.hidden = true;

    if (grupos.proximas.length + grupos.pasadas.length === 0) {
        vacio.hidden = false;
        return;
    }

    // ---- Pestañas y "Ver más" (de 5 en 5; las compras ya llegan todas) ----
    const POR_PAGINA = 5;
    const panel = document.getElementById('panelCompras');
    const lista = document.getElementById('listaCompras');
    const sin = document.getElementById('comprasSin');
    const verMas = document.getElementById('verMas');
    const pestanas = [...document.querySelectorAll('#pestanas [role="tab"]')];
    let actual = null;
    let mostradas = 0;

    document.getElementById('cuentaProximas').textContent = grupos.proximas.length;
    document.getElementById('cuentaPasadas').textContent = grupos.pasadas.length;

    function mostrarMas() {
        const compras = grupos[actual];
        const siguientes = compras.slice(mostradas, mostradas + POR_PAGINA);
        lista.append(...siguientes.map((compra, i) => tarjeta(compra, i)));
        mostradas += siguientes.length;

        const quedan = compras.length - mostradas;
        verMas.hidden = quedan <= 0;
        verMas.replaceChildren('Ver más ', Object.assign(document.createElement('span'), { textContent: `· quedan ${quedan}` }));
    }

    function elegir(grupo, { enfocar = false } = {}) {
        actual = grupo;
        mostradas = 0;
        lista.replaceChildren();

        pestanas.forEach((boton) => {
            const activa = boton.dataset.grupo === grupo;
            boton.setAttribute('aria-selected', String(activa));
            boton.tabIndex = activa ? 0 : -1;
            if (activa && enfocar) boton.focus();
        });
        panel.classList.toggle('compras__panel--pasadas', grupo === 'pasadas');
        panel.setAttribute('aria-labelledby', grupo === 'pasadas' ? 'pestanaPasadas' : 'pestanaProximas');

        sin.hidden = grupos[grupo].length > 0;
        sin.textContent = grupo === 'pasadas'
            ? 'Aún no tienes funciones pasadas.'
            : 'No tienes funciones próximas.';

        mostrarMas();
        history.replaceState(null, '', grupo === 'pasadas' ? '#pasadas' : location.pathname);
    }

    pestanas.forEach((boton, i) => {
        boton.addEventListener('click', () => elegir(boton.dataset.grupo));
        // Flechas izquierda/derecha entre pestañas (patrón de accesibilidad para tabs)
        boton.addEventListener('keydown', (evento) => {
            if (evento.key !== 'ArrowLeft' && evento.key !== 'ArrowRight') return;
            const otra = pestanas[(i + (evento.key === 'ArrowRight' ? 1 : -1) + pestanas.length) % pestanas.length];
            elegir(otra.dataset.grupo, { enfocar: true });
        });
    });
    verMas.addEventListener('click', mostrarMas);

    document.getElementById('pestanas').hidden = false;
    panel.hidden = false;

    // Pestaña inicial: la de la dirección (#pasadas) o la que tenga compras
    const pedida = location.hash === '#pasadas' ? 'pasadas' : 'proximas';
    const otra = pedida === 'pasadas' ? 'proximas' : 'pasadas';
    elegir(grupos[pedida].length || !grupos[otra].length ? pedida : otra);
});
