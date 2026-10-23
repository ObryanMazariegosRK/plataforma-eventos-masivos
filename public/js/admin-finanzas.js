'use strict';

// Panel → Resumen → "Ventas y ganancias" (solo administrador; GET /api/admin/finanzas).
// Cifras del período, columnas de ventas por día (SVG, con tooltip y tabla) y la rentabilidad
// de cada evento: ingresos netos contra costos y, si aún no ocurre, su punto de equilibrio.
// Colores de las series validados (dataviz): ver --t-serie-* en admin-tema.css.

document.addEventListener('DOMContentLoaded', () => {
    const panel = document.getElementById('finanzas-panel');
    if (!panel) return;

    const $ = (id) => document.getElementById(id);
    const SVG = 'http://www.w3.org/2000/svg';
    const zona = { timeZone: 'America/Guatemala' };
    const q = new Intl.NumberFormat('es-GT', { style: 'currency', currency: 'GTQ' });
    const qEntero = new Intl.NumberFormat('es-GT', { style: 'currency', currency: 'GTQ', maximumFractionDigits: 0 });
    const qCorto = (valor) => (valor >= 10000 ? qEntero.format(valor / 1000).replace(/\s?$/, '') + ' mil' : qEntero.format(valor));
    const entero = new Intl.NumberFormat('es-GT');
    const pct = new Intl.NumberFormat('es-GT', { style: 'percent', maximumFractionDigits: 1 });
    // Los días llegan como "2026-10-22" (ya en hora de Guatemala): se leen a mediodía UTC
    const diaDe = (texto) => new Date(`${texto}T12:00:00Z`);
    const diaCorto = new Intl.DateTimeFormat('es-GT', { timeZone: 'UTC', day: 'numeric', month: 'short' });
    const diaLargo = new Intl.DateTimeFormat('es-GT', { timeZone: 'UTC', weekday: 'long', day: 'numeric', month: 'long' });
    const fechaEvento = new Intl.DateTimeFormat('es-GT', { ...zona, day: 'numeric', month: 'short', year: 'numeric' });
    const mesActual = new Intl.DateTimeFormat('es-GT', { ...zona, month: 'long' }).format(new Date());

    let datos = null;
    let todosLosEventos = false;

    const crear = (etiqueta, clase, texto) => {
        const nodo = document.createElement(etiqueta);
        if (clase) nodo.className = clase;
        if (texto !== undefined) nodo.textContent = texto;
        return nodo;
    };
    const svg = (etiqueta, atributos = {}) => {
        const nodo = document.createElementNS(SVG, etiqueta);
        Object.entries(atributos).forEach(([k, v]) => nodo.setAttribute(k, v));
        return nodo;
    };
    const conSigno = (monto) => (monto > 0 ? '+' : monto < 0 ? '−' : '') + q.format(Math.abs(monto));

    // ---- Cifras ----
    function cifra(etiqueta, valor, nota, tono) {
        const tarjeta = crear('div', 'finanzas__cifra');
        const valorNodo = crear('strong', 'finanzas__valor', valor);
        tarjeta.append(crear('span', 'finanzas__etiqueta', etiqueta), valorNodo);
        if (nota) {
            const notaNodo = crear('span', 'finanzas__variacion', nota);
            if (tono) notaNodo.dataset.tono = tono;
            tarjeta.append(notaNodo);
        }
        return tarjeta;
    }

    function pintarCifras() {
        const t = datos.totales;
        let variacion = 'Sin ventas en los ' + datos.periodo.dias + ' días anteriores';
        let tono = null;
        if (t.variacion !== null) {
            const sube = t.variacion >= 0;
            variacion = `${sube ? '▲' : '▼'} ${pct.format(Math.abs(t.variacion))} frente a los ${datos.periodo.dias} días anteriores`;
            tono = sube ? 'bien' : 'mal';
        }
        let gananciaMes = t.eventos_mes
            ? `${t.eventos_mes} ${t.eventos_mes === 1 ? 'evento realizado' : 'eventos realizados'} en ${mesActual}`
            : `Aún no hay eventos realizados en ${mesActual}`;
        if (t.eventos_mes_sin_costos) {
            gananciaMes += ` · ${t.eventos_mes_sin_costos} sin costos registrados`;
        }

        $('finanzas-cifras').replaceChildren(
            cifra(`Ventas · últimos ${datos.periodo.dias} días`, q.format(t.ventas), variacion, tono),
            cifra('Boletos vendidos', entero.format(t.boletos), `en los últimos ${datos.periodo.dias} días`),
            cifra('Ganancia del mes', conSigno(t.ganancia_mes), gananciaMes, t.ganancia_mes < 0 ? 'mal' : null),
            cifra('Ganancia acumulada', conSigno(t.ganancia_acumulada), 'de todos los eventos ya realizados', t.ganancia_acumulada < 0 ? 'mal' : null),
        );

        const desde = diaCorto.format(diaDe(datos.periodo.desde));
        const hasta = diaCorto.format(diaDe(datos.periodo.hasta));
        $('finanzas-periodo').textContent = `Del ${desde} al ${hasta} · hora de Guatemala · comisión de pasarela ${pct.format(datos.comision_pasarela)}`;
    }

    // ---- Ventas por día (columnas) ----
    function escalaLimpia(maximo) {
        if (maximo <= 0) return { tope: 100, paso: 25 };
        const bruto = maximo / 4;
        const potencia = 10 ** Math.floor(Math.log10(bruto));
        const paso = [1, 2, 2.5, 5, 10].map((m) => m * potencia).find((p) => p >= bruto);
        return { tope: paso * Math.ceil(maximo / paso), paso };
    }

    function pintarVentas() {
        const contenedor = $('grafica-ventas');
        const dias = datos.ventas_diarias;
        const ancho = Math.max(320, contenedor.clientWidth || 600);
        const alto = 230;
        const m = { arriba: 22, derecha: 8, abajo: 26, izquierda: 58 };
        const areaAncho = ancho - m.izquierda - m.derecha;
        const areaAlto = alto - m.arriba - m.abajo;
        const { tope, paso } = escalaLimpia(Math.max(...dias.map((d) => d.monto)));
        const banda = areaAncho / dias.length;
        const grosor = Math.min(24, Math.max(4, banda - 4));   // deja aire entre columnas
        const y = (valor) => m.arriba + areaAlto - (valor / tope) * areaAlto;

        const lienzo = svg('svg', { viewBox: `0 0 ${ancho} ${alto}`, width: ancho, height: alto, role: 'img', 'aria-labelledby': 'ventas-titulo' });

        // Rejilla y eje Y (montos redondos)
        for (let valor = 0; valor <= tope + 0.001; valor += paso) {
            lienzo.append(
                svg('line', { x1: m.izquierda, x2: ancho - m.derecha, y1: y(valor), y2: y(valor), class: valor === 0 ? 'eje' : 'rejilla' }),
                Object.assign(svg('text', { x: m.izquierda - 8, y: y(valor) + 4, class: 'eje-texto', 'text-anchor': 'end' }), { textContent: qCorto(valor) }),
            );
        }

        const maximo = dias.reduce((mejor, d, i) => (d.monto > dias[mejor].monto ? i : mejor), 0);
        dias.forEach((dia, i) => {
            const centro = m.izquierda + banda * i + banda / 2;

            // Fechas cada 5 días (y el último), para no amontonarlas
            if (i % 5 === 0 || i === dias.length - 1) {
                lienzo.append(Object.assign(
                    svg('text', { x: centro, y: alto - 8, class: 'eje-texto', 'text-anchor': 'middle' }),
                    { textContent: diaCorto.format(diaDe(dia.dia)) },
                ));
            }

            const grupo = svg('g', { class: 'columna', tabindex: '0', role: 'img' });
            grupo.setAttribute('aria-label', `${diaLargo.format(diaDe(dia.dia))}: ${q.format(dia.monto)}, ${dia.boletos} boletos`);

            if (dia.monto > 0) {
                const x = centro - grosor / 2;
                const tope_ = y(dia.monto);
                const base = y(0);
                const radio = Math.min(4, (base - tope_) / 2, grosor / 2);
                // Esquinas redondeadas solo arriba; la base queda recta sobre el eje
                grupo.append(svg('path', {
                    class: 'columna__barra',
                    d: `M${x},${base} V${tope_ + radio} Q${x},${tope_} ${x + radio},${tope_} H${x + grosor - radio} Q${x + grosor},${tope_} ${x + grosor},${tope_ + radio} V${base} Z`,
                }));
            }
            // Zona sensible: toda la franja del día, no solo la barra
            grupo.append(svg('rect', { class: 'columna__zona', x: m.izquierda + banda * i, y: m.arriba, width: banda, height: areaAlto }));
            grupo.addEventListener('pointerenter', () => mostrarTooltip(dia, centro, y(dia.monto)));
            grupo.addEventListener('focus', () => mostrarTooltip(dia, centro, y(dia.monto)));
            grupo.addEventListener('pointerleave', ocultarTooltip);
            grupo.addEventListener('blur', ocultarTooltip);
            lienzo.append(grupo);

            // Etiqueta directa solo en el día de mayores ventas
            if (i === maximo && dia.monto > 0) {
                lienzo.append(Object.assign(
                    svg('text', { x: Math.min(Math.max(centro, m.izquierda + 30), ancho - 30), y: y(dia.monto) - 7, class: 'valor-texto', 'text-anchor': 'middle' }),
                    { textContent: qCorto(dia.monto) },
                ));
            }
        });

        const tooltip = crear('div', 'grafica-tooltip');
        tooltip.hidden = true;
        tooltip.setAttribute('role', 'status');
        contenedor.replaceChildren(lienzo, tooltip);

        if (!dias.some((d) => d.monto > 0)) {
            contenedor.append(crear('p', 'grafica-vacia', 'Sin ventas en este período.'));
        }
    }

    function mostrarTooltip(dia, x, yTope) {
        const tooltip = $('grafica-ventas').querySelector('.grafica-tooltip');
        tooltip.replaceChildren(
            crear('strong', '', q.format(dia.monto)),
            crear('span', '', `${dia.boletos} ${dia.boletos === 1 ? 'boleto' : 'boletos'}`),
            crear('span', 'grafica-tooltip__dia', diaLargo.format(diaDe(dia.dia))),
        );
        tooltip.hidden = false;
        const ancho = $('grafica-ventas').clientWidth;
        tooltip.style.left = `${Math.min(Math.max(x, 70), ancho - 70)}px`;
        tooltip.style.top = `${Math.max(yTope - 8, 10)}px`;
    }

    function ocultarTooltip() {
        const tooltip = $('grafica-ventas').querySelector('.grafica-tooltip');
        if (tooltip) tooltip.hidden = true;
    }

    function pintarTablaVentas() {
        const tabla = crear('table', 'finanzas__datos');
        const cabecera = tabla.createTHead().insertRow();
        ['Día', 'Ventas', 'Boletos'].forEach((t) => cabecera.append(crear('th', '', t)));
        const cuerpo = tabla.createTBody();
        [...datos.ventas_diarias].reverse().forEach((d) => {
            const fila = cuerpo.insertRow();
            fila.append(crear('td', '', diaLargo.format(diaDe(d.dia))), crear('td', '', q.format(d.monto)), crear('td', '', String(d.boletos)));
        });
        $('ventas-tabla').replaceChildren(tabla);
    }

    // ---- Rentabilidad por evento ----
    const RESULTADOS = {
        ganancia: ['▲', 'Ganancia'],
        perdida: ['▼', 'Pérdida'],
        cubierto: ['✓', 'Costos cubiertos'],
        en_venta: ['●', 'En venta'],
        sin_costos: ['○', 'Sin costos'],
        cancelado: ['✕', 'Cancelado'],
    };

    function barra(clase, etiqueta, valor, escala) {
        const fila = crear('div', 'rentabilidad__fila');
        const pista = crear('div', 'rentabilidad__pista');
        const relleno = crear('span', `rentabilidad__barra ${clase}`);
        relleno.style.width = `${escala > 0 ? Math.max(0, valor) / escala * 100 : 0}%`;
        pista.append(relleno);
        fila.append(crear('span', 'rentabilidad__etiqueta', etiqueta), pista, crear('span', 'rentabilidad__monto', q.format(valor)));
        return fila;
    }

    function equilibrio(evento) {
        const caja = crear('div', 'rentabilidad__equilibrio');

        if (evento.costos <= 0) {
            caja.append(crear('span', 'rentabilidad__aviso', 'Sin costos registrados: agrégalos en "Editar" para calcular la ganancia.'));
            return caja;
        }
        if (!evento.equilibrio_alcanzable) {
            caja.dataset.tono = 'mal';
            caja.append(crear('span', 'rentabilidad__aviso', `⚠ Ni vendiendo los ${evento.lugares} lugares se cubren los costos: se necesitan ${evento.equilibrio_boletos} boletos.`));
            return caja;
        }

        const faltan = Math.max(0, evento.equilibrio_boletos - evento.vendidos);
        const texto = faltan === 0
            ? `✓ Costos cubiertos con ${evento.vendidos} de ${evento.equilibrio_boletos} boletos necesarios`
            : `Punto de equilibrio: faltan ${faltan} boletos (${evento.vendidos} de ${evento.equilibrio_boletos})`;
        const medidor = crear('div', 'rentabilidad__medidor');
        medidor.setAttribute('role', 'progressbar');
        medidor.setAttribute('aria-valuemin', '0');
        medidor.setAttribute('aria-valuemax', String(evento.equilibrio_boletos));
        medidor.setAttribute('aria-valuenow', String(Math.min(evento.vendidos, evento.equilibrio_boletos)));
        medidor.setAttribute('aria-label', texto);
        const relleno = crear('span');
        relleno.style.width = `${evento.avance_equilibrio * 100}%`;
        medidor.append(relleno);
        caja.append(crear('span', '', texto), medidor);
        return caja;
    }

    function pintarRentabilidad() {
        const lista = $('rentabilidad-lista');
        // Primero los que vienen (por fecha), luego los ya realizados (el más reciente primero)
        const proximos = datos.eventos.filter((e) => !e.realizado);
        const realizados = datos.eventos.filter((e) => e.realizado).reverse();
        const ordenados = [...proximos, ...realizados];
        const visibles = todosLosEventos ? ordenados : ordenados.slice(0, 6);
        const escala = Math.max(1, ...visibles.flatMap((e) => [e.ingresos_netos, e.costos]));

        if (!ordenados.length) {
            lista.replaceChildren(crear('li', 'rentabilidad__vacio', 'Todavía no hay eventos publicados.'));
            return;
        }

        lista.replaceChildren(...visibles.map((evento) => {
            const item = crear('li', 'rentabilidad__evento');
            const [icono, texto] = RESULTADOS[evento.resultado];

            const cabeza = crear('div', 'rentabilidad__cabeza');
            const titulo = crear('div', 'rentabilidad__titulo');
            titulo.append(
                crear('strong', '', evento.nombre),
                crear('span', 'rentabilidad__fecha', evento.fecha_evento ? fechaEvento.format(new Date(evento.fecha_evento.replace(' ', 'T') + 'Z')) : 'Sin fecha'),
            );
            const estado = crear('span', 'rentabilidad__resultado', `${icono} ${texto}`);
            estado.dataset.resultado = evento.resultado;

            const ganancia = crear('div', 'rentabilidad__ganancia');
            ganancia.append(
                crear('strong', '', conSigno(evento.ganancia)),
                crear('span', '', !evento.tiene_costos ? 'sin costos registrados'
                    : evento.realizado ? (evento.margen !== null ? `margen ${pct.format(evento.margen)}` : 'sin ventas') : 'por ahora'),
            );
            cabeza.append(titulo, estado, ganancia);

            item.append(
                cabeza,
                barra('rentabilidad__barra--ingresos', 'Ingresos netos', evento.ingresos_netos, escala),
                barra('rentabilidad__barra--costos', 'Costos', evento.costos, escala),
            );
            if (!evento.realizado && evento.resultado !== 'cancelado') {
                item.append(equilibrio(evento));
            }
            return item;
        }));

        if (ordenados.length > 6) {
            const boton = crear('button', 'rentabilidad__mas', todosLosEventos ? 'Ver menos' : `Ver los ${ordenados.length} eventos`);
            boton.type = 'button';
            boton.addEventListener('click', () => {
                todosLosEventos = !todosLosEventos;
                pintarRentabilidad();
            });
            const contenedor = crear('li', 'rentabilidad__pie');
            contenedor.append(boton);
            lista.append(contenedor);
        }
    }

    function pintar() {
        if (!datos) return;
        pintarCifras();
        pintarVentas();
        pintarTablaVentas();
        pintarRentabilidad();
    }

    async function cargar() {
        panel.setAttribute('aria-busy', 'true');
        try {
            const respuesta = await fetch('/api/admin/finanzas', {
                headers: { Accept: 'application/json', Authorization: 'Bearer ' + localStorage.getItem('auth_token') },
            });
            if (respuesta.status === 403) {
                panel.hidden = true;   // solo el administrador ve las ganancias
                return;
            }
            if (!respuesta.ok) throw new Error();
            datos = (await respuesta.json()).data;
            panel.hidden = false;
            pintar();
        } catch {
            $('finanzas-periodo').textContent = 'No se pudieron consultar las finanzas. Inténtalo de nuevo.';
        } finally {
            panel.removeAttribute('aria-busy');
        }
    }

    // Se recarga cada vez que se entra al Resumen; la gráfica se redibuja si cambia el ancho
    document.addEventListener('admin:recargar', (evento) => {
        if (evento.detail?.section === 'resumen') cargar();
    });
    let ultimoAncho = 0;
    new ResizeObserver(([entrada]) => {
        const ancho = Math.round(entrada.contentRect.width);
        if (datos && Math.abs(ancho - ultimoAncho) > 8) {
            ultimoAncho = ancho;
            pintarVentas();
        }
    }).observe($('grafica-ventas'));
});
