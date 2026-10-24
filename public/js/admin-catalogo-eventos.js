'use strict';

// Panel → "Catálogo de eventos": tarjetas de todos los eventos (cualquier estado) con filtros
// que buscan en el servidor (GET /api/admin/eventos/catalogo). La sección la muestra admin.js;
// cada tarjeta lleva los botones de gestión de admin.js (Ver, Editar, Precios, Publicar...)
// y el catálogo se actualiza con el aviso "admin:recargar" que admin.js emite al recargar.

document.addEventListener('DOMContentLoaded', () => {
    const panel = document.getElementById('catalogo-panel');
    const formulario = document.getElementById('catalogo-filtros');
    const grid = document.getElementById('catalogo-grid');
    const resumen = document.getElementById('catalogo-resumen');
    const verMas = document.getElementById('catalogo-ver-mas');
    const selectorRecinto = document.getElementById('catalogo-recinto');
    const estados = document.getElementById('catalogo-estados');
    const totalEstados = document.getElementById('catalogo-estados-total');

    if (!panel || !formulario) {
        return;
    }

    const FOTO_RESPALDO = '/img/landing/tarjetas/auditorio-general.webp';
    const ESTADOS = {
        publicado: 'Publicado',
        borrador: 'Borrador',
        agotado: 'Agotado',
        finalizado: 'Finalizado',
        cancelado: 'Cancelado',
    };
    const zona = { timeZone: 'America/Guatemala' };
    const fecha = new Intl.DateTimeFormat('es-GT', { ...zona, weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
    const hora = new Intl.DateTimeFormat('es-GT', { ...zona, hour: 'numeric', minute: '2-digit' });

    let iniciado = false;
    let pagina = 0;
    let total = 0;
    let mostrados = 0;
    let controlador = null;
    let espera = null;

    const crear = (etiqueta, clase, texto) => {
        const nodo = document.createElement(etiqueta);
        if (clase) nodo.className = clase;
        if (texto !== undefined) nodo.textContent = texto;
        return nodo;
    };

    async function pedir(ruta, señal) {
        const respuesta = await fetch(`/api/admin${ruta}`, {
            headers: { Accept: 'application/json', Authorization: `Bearer ${localStorage.getItem('auth_token')}` },
            signal: señal,
        });
        if (!respuesta.ok) throw new Error(String(respuesta.status));
        return respuesta.json();
    }

    // ---- Filtros ----
    function filtros() {
        const datos = new FormData(formulario);
        const parametros = new URLSearchParams();
        for (const [clave, valor] of datos) {
            if (String(valor).trim()) parametros.set(clave, String(valor).trim());
        }
        return parametros;
    }

    async function cargarRecintos() {
        try {
            const { data } = await pedir('/eventos/recintos');
            data.forEach((recinto) => selectorRecinto.add(new Option(`${recinto.nombre} · ${recinto.ciudad}`, recinto.id)));
        } catch {
            // sin recintos el filtro queda en "Todos"
        }
    }

    // ---- Tarjetas ----
    function tarjeta(evento) {
        const cuando = evento.fecha_evento ? new Date(evento.fecha_evento) : null;
        const pasado = cuando && cuando < new Date();
        const articulo = crear('article', `catalogo-tarjeta${pasado ? ' catalogo-tarjeta--pasado' : ''}`);

        const foto = crear('div', 'catalogo-tarjeta__foto');
        const imagen = new Image();
        imagen.alt = '';
        imagen.loading = 'lazy';
        imagen.decoding = 'async';
        imagen.src = /^(https?:\/\/|\/(?!\/))/i.test(evento.imagen_url || '') ? evento.imagen_url : FOTO_RESPALDO;
        imagen.addEventListener('error', () => { imagen.src = FOTO_RESPALDO; }, { once: true });
        const estado = crear('span', 'so-event-status catalogo-tarjeta__estado', ESTADOS[evento.estado] || evento.estado);
        estado.dataset.status = evento.estado;
        foto.append(imagen, estado);
        if (evento.cola_virtual) foto.append(crear('span', 'catalogo-tarjeta__fila', 'Fila virtual'));

        const cuerpo = crear('div', 'catalogo-tarjeta__cuerpo');
        cuerpo.append(
            crear('span', 'catalogo-tarjeta__fecha', cuando ? `${fecha.format(cuando)} · ${hora.format(cuando)}` : 'Fecha por confirmar'),
            crear('h3', 'catalogo-tarjeta__nombre', evento.nombre),
            crear('span', 'catalogo-tarjeta__lugar', [evento.recinto, evento.ciudad].filter(Boolean).join(' · ') || 'Sin recinto'),
        );

        // Ocupación: solo si el evento ya tiene lugares a la venta (publicado)
        if (evento.lugares > 0) {
            const porcentaje = Math.round((evento.vendidos / evento.lugares) * 100);
            const ocupacion = crear('div', 'catalogo-tarjeta__ocupacion');
            const barra = crear('div', 'catalogo-tarjeta__barra');
            const relleno = crear('span');
            relleno.style.width = `${porcentaje}%`;
            barra.append(relleno);
            ocupacion.append(
                crear('span', '', `${evento.vendidos} de ${evento.lugares} vendidos · ${porcentaje}%`),
                barra,
            );
            cuerpo.append(ocupacion);
        } else {
            cuerpo.append(crear('span', 'catalogo-tarjeta__nota', 'Aún sin lugares a la venta'));
        }

        // Mismos botones que tenía la tabla de Eventos (los arma admin.js)
        const acciones = window.AdminEventos?.acciones(evento);
        if (acciones) {
            acciones.classList.add('catalogo-tarjeta__acciones');
            cuerpo.append(acciones);
        }

        articulo.append(foto, cuerpo);
        return articulo;
    }

    // ---- Estados de los eventos (cada uno filtra el catálogo al tocarlo) ----
    function pintarEstados(porEstado) {
        if (!estados || !porEstado) return;
        const total = porEstado.reduce((suma, e) => suma + e.total, 0);
        const elegido = formulario.elements.namedItem('estado').value;
        totalEstados.textContent = `Total: ${total}`;

        estados.replaceChildren(...porEstado.map((estado) => {
            const boton = crear('button', 'bar-row estados-tira__estado');
            boton.type = 'button';
            boton.dataset.estado = estado.estado;
            boton.setAttribute('aria-pressed', String(elegido === estado.estado));
            boton.setAttribute('aria-label', `${estado.etiqueta}: ${estado.total} eventos. Filtrar por este estado`);

            const etiqueta = crear('div', 'bar-label');
            etiqueta.append(crear('span', '', estado.etiqueta), crear('b', '', String(estado.total)));
            const pista = crear('div', 'track');
            const relleno = crear('div', 'fill');
            relleno.style.width = `${total ? (estado.total / total) * 100 : 0}%`;
            pista.append(relleno);
            boton.append(etiqueta, pista);
            return boton;
        }));
    }

    estados?.addEventListener('click', (evento) => {
        const boton = evento.target.closest('[data-estado]');
        if (!boton) return;
        const selector = formulario.elements.namedItem('estado');
        // Tocar el estado ya elegido quita el filtro
        selector.value = selector.value === boton.dataset.estado ? '' : boton.dataset.estado;
        buscar();
    });

    function pintarResumen() {
        const hay = Array.from(new FormData(formulario).values()).some((v) => String(v).trim());
        resumen.textContent = total === 0
            ? (hay ? 'No hay eventos con estos filtros.' : 'Todavía no hay eventos.')
            : `${total} ${total === 1 ? 'evento' : 'eventos'}${hay ? ' con estos filtros' : ''}`;

        const quedan = total - mostrados;
        verMas.hidden = quedan <= 0;
        verMas.textContent = `Ver más eventos · quedan ${quedan}`;
    }

    // ---- Búsqueda (página 1 reemplaza; las siguientes se agregan) ----
    async function buscar(siguiente = false) {
        controlador?.abort();
        controlador = new AbortController();
        const parametros = filtros();
        parametros.set('pagina', String(siguiente ? pagina + 1 : 1));

        if (!siguiente) {
            resumen.textContent = 'Buscando…';
            grid.setAttribute('aria-busy', 'true');
        }
        verMas.disabled = true;

        try {
            const { data, meta } = await pedir(`/eventos/catalogo?${parametros}`, controlador.signal);
            pagina = meta.pagina;
            total = meta.total;

            if (!siguiente) {
                grid.replaceChildren();
                mostrados = 0;
            }
            grid.append(...data.map(tarjeta));
            mostrados += data.length;
            pintarResumen();
            pintarEstados(meta.por_estado);
        } catch (error) {
            if (error.name === 'AbortError') return;
            resumen.textContent = 'No se pudo consultar el catálogo. Inténtalo de nuevo.';
        } finally {
            grid.removeAttribute('aria-busy');
            verMas.disabled = false;
        }
    }

    formulario.addEventListener('submit', (evento) => {
        evento.preventDefault();
        buscar();
    });
    // Al escribir se espera un momento (no se consulta en cada tecla); selects y fechas, al instante
    formulario.addEventListener('input', (evento) => {
        clearTimeout(espera);
        espera = setTimeout(buscar, evento.target.type === 'search' ? 350 : 0);
    });
    formulario.addEventListener('reset', () => setTimeout(buscar, 0));
    verMas.addEventListener('click', () => buscar(true));

    // admin.js avisa cada vez que muestra o recarga una sección (también tras publicar, editar o
    // cambiar una imagen): si es el catálogo, se llena la primera vez y luego se actualiza
    document.addEventListener('admin:recargar', async (evento) => {
        if (evento.detail?.section !== 'catalogo') return;

        if (!iniciado) {
            iniciado = true;
            await cargarRecintos();
        }
        buscar();
    });
});
