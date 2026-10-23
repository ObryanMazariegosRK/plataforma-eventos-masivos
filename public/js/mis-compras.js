'use strict';

// Mis compras: reservas pagadas del usuario (GET /api/mis-compras).
// Cada compra enlaza a sus boletos con QR: /mis-compras/{reserva}/boletos (boletos.js).
// Las próximas permiten pedir el reembolso: el diálogo muestra la política y cuánto se
// devolvería hoy (GET /api/reservas/{id}/reembolso) y lo solicita (POST a la misma ruta).

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

        // Reembolso: su estado, o el botón para pedirlo si la función aún no llega
        const estado = estadoReembolso(compra);
        if (estado) {
            lado.append(crear('span', `compra__reembolso compra__reembolso--${estado.tono}`, estado.texto));
        } else if (fecha > new Date()) {
            const pedir = crear('button', 'compra__pedir-reembolso', 'Solicitar reembolso');
            pedir.type = 'button';
            pedir.addEventListener('click', () => abrirReembolso(compra, articulo, pedir));
            lado.append(pedir);
        }

        articulo.append(bloqueFecha, info, lado);
        return articulo;
    }

    function estadoReembolso(compra) {
        const r = compra.reembolso;
        if (!r) {
            return compra.evento_cancelado ? { tono: 'aviso', texto: 'Función cancelada · reembolso en proceso' } : null;
        }
        return {
            solicitado: { tono: 'espera', texto: 'Reembolso solicitado · en revisión' },
            aprobado: { tono: 'espera', texto: `Reembolso aprobado · devolución de ${moneda.format(r.monto)} en proceso` },
            procesado: { tono: 'listo', texto: `Reembolsado · ${moneda.format(r.monto)}` },
            rechazado: { tono: 'aviso', texto: 'Reembolso no aprobado' },
        }[r.estado] || null;
    }

    // ---- Diálogo "Solicitar reembolso" ----
    const dialogo = document.getElementById('dialogoReembolso');
    const formReembolso = document.getElementById('formReembolso');
    const cuerpo = document.getElementById('reembolsoCuerpo');
    const errorReembolso = document.getElementById('reembolsoError');
    const enviar = document.getElementById('reembolsoEnviar');
    const botonVolver = formReembolso.querySelector('.reembolso__secundario');
    let abierta = null;   // { compra, articulo, boton }

    // 168 → "7 días", 24 → "24 horas"
    const plazo = (horas) => (horas >= 48 && horas % 24 === 0 ? `${horas / 24} días` : `${horas} horas`);

    function tablaPolitica(politica, horasRestantes) {
        const vigente = horasRestantes === undefined ? null : politica.find((t) => horasRestantes >= t.horas_minimas);
        const tabla = crear('ul', 'reembolso__politica');
        const filas = [
            ...politica.map((t) => [t, `${plazo(t.horas_minimas)} o más antes`, `${t.porcentaje_reembolso} %`]),
            [null, `Menos de ${plazo(politica[politica.length - 1].horas_minimas)}`, 'Sin reembolso'],
        ];
        filas.forEach(([tramo, cuando, cuanto]) => {
            const li = crear('li', tramo && tramo === vigente ? 'es-vigente' : '');
            li.append(crear('span', '', cuando), crear('strong', '', cuanto));
            if (tramo && tramo === vigente) li.setAttribute('aria-current', 'true');
            tabla.append(li);
        });
        return tabla;
    }

    function pintarVista(vista) {
        cuerpo.replaceChildren();
        enviar.hidden = !vista.puede_solicitar;

        if (!vista.puede_solicitar) {
            cuerpo.append(crear('p', 'reembolso__no', vista.motivo_no));
            cuerpo.append(crear('p', 'reembolso__subtitulo', 'Política de reembolsos'), tablaPolitica(vista.politica));
            return;
        }

        const { calculo } = vista;
        const monto = crear('div', 'reembolso__monto');
        monto.append(
            crear('span', '', 'Te devolveremos'),
            crear('strong', '', moneda.format(calculo.monto_reembolso)),
            crear('small', '', `${calculo.porcentaje_reembolso} % de los ${moneda.format(calculo.monto_pagado)} que pagaste`)
        );

        const campo = crear('label', 'reembolso__motivo');
        const texto = crear('textarea');
        texto.name = 'motivo';
        texto.rows = 3;
        texto.maxLength = 255;
        texto.placeholder = 'Cuéntanos por qué (opcional)';
        const contador = crear('span', 'reembolso__contador', '0 / 255');
        texto.addEventListener('input', () => { contador.textContent = `${texto.value.length} / 255`; });
        campo.append(crear('span', '', 'Motivo'), texto, contador);

        // Si la función se aplazó, el 100 % no depende de los plazos
        const segunPolitica = calculo.por_reprogramacion
            ? [crear('p', 'reembolso__nota reembolso__nota--destacada', 'La función cambió de fecha: te devolvemos el 100 % aunque falte poco para la nueva fecha.')]
            : [crear('p', 'reembolso__subtitulo', 'Según cuánto falta para la función'), tablaPolitica(vista.politica, calculo.horas_restantes)];

        cuerpo.append(
            monto,
            ...segunPolitica,
            campo,
            crear('p', 'reembolso__nota', 'Revisaremos tu solicitud y te avisaremos por correo. Al aprobarla, tus boletos se anulan y el dinero vuelve a tu medio de pago.')
        );
    }

    async function abrirReembolso(compra, articulo, boton) {
        abierta = { compra, articulo, boton };
        document.getElementById('reembolsoTitulo').textContent = 'Solicitar reembolso';
        botonVolver.textContent = 'Volver';
        document.getElementById('reembolsoEvento').textContent = compra.evento.nombre;
        cuerpo.replaceChildren(crear('p', 'reembolso__cargando', 'Calculando tu reembolso…'));
        errorReembolso.hidden = true;
        enviar.hidden = true;
        enviar.disabled = false;
        dialogo.showModal();
        document.getElementById('reembolsoTitulo').focus();   // el lector anuncia el título, no la ✕

        const respuesta = await Auth.api(`/reservas/${compra.id}/reembolso`, { metodo: 'GET', conToken: true });
        if (abierta?.compra !== compra) return;   // se cerró o se abrió otra mientras cargaba
        if (!respuesta.ok) {
            cuerpo.replaceChildren();
            errorReembolso.textContent = Auth.mensajeDeError(respuesta);
            errorReembolso.hidden = false;
            return;
        }
        pintarVista(respuesta.data.data);
    }

    formReembolso.addEventListener('submit', async (evento) => {
        evento.preventDefault();
        if (!abierta || enviar.disabled) return;
        const { compra, articulo } = abierta;

        enviar.disabled = true;
        enviar.textContent = 'Enviando…';
        errorReembolso.hidden = true;

        const motivo = formReembolso.elements.motivo?.value.trim() || null;
        const respuesta = await Auth.api(`/reservas/${compra.id}/reembolso`, { metodo: 'POST', cuerpo: { motivo }, conToken: true });

        enviar.textContent = 'Solicitar reembolso';
        if (!respuesta.ok) {
            enviar.disabled = false;
            errorReembolso.textContent = Auth.mensajeDeError(respuesta);
            errorReembolso.hidden = false;
            return;
        }

        compra.reembolso = { ...respuesta.data.data.reembolso, fecha: new Date().toISOString() };
        const nueva = tarjeta(compra, 0);
        nueva.style.animation = 'none';
        articulo.replaceWith(nueva);
        document.getElementById('reembolsoTitulo').textContent = 'Solicitud enviada';
        cuerpo.replaceChildren(crear('p', 'reembolso__listo', respuesta.data.message));
        enviar.hidden = true;
        botonVolver.textContent = 'Listo';
        botonVolver.focus();
        abierta = { ...abierta, articulo: nueva, boton: null };
    });

    function cerrarReembolso() {
        if (dialogo.open) dialogo.close();
    }
    dialogo.addEventListener('close', () => {
        const boton = abierta?.boton;
        abierta = null;
        if (boton?.isConnected) boton.focus();
    });
    dialogo.querySelectorAll('[data-cerrar]').forEach((b) => b.addEventListener('click', cerrarReembolso));
    // Clic fuera de la caja (en el fondo oscuro) también cierra
    dialogo.addEventListener('click', (evento) => { if (evento.target === dialogo) cerrarReembolso(); });

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
