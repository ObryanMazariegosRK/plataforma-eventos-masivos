'use strict';

// Panel → Resumen → "Ventas y ganancias" (solo administrador; GET /api/admin/finanzas).
// Un filtro de período arriba que aplica a todo: cifras, columnas de ventas (por día, semana o
// mes según el largo del período), ganancia por evento ya realizado (positiva hacia arriba,
// pérdida hacia abajo) y la tabla de rentabilidad (historial del período / próximos eventos).
// Colores validados (dataviz): --t-serie-ingresos, --t-ganancia, --t-perdida en admin-tema.css.

document.addEventListener('DOMContentLoaded', () => {
    const panel = document.getElementById('finanzas-panel');
    if (!panel) return;

    const $ = (id) => document.getElementById(id);
    const SVG = 'http://www.w3.org/2000/svg';
    const POR_PAGINA = 8;

    const zona = { timeZone: 'America/Guatemala' };
    const q = new Intl.NumberFormat('es-GT', { style: 'currency', currency: 'GTQ' });
    const qEntero = new Intl.NumberFormat('es-GT', { style: 'currency', currency: 'GTQ', maximumFractionDigits: 0 });
    const entero = new Intl.NumberFormat('es-GT');
    const pct = new Intl.NumberFormat('es-GT', { style: 'percent', maximumFractionDigits: 1 });
    // Los días llegan como "2026-10-22" (ya en hora de Guatemala): se leen a mediodía UTC
    const diaDe = (texto) => new Date(`${texto}T12:00:00Z`);
    const enUtc = { timeZone: 'UTC' };
    const diaCorto = new Intl.DateTimeFormat('es-GT', { ...enUtc, day: 'numeric', month: 'short' });
    const diaLargo = new Intl.DateTimeFormat('es-GT', { ...enUtc, weekday: 'long', day: 'numeric', month: 'long' });
    const mesCorto = new Intl.DateTimeFormat('es-GT', { ...enUtc, month: 'short' });
    const mesLargo = new Intl.DateTimeFormat('es-GT', { ...enUtc, month: 'long', year: 'numeric' });
    const fechaEvento = new Intl.DateTimeFormat('es-GT', { ...zona, day: 'numeric', month: 'short', year: 'numeric' });
    const fechaEventoCorta = new Intl.DateTimeFormat('es-GT', { ...zona, day: 'numeric', month: 'short' });
    const hoyEnGuatemala = () => new Intl.DateTimeFormat('en-CA', zona).format(new Date());

    let datos = null;
    let vista = 'historial';
    let mostrados = POR_PAGINA;
    let controlador = null;

    const crear = (etiqueta, clase, texto) => {
        const nodo = document.createElement(etiqueta);
        if (clase) nodo.className = clase;
        if (texto !== undefined) nodo.textContent = texto;
        return nodo;
    };
    const svg = (etiqueta, atributos = {}, texto) => {
        const nodo = document.createElementNS(SVG, etiqueta);
        Object.entries(atributos).forEach(([k, v]) => nodo.setAttribute(k, v));
        if (texto !== undefined) nodo.textContent = texto;
        return nodo;
    };
    const conSigno = (monto) => (monto > 0 ? '+' : monto < 0 ? '−' : '') + q.format(Math.abs(monto));
    const qEje = (valor) => {
        const signo = valor < 0 ? '−' : '';
        const absoluto = Math.abs(valor);
        return signo + (absoluto >= 10000 ? `${qEntero.format(absoluto / 1000)} mil` : qEntero.format(absoluto));
    };
    const fechaDeEvento = (e) => (e.fecha_evento ? new Date(e.fecha_evento.replace(' ', 'T') + 'Z') : null);

    // =====================================================
    // Filtro de período
    // =====================================================
    function sumarDias(texto, dias) {
        const fecha = diaDe(texto);
        fecha.setUTCDate(fecha.getUTCDate() + dias);
        return fecha.toISOString().slice(0, 10);
    }

    function elegirPreset(boton) {
        const hoy = hoyEnGuatemala();
        $('finanzas-hasta').value = hoy;
        $('finanzas-desde').value = boton.hasAttribute('data-anio') ? `${hoy.slice(0, 4)}-01-01` : sumarDias(hoy, 1 - Number(boton.dataset.dias));
        marcarPreset(boton);
        cargar();
    }

    function marcarPreset(activo) {
        panel.querySelectorAll('.finanzas__presets button').forEach((b) => b.setAttribute('aria-pressed', String(b === activo)));
    }

    panel.querySelectorAll('.finanzas__presets button').forEach((boton) => boton.addEventListener('click', () => elegirPreset(boton)));
    $('finanzas-filtro').addEventListener('change', (evento) => {
        if (evento.target.type !== 'date') return;
        marcarPreset(null);   // rango personalizado
        if ($('finanzas-desde').value && $('finanzas-hasta').value) cargar();
    });
    $('finanzas-filtro').addEventListener('submit', (evento) => evento.preventDefault());

    // =====================================================
    // Cifras
    // =====================================================
    function cifra(etiqueta, valor, nota, tono) {
        const tarjeta = crear('div', 'finanzas__cifra');
        tarjeta.append(crear('span', 'finanzas__etiqueta', etiqueta), crear('strong', 'finanzas__valor', valor));
        if (nota) {
            const notaNodo = crear('span', 'finanzas__variacion', nota);
            if (tono) notaNodo.dataset.tono = tono;
            tarjeta.append(notaNodo);
        }
        return tarjeta;
    }

    function pintarCifras() {
        const t = datos.totales;
        const p = datos.periodo;
        // De qué período son las ventas; si antes hubo ventas, además cuánto cambiaron
        const hastaHoy = p.hasta === hoyEnGuatemala();
        const periodo = hastaHoy
            ? (p.dias === 1 ? 'Ventas de hoy' : `Ventas de los últimos ${p.dias} días`)
            : `Ventas del ${diaCorto.format(diaDe(p.desde))} al ${diaCorto.format(diaDe(p.hasta))}`;
        let variacion = periodo;
        let tono = null;
        if (t.variacion !== null) {
            const sube = t.variacion >= 0;
            const antes = hastaHoy ? `los ${p.dias} días anteriores` : 'el período anterior';
            variacion = `${periodo} · ${sube ? '▲' : '▼'} ${pct.format(Math.abs(t.variacion))} frente a ${antes}`;
            tono = sube ? 'bien' : 'mal';
        }

        let notaGanancia = t.eventos_cerrados
            ? `${t.eventos_cerrados} ${t.eventos_cerrados === 1 ? 'evento realizado' : 'eventos realizados'} en el período`
            : 'Ningún evento realizado en el período';
        if (t.eventos_sin_costos) notaGanancia += ` · ${t.eventos_sin_costos} sin costos registrados`;

        // Ventas y boletos ya descuentan lo reembolsado en el período
        $('finanzas-cifras').replaceChildren(
            cifra('Ventas netas', q.format(t.ventas), variacion, tono),
            cifra('Reembolsos', t.reembolsos > 0 ? `−${q.format(t.reembolsos)}` : q.format(0),
                t.reembolsos > 0
                    ? `${pct.format(t.reembolsos / Math.max(t.ventas_brutas, 1))} de ${q.format(t.ventas_brutas)} cobrados · ${t.boletos_devueltos} boletos devueltos`
                    : 'Sin reembolsos en el período',
                t.reembolsos > 0 ? 'mal' : null),
            cifra('Boletos vendidos', entero.format(t.boletos),
                t.boletos_devueltos ? `${entero.format(t.boletos_vendidos)} vendidos − ${entero.format(t.boletos_devueltos)} devueltos`
                    : (t.boletos_anteriores ? `${entero.format(t.boletos_anteriores)} en el período anterior` : null)),
            cifra('Ganancia', conSigno(t.ganancia), notaGanancia, t.ganancia < 0 ? 'mal' : null),
            cifra('Margen', t.margen === null ? '—' : pct.format(t.margen), 'ganancia ÷ (cobrado − reembolsado) de esos eventos', t.margen !== null && t.margen < 0 ? 'mal' : null),
        );

        $('finanzas-periodo').textContent = `Del ${diaCorto.format(diaDe(p.desde))} al ${diaCorto.format(diaDe(p.hasta))} · hora de Guatemala · comisión de pasarela ${pct.format(datos.comision_pasarela)}`;
    }

    // =====================================================
    // Columnas (ventas y ganancias): admite valores negativos
    // =====================================================
    function escala(minimo, maximo) {
        const rango = Math.max(maximo, -minimo, 1);
        const bruto = rango / 4;
        const potencia = 10 ** Math.floor(Math.log10(bruto));
        const paso = [1, 2, 2.5, 5, 10].map((m) => m * potencia).find((p) => p >= bruto);
        return {
            paso,
            arriba: maximo > 0 ? paso * Math.ceil(maximo / paso) : 0,
            abajo: minimo < 0 ? paso * Math.floor(minimo / paso) : 0,
        };
    }

    /**
     * items: [{valor, etiquetaX, descripcion (aria), tooltip: [fuerte, ...líneas], clase}]
     * extremos: índices que llevan etiqueta directa (el mayor y, si hay, la mayor pérdida)
     */
    function columnas(contenedor, items, { vacio, extremos = [] }) {
        const ancho = Math.max(300, contenedor.clientWidth || 560);
        const alto = 230;
        const m = { arriba: 22, derecha: 8, abajo: 28, izquierda: 62 };
        const areaAncho = ancho - m.izquierda - m.derecha;
        const areaAlto = alto - m.arriba - m.abajo;
        // item.abajo (opcional, positivo): una segunda barra bajo el eje en la misma franja (p. ej. reembolsos)
        const valores = items.flatMap((i) => [i.valor, -(i.abajo || 0)]);
        const { paso, arriba, abajo } = escala(Math.min(0, ...valores), Math.max(0, ...valores));
        const total = (arriba - abajo) || paso;
        const y = (valor) => m.arriba + ((arriba - valor) / total) * areaAlto;
        const banda = areaAncho / Math.max(1, items.length);
        const grosor = Math.min(24, Math.max(3, banda - 4));
        const cadaCuantas = Math.max(1, Math.ceil(items.length / Math.max(1, Math.floor(areaAncho / 64))));

        const lienzo = svg('svg', { viewBox: `0 0 ${ancho} ${alto}`, width: ancho, height: alto, role: 'img' });
        const titulo = contenedor.closest('article')?.querySelector('h3');
        if (titulo) lienzo.setAttribute('aria-labelledby', titulo.id);

        for (let valor = abajo; valor <= arriba + 0.001; valor += paso) {
            lienzo.append(
                svg('line', { x1: m.izquierda, x2: ancho - m.derecha, y1: y(valor), y2: y(valor), class: Math.abs(valor) < 0.001 ? 'eje' : 'rejilla' }),
                svg('text', { x: m.izquierda - 8, y: y(valor) + 4, class: 'eje-texto', 'text-anchor': 'end' }, qEje(valor)),
            );
        }

        const tooltip = crear('div', 'grafica-tooltip');
        tooltip.hidden = true;
        tooltip.setAttribute('role', 'status');

        const mostrar = (item, x, yTope) => {
            const [fuerte, ...lineas] = item.tooltip;
            tooltip.replaceChildren(crear('strong', '', fuerte), ...lineas.map((l) => crear('span', '', l)));
            tooltip.hidden = false;
            tooltip.style.left = `${Math.min(Math.max(x, 80), ancho - 80)}px`;
            tooltip.style.top = `${Math.max(yTope - 8, 12)}px`;
        };
        const ocultar = () => { tooltip.hidden = true; };

        items.forEach((item, i) => {
            const centro = m.izquierda + banda * i + banda / 2;

            // Etiquetas espaciadas para que no se encimen
            if (i % cadaCuantas === 0) {
                lienzo.append(svg('text', { x: centro, y: alto - 9, class: 'eje-texto', 'text-anchor': 'middle' }, item.etiquetaX));
            }

            const grupo = svg('g', { class: 'columna', tabindex: '0', role: 'img', 'aria-label': item.descripcion });
            const base = y(0);
            const barra = (valor, clase) => {
                const punta = y(valor);
                if (Math.abs(base - punta) < 0.5) return;
                const x = centro - grosor / 2;
                const alturaBarra = Math.abs(base - punta);
                const r = Math.min(4, alturaBarra / 2, grosor / 2);
                // Esquinas redondeadas solo en el extremo del dato; la base queda recta sobre el eje
                const d = valor >= 0
                    ? `M${x},${base} V${punta + r} Q${x},${punta} ${x + r},${punta} H${x + grosor - r} Q${x + grosor},${punta} ${x + grosor},${punta + r} V${base} Z`
                    : `M${x},${base} V${punta - r} Q${x},${punta} ${x + r},${punta} H${x + grosor - r} Q${x + grosor},${punta} ${x + grosor},${punta - r} V${base} Z`;
                grupo.append(svg('path', { class: `columna__barra ${clase || ''}`, d }));
            };
            barra(item.valor, item.clase);
            if (item.abajo) barra(-item.abajo, item.claseAbajo);
            const punta = y(item.valor);
            // Zona sensible: toda la franja, no solo la barra
            grupo.append(svg('rect', { class: 'columna__zona', x: m.izquierda + banda * i, y: m.arriba, width: banda, height: areaAlto }));
            const enfocar = () => mostrar(item, centro, Math.min(base, punta, y(-(item.abajo || 0))));
            grupo.addEventListener('pointerenter', enfocar);
            grupo.addEventListener('focus', enfocar);
            grupo.addEventListener('pointerleave', ocultar);
            grupo.addEventListener('blur', ocultar);
            lienzo.append(grupo);

            if (extremos.includes(i) && item.valor !== 0) {
                const xEtiqueta = Math.min(Math.max(centro, m.izquierda + 34), ancho - 34);
                const yEtiqueta = item.valor > 0 ? punta - 7 : punta + 15;
                lienzo.append(svg('text', { x: xEtiqueta, y: yEtiqueta, class: 'valor-texto', 'text-anchor': 'middle' }, qEje(item.valor)));
            }
        });

        contenedor.replaceChildren(lienzo, tooltip);
        if (!items.length || items.every((i) => i.valor === 0 && !i.abajo)) {
            contenedor.append(crear('p', 'grafica-vacia', vacio));
        }
    }

    function etiquetaGrupo(g, agrupacion, larga = false) {
        if (agrupacion === 'mes') return larga ? mesLargo.format(diaDe(g.inicio)) : mesCorto.format(diaDe(g.inicio));
        if (agrupacion === 'semana') {
            return larga ? `Del ${diaCorto.format(diaDe(g.inicio))} al ${diaCorto.format(diaDe(g.fin))}` : diaCorto.format(diaDe(g.inicio));
        }
        return larga ? diaLargo.format(diaDe(g.inicio)) : diaCorto.format(diaDe(g.inicio));
    }

    function pintarVentas() {
        const { agrupacion } = datos.periodo;
        const hayReembolsos = datos.ventas.some((g) => g.reembolsos > 0);
        $('ventas-nota').textContent = `Cobrado (pagos aprobados) por ${{ dia: 'día', semana: 'semana', mes: 'mes' }[agrupacion]}`
            + (hayReembolsos ? '; bajo la línea, lo reembolsado ese día.' : '.');

        const plural = (n, uno, varios) => `${n} ${n === 1 ? uno : varios}`;
        const items = datos.ventas.map((g) => {
            const lineas = [`${plural(g.boletos, 'boleto', 'boletos')} vendidos`];
            if (g.reembolsos > 0) {
                lineas.unshift(`Cobrado ${q.format(g.monto)}`, `Reembolsado −${q.format(g.reembolsos)}`);
                lineas.push(`${plural(g.devueltos, 'boleto devuelto', 'boletos devueltos')}`);
            }
            return {
                valor: g.monto,
                abajo: g.reembolsos,
                etiquetaX: etiquetaGrupo(g, agrupacion),
                descripcion: `${etiquetaGrupo(g, agrupacion, true)}: cobrado ${q.format(g.monto)}, reembolsado ${q.format(g.reembolsos)}, ${g.boletos} boletos`,
                tooltip: [g.reembolsos > 0 ? `Neto ${conSigno(g.neto)}` : q.format(g.monto), ...lineas, etiquetaGrupo(g, agrupacion, true)],
                clase: 'columna__barra--ventas',
                claseAbajo: 'columna__barra--perdida',
            };
        });
        const mayor = items.reduce((mejor, item, i) => (item.valor > items[mejor].valor ? i : mejor), 0);
        columnas($('grafica-ventas'), items, { vacio: 'Sin ventas en este período.', extremos: [mayor] });

        const tabla = crear('table', 'finanzas__datos');
        const cabecera = tabla.createTHead().insertRow();
        [{ dia: 'Día', semana: 'Semana', mes: 'Mes' }[agrupacion], 'Cobrado', 'Reembolsado', 'Neto', 'Boletos'].forEach((t) => cabecera.append(crear('th', '', t)));
        const cuerpo = tabla.createTBody();
        [...datos.ventas].reverse().forEach((g) => {
            const fila = cuerpo.insertRow();
            fila.append(
                crear('td', '', etiquetaGrupo(g, agrupacion, true)),
                crear('td', '', q.format(g.monto)),
                crear('td', '', g.reembolsos > 0 ? `−${q.format(g.reembolsos)}` : '—'),
                crear('td', '', q.format(g.neto)),
                crear('td', '', g.devueltos ? `${g.boletos} (−${g.devueltos})` : String(g.boletos))
            );
        });
        $('ventas-tabla').replaceChildren(tabla);
    }

    function pintarGanancias() {
        const eventos = datos.historial;
        const pocos = eventos.length <= 8;
        const items = eventos.map((e) => {
            const fecha = fechaDeEvento(e);
            const lineas = [e.nombre, fecha ? fechaEvento.format(fecha) : 'Sin fecha', `Ingresos netos ${q.format(e.ingresos_netos)} · Costos ${q.format(e.costos)}`];
            if (!e.tiene_costos) lineas.push('Sin costos registrados');
            return {
                valor: e.ganancia,
                // Con pocos eventos se ve el nombre; con muchos, la fecha
                etiquetaX: pocos ? (e.nombre.length > 11 ? `${e.nombre.slice(0, 10)}…` : e.nombre) : (fecha ? fechaEventoCorta.format(fecha) : ''),
                descripcion: `${e.nombre}: ${e.ganancia >= 0 ? 'ganancia' : 'pérdida'} de ${q.format(Math.abs(e.ganancia))}`,
                tooltip: [conSigno(e.ganancia), ...lineas],
                clase: e.ganancia >= 0 ? 'columna__barra--ganancia' : 'columna__barra--perdida',
            };
        });
        const indices = items.map((_, i) => i);
        const mayor = indices.reduce((a, i) => (items[i].valor > items[a].valor ? i : a), 0);
        const menor = indices.reduce((a, i) => (items[i].valor < items[a].valor ? i : a), 0);
        columnas($('grafica-ganancias'), items, {
            vacio: 'Ningún evento realizado en este período.',
            extremos: items.length ? [...new Set([mayor, ...(items[menor].valor < 0 ? [menor] : [])])] : [],
        });
    }

    // =====================================================
    // Tabla de rentabilidad (historial del período / próximos)
    // =====================================================
    const RESULTADOS = {
        ganancia: ['▲', 'Ganancia'],
        perdida: ['▼', 'Pérdida'],
        cubierto: ['✓', 'Costos cubiertos'],
        en_venta: ['●', 'En venta'],
        sin_costos: ['○', 'Sin costos'],
        cancelado: ['✕', 'Cancelado'],
    };

    function celdaEvento(e) {
        const celda = crear('td', 'rentabilidad__evento');
        const fecha = fechaDeEvento(e);
        celda.append(crear('strong', '', e.nombre), crear('span', '', fecha ? fechaEvento.format(fecha) : 'Sin fecha'));
        return celda;
    }

    function celdaResultado(e) {
        const [icono, texto] = RESULTADOS[e.resultado];
        const celda = crear('td');
        const pastilla = crear('span', 'rentabilidad__resultado', `${icono} ${texto}`);
        pastilla.dataset.resultado = e.resultado;
        celda.append(pastilla);
        return celda;
    }

    const numero = (texto, clase = '') => crear('td', `rentabilidad__numero ${clase}`.trim(), texto);

    function celdaEquilibrio(e) {
        const celda = crear('td', 'rentabilidad__equilibrio');
        if (!e.tiene_costos) {
            celda.append(crear('span', 'rentabilidad__aviso', 'Registra sus costos en "Editar"'));
            return celda;
        }
        if (!e.equilibrio_alcanzable) {
            celda.dataset.tono = 'mal';
            celda.append(crear('span', '', `⚠ Necesita ${e.equilibrio_boletos} boletos y solo hay ${e.lugares}`));
            return celda;
        }
        const faltan = Math.max(0, e.equilibrio_boletos - e.vendidos);
        const texto = faltan === 0 ? `✓ Cubiertos (${e.equilibrio_boletos} necesarios)` : `Faltan ${faltan} de ${e.equilibrio_boletos}`;
        const medidor = crear('div', 'rentabilidad__medidor');
        medidor.setAttribute('role', 'progressbar');
        medidor.setAttribute('aria-valuemin', '0');
        medidor.setAttribute('aria-valuemax', String(e.equilibrio_boletos));
        medidor.setAttribute('aria-valuenow', String(Math.min(e.vendidos, e.equilibrio_boletos)));
        medidor.setAttribute('aria-label', `Punto de equilibrio: ${texto}`);
        const relleno = crear('span');
        relleno.style.width = `${e.avance_equilibrio * 100}%`;
        medidor.append(relleno);
        celda.append(crear('span', '', texto), medidor);
        return celda;
    }

    function pintarRentabilidad() {
        const historial = vista === 'historial';
        const eventos = historial
            ? [...datos.historial].reverse()            // el más reciente primero
            : datos.proximos;                            // el más cercano primero

        $('pestana-historial').textContent = `Historial del período (${datos.historial.length})`;
        $('pestana-proximos').textContent = `Próximos eventos (${datos.proximos.length})`;
        panel.querySelectorAll('.finanzas__pestanas [role="tab"]').forEach((b) => b.setAttribute('aria-selected', String(b.dataset.vista === vista)));
        $('rentabilidad-tabla').setAttribute('aria-labelledby', historial ? 'pestana-historial' : 'pestana-proximos');
        $('rentabilidad-nota').textContent = historial
            ? 'Ganancia definitiva de los eventos que ya ocurrieron o se cancelaron. Ingresos netos = pagos aprobados − reembolsos − comisión.'
            : 'Aún no se sabe si darán ganancia: el punto de equilibrio dice cuántos boletos faltan para cubrir los costos.';

        if (!eventos.length) {
            $('rentabilidad-tabla').replaceChildren(crear('p', 'rentabilidad__vacio', historial ? 'Ningún evento realizado en este período.' : 'No hay eventos próximos a la venta.'));
            $('rentabilidad-mas').hidden = true;
            return;
        }

        const tabla = crear('table', 'rentabilidad__tabla');
        const cabecera = tabla.createTHead().insertRow();
        const titulos = historial
            ? ['Evento', 'Resultado', 'Boletos', 'Ingresos netos', 'Costos', 'Ganancia', 'Margen']
            : ['Evento', 'Boletos', 'Punto de equilibrio', 'Ingresos netos', 'Costos', 'Estado'];
        titulos.forEach((t, i) => cabecera.append(crear('th', i >= (historial ? 2 : 3) || (!historial && i === 1) ? 'rentabilidad__numero' : '', t)));

        const cuerpo = tabla.createTBody();
        eventos.slice(0, mostrados).forEach((e) => {
            const fila = cuerpo.insertRow();
            const boletos = numero(`${entero.format(e.vendidos)} / ${entero.format(e.lugares)}`);
            if (historial) {
                fila.append(
                    celdaEvento(e), celdaResultado(e), boletos,
                    numero(q.format(e.ingresos_netos)), numero(e.tiene_costos ? q.format(e.costos) : '—'),
                    numero(conSigno(e.ganancia), e.ganancia < 0 ? 'es-negativo' : ''),
                    numero(!e.tiene_costos ? 'sin costos' : e.margen === null ? '—' : pct.format(e.margen)),
                );
            } else {
                fila.append(
                    celdaEvento(e), boletos, celdaEquilibrio(e),
                    numero(q.format(e.ingresos_netos)), numero(e.tiene_costos ? q.format(e.costos) : '—'), celdaResultado(e),
                );
            }
        });

        const envoltura = crear('div', 'rentabilidad__desplazable');
        envoltura.append(tabla);
        $('rentabilidad-tabla').replaceChildren(envoltura);

        const quedan = eventos.length - mostrados;
        $('rentabilidad-mas').hidden = quedan <= 0;
        $('rentabilidad-mas').textContent = `Ver más · quedan ${quedan}`;
    }

    panel.querySelectorAll('.finanzas__pestanas [role="tab"]').forEach((boton) => boton.addEventListener('click', () => {
        vista = boton.dataset.vista;
        mostrados = POR_PAGINA;
        if (datos) pintarRentabilidad();
    }));
    $('rentabilidad-mas').addEventListener('click', () => {
        mostrados += POR_PAGINA;
        pintarRentabilidad();
    });

    // =====================================================
    // Carga
    // =====================================================
    function pintar() {
        pintarCifras();
        pintarVentas();
        pintarGanancias();
        pintarRentabilidad();
    }

    async function cargar() {
        controlador?.abort();
        controlador = new AbortController();
        const parametros = new URLSearchParams();
        if ($('finanzas-desde').value) parametros.set('desde', $('finanzas-desde').value);
        if ($('finanzas-hasta').value) parametros.set('hasta', $('finanzas-hasta').value);

        // Al recargar se conserva lo dibujado, atenuado (sin saltos)
        panel.setAttribute('aria-busy', 'true');
        $('finanzas-error').hidden = true;
        try {
            const respuesta = await fetch(`/api/admin/finanzas?${parametros}`, {
                headers: { Accept: 'application/json', Authorization: 'Bearer ' + localStorage.getItem('auth_token') },
                signal: controlador.signal,
            });
            if (respuesta.status === 403) {
                panel.hidden = true;   // solo el administrador ve las ganancias
                return;
            }
            const cuerpo = await respuesta.json().catch(() => ({}));
            if (!respuesta.ok) {
                throw new Error(cuerpo.errors ? Object.values(cuerpo.errors).flat()[0] : 'No se pudieron consultar las finanzas.');
            }
            datos = cuerpo.data;
            mostrados = POR_PAGINA;
            panel.hidden = false;
            $('finanzas-desde').value = datos.periodo.desde;
            $('finanzas-hasta').value = datos.periodo.hasta;
            pintar();
        } catch (error) {
            if (error.name === 'AbortError') return;
            $('finanzas-error').textContent = error.message;
            $('finanzas-error').hidden = false;
        } finally {
            panel.removeAttribute('aria-busy');
        }
    }

    // Se recarga cada vez que se entra al Resumen (con el período elegido)
    document.addEventListener('admin:recargar', (evento) => {
        if (evento.detail?.section === 'resumen') cargar();
    });

    // Las gráficas se redibujan si cambia su ancho
    const anchos = new Map();
    const observador = new ResizeObserver((entradas) => {
        if (!datos) return;
        const cambio = entradas.some((entrada) => {
            const ancho = Math.round(entrada.contentRect.width);
            const antes = anchos.get(entrada.target) || 0;
            anchos.set(entrada.target, ancho);
            return Math.abs(ancho - antes) > 8;
        });
        if (cambio) {
            pintarVentas();
            pintarGanancias();
        }
    });
    observador.observe($('grafica-ventas'));
    observador.observe($('grafica-ganancias'));
});
