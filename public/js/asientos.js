// Dibuja el PLANO de asientos de un evento a partir de GET /api/eventos/{id}/disponibilidad.
//
// El plano es un SVG: el escenario arriba y las filas como arcos concéntricos frente a él
// (como en los planos de arquitectura), divididas en bloques con pasillos. Cada butaca se gira
// para "mirar" al escenario. Las localidades generales (sin asiento) son una franja curva detrás.
//
// Lo usan dos vistas:
//   - modo 'cliente': las butacas disponibles se pueden elegir; las zonas generales tienen contador.
//   - modo 'admin':   solo lectura; se distingue libre / apartado (bloqueado) / vendido.

const MapaAsientos = {
    formatoMoneda: new Intl.NumberFormat('es-GT', { style: 'currency', currency: 'GTQ' }),

    moneda(valor) {
        return this.formatoMoneda.format(valor || 0);
    },

    // Medidas del plano (en unidades del SVG; el navegador lo escala a la pantalla)
    MEDIDAS: {
        butacaAncho: 20,
        butacaAlto: 17,
        paso: 27,          // distancia entre centros de butacas sobre el arco
        entreFilas: 31,
        entreZonas: 54,    // espacio extra entre localidades (incluye su rótulo)
        radioInicial: 150, // distancia del centro del escenario a la primera fila
        pasillo: 1.2,      // ancho de un pasillo, en "butacas"
        franjaGeneral: 74, // grosor de la franja de una localidad general
        margen: 14,
    },

    // estados de la vez anterior, para animar solo las butacas que cambiaron (otra persona las tomó)
    estadosPrevios: new Map(),

    /**
     * @param {HTMLElement} contenedor
     * @param {object} datos  respuesta de la API de disponibilidad
     * @param {object} opciones
     *   modo: 'cliente' | 'admin'
     *   seleccion: Set<int>        ids de inventario elegidos (cliente)
     *   cupos: Map<int,int>        localidad_id → cantidad elegida en zonas generales (cliente)
     *   puedeAgregar: () => bool   si aún se permite agregar lugares (máximo por compra)
     *   alAlternarAsiento(asiento, localidad), alCambiarCupo(localidad, nuevaCantidad)
     */
    dibujar(contenedor, datos, opciones) {
        const admin = opciones.modo === 'admin';
        const fragmento = document.createDocumentFragment();

        const plano = document.createElement('div');
        plano.className = 'plano';
        plano.appendChild(this.planoSvg(datos, opciones, admin));
        fragmento.appendChild(plano);

        const generales = datos.localidades.filter((l) => l.tipo !== 'numerada');
        if (generales.length) {
            const lista = document.createElement('div');
            lista.className = 'generales';
            generales.forEach((localidad) => lista.appendChild(this.controlGeneral(localidad, opciones, admin)));
            fragmento.appendChild(lista);
        }

        // la leyenda solo en el admin (explica apartado vs. vendido); el cliente ve el plano más grande
        if (admin) fragmento.appendChild(this.leyenda(admin));
        contenedor.replaceChildren(fragmento);
    },

    // ---------- El plano (SVG) ----------

    planoSvg(datos, opciones, admin) {
        const M = this.MEDIDAS;
        // xMinYMid: si sobra espacio a los lados, el plano se pega a la izquierda en vez de centrarse
        const svg = this.svg('svg', { class: 'plano__svg', role: 'group', 'aria-label': 'Plano del recinto',
            preserveAspectRatio: 'xMinYMid meet' });
        const guias = this.svg('g', { class: 'plano__guias' }, svg);
        const butacas = this.svg('g', {}, svg);
        const textos = this.svg('g', { class: 'plano__textos' }, svg);

        // límites del dibujo, para ajustar el viewBox al final
        const caja = { x1: Infinity, y1: Infinity, x2: -Infinity, y2: -Infinity };
        const extender = (x, y) => {
            caja.x1 = Math.min(caja.x1, x); caja.y1 = Math.min(caja.y1, y);
            caja.x2 = Math.max(caja.x2, x); caja.y2 = Math.max(caja.y2, y);
        };

        // Escenario: recto atrás, curvo hacia el público
        this.svg('path', { class: 'plano__escenario', d: 'M -170 -74 L 170 -74 L 170 -6 Q 0 74 -170 -6 Z' }, guias);
        this.texto(textos, 0, -26, 'ESCENARIO', 'plano__rotulo plano__rotulo--escenario');
        extender(-170, -74); extender(170, 40);

        let radio = M.radioInicial;

        datos.localidades.forEach((localidad) => {
            const color = localidad.color || '#c8b48c';

            if (localidad.tipo === 'numerada') {
                // agrupar por fila (A, B, C...) manteniendo el orden de la API
                const filas = new Map();
                localidad.asientos.forEach((a) => {
                    if (!filas.has(a.fila)) filas.set(a.fila, []);
                    filas.get(a.fila).push(a);
                });

                const radioInicioZona = radio;
                let mitadMaxima = 0;
                const grupoZona = this.svg('g', { style: `--color-zona: ${color}` }, butacas);

                filas.forEach((asientos, letra) => {
                    const mitad = this.filaSvg(grupoZona, textos, asientos, letra, radio, localidad, opciones, admin, extender);
                    mitadMaxima = Math.max(mitadMaxima, mitad);
                    radio += M.entreFilas;
                });

                const radioFinZona = radio - M.entreFilas;
                // contorno punteado del sector, como en un plano
                const margenAngulo = 34 / radioFinZona;
                const sectorR1 = radioInicioZona - 20;
                const sectorR2 = radioFinZona + 20;
                const sectorMitad = mitadMaxima + margenAngulo;
                this.svg('path', {
                    class: 'plano__sector',
                    style: `--color-zona: ${color}`,
                    d: this.sector(sectorR1, sectorR2, sectorMitad),
                }, guias);
                // el sector es más ancho que las butacas: sus esquinas también cuentan para el encuadre
                [[-sectorMitad, sectorR2], [sectorMitad, sectorR2], [0, sectorR2], [-sectorMitad, sectorR1], [sectorMitad, sectorR1]]
                    .forEach(([t, r]) => extender(r * Math.sin(t), r * Math.cos(t)));
                this.textoCurvo(svg, textos, radioFinZona + 42, `${localidad.nombre.toUpperCase()}  ·  ${this.moneda(localidad.precio)}`,
                    'plano__rotulo', color, `rotulo-${localidad.id}`);
                extender(0, radioFinZona + 48);

                radio = radioFinZona + M.entreZonas + 22;
            } else {
                // Localidad general: franja curva clickeable
                const r1 = radio;
                const r2 = radio + M.franjaGeneral;
                const mitad = Math.min(1.05, 300 / ((r1 + r2) / 2));
                const franja = this.svg('path', {
                    class: 'plano__general',
                    style: `--color-zona: ${color}`,
                    d: this.sector(r1, r2, mitad),
                }, guias);

                if (!admin) {
                    franja.setAttribute('tabindex', '0');
                    franja.setAttribute('role', 'button');
                    franja.setAttribute('aria-label', `${localidad.nombre}: elegir cantidad de lugares`);
                    const irAlContador = () => {
                        const control = document.getElementById(`general-${localidad.id}`);
                        control?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        control?.classList.remove('zona-general--resaltar');
                        void control?.offsetWidth;   // reinicia la animación
                        control?.classList.add('zona-general--resaltar');
                    };
                    franja.addEventListener('click', irAlContador);
                    franja.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); irAlContador(); } });
                }

                const centro = (r1 + r2) / 2;
                this.textoCurvo(svg, textos, centro - 4, localidad.nombre.toUpperCase(), 'plano__rotulo', color,
                    `rotulo-${localidad.id}`);
                this.textoCurvo(svg, textos, centro + 14,
                    admin ? `${localidad.vendidos} vendidos · ${localidad.disponibles} libres`
                          : `${localidad.disponibles} de ${localidad.capacidad} libres · ${this.moneda(localidad.precio)}`,
                    'plano__subrotulo', null, `subrotulo-${localidad.id}`);

                [[-mitad, r2], [mitad, r2], [0, r2], [-mitad, r1], [mitad, r1]].forEach(([t, r]) =>
                    extender(r * Math.sin(t), r * Math.cos(t)));
                radio = r2 + M.entreZonas;
            }
        });

        const m = M.margen;
        svg.setAttribute('viewBox', `${caja.x1 - m} ${caja.y1 - m} ${caja.x2 - caja.x1 + 2 * m} ${caja.y2 - caja.y1 + 2 * m}`);
        return svg;
    },

    /** Dibuja una fila como un arco, en bloques separados por pasillos. Devuelve su media apertura (radianes). */
    filaSvg(grupo, textos, asientos, letra, radio, localidad, opciones, admin, extender) {
        const M = this.MEDIDAS;
        const n = asientos.length;
        const bloques = n <= 8 ? 1 : n <= 16 ? 2 : 3;
        const porBloque = Math.ceil(n / bloques);
        const unidades = n + (bloques - 1) * M.pasillo;
        const pasoAngular = M.paso / radio;
        const mitad = (unidades * pasoAngular) / 2;

        let unidad = 0;
        asientos.forEach((asiento, i) => {
            if (i > 0 && i % porBloque === 0) unidad += M.pasillo;   // pasillo entre bloques
            const angulo = -mitad + (unidad + 0.5) * pasoAngular;
            unidad += 1;

            const x = radio * Math.sin(angulo);
            const y = radio * Math.cos(angulo);
            extender(x - 14, y - 14); extender(x + 14, y + 14);
            grupo.appendChild(this.butaca(asiento, localidad, x, y, angulo, opciones, admin));
        });

        // letra de la fila en los dos extremos
        const margenLetra = 0.85 * pasoAngular;
        [-mitad - margenLetra, mitad + margenLetra].forEach((angulo) => {
            const x = radio * Math.sin(angulo);
            const y = radio * Math.cos(angulo);
            this.texto(textos, x, y + 4, letra, 'plano__fila');
            extender(x - 10, y);
            extender(x + 10, y);
        });

        return mitad;
    },

    butaca(asiento, localidad, x, y, angulo, opciones, admin) {
        const M = this.MEDIDAS;
        const grados = (-angulo * 180) / Math.PI;   // girada para mirar al escenario
        const g = this.svg('g', {
            class: 'butaca',
            transform: `translate(${x.toFixed(1)} ${y.toFixed(1)}) rotate(${grados.toFixed(1)})`,
        });

        // área de toque invisible, más grande que la butaca (llena el espacio hasta la vecina):
        // en celular el plano se encoge y así sigue siendo fácil atinarle con el dedo
        this.svg('rect', { class: 'butaca__toque', x: -M.paso / 2, y: -M.entreFilas / 2,
            width: M.paso, height: M.entreFilas }, g);

        // asiento + respaldo (el respaldo queda del lado contrario al escenario)
        this.svg('rect', { class: 'butaca__cuerpo', x: -M.butacaAncho / 2, y: -M.butacaAlto / 2,
            width: M.butacaAncho, height: M.butacaAlto, rx: 5 }, g);
        this.svg('rect', { class: 'butaca__respaldo', x: -M.butacaAncho / 2 + 2, y: M.butacaAlto / 2 - 4,
            width: M.butacaAncho - 4, height: 3, rx: 1.5 }, g);
        const numero = this.svg('text', { class: 'butaca__numero', x: 0, y: 2.5 }, g);
        numero.textContent = asiento.numero;

        const etiqueta = `${localidad.nombre} · Fila ${asiento.fila} · Asiento ${asiento.numero}`;
        const titulo = this.svg('title', {}, g);

        if (admin) {
            g.classList.add(`butaca--${asiento.estado}`);
            titulo.textContent = `${etiqueta} · ${{ disponible: 'Libre', bloqueado: 'Apartado', vendido: 'Vendido' }[asiento.estado] || asiento.estado}`;
        } else {
            const elegida = opciones.seleccion.has(asiento.inventario_id);
            const libre = asiento.estado === 'disponible';
            if (elegida) g.classList.add('butaca--elegida');
            else if (!libre) g.classList.add('butaca--ocupada');

            titulo.textContent = libre || elegida ? `${etiqueta} · ${this.moneda(localidad.precio)}` : `${etiqueta} · Ocupado`;

            if (libre || elegida) {
                g.classList.add('butaca--interactiva');
                g.setAttribute('tabindex', '0');
                g.setAttribute('role', 'button');
                g.setAttribute('aria-pressed', String(elegida));
                g.setAttribute('aria-label', titulo.textContent);
                const elegir = () => opciones.alAlternarAsiento(asiento, localidad);
                g.addEventListener('click', elegir);
                g.addEventListener('keydown', (e) => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); elegir(); } });
            }
        }

        // latido si cambió desde la última actualización (p. ej. otra persona la acaba de apartar)
        const previo = this.estadosPrevios.get(asiento.inventario_id);
        if (previo && previo !== asiento.estado) g.classList.add('butaca--cambio');
        this.estadosPrevios.set(asiento.inventario_id, asiento.estado);

        return g;
    },

    // ---------- Controles de zonas generales (debajo del plano) ----------

    controlGeneral(localidad, opciones, admin) {
        const caja = document.createElement('div');
        caja.className = 'zona-general';
        caja.id = `general-${localidad.id}`;
        caja.style.setProperty('--color-zona', localidad.color || '#c8b48c');

        const titulo = document.createElement('div');
        titulo.className = 'zona-general__titulo';
        const nombre = document.createElement('strong');
        nombre.textContent = localidad.nombre;
        const info = document.createElement('span');
        info.textContent = admin
            ? `${localidad.vendidos} vendidos · ${localidad.bloqueados} apartados · ${localidad.disponibles} libres`
            : `${this.moneda(localidad.precio)} por lugar · ${localidad.disponibles} de ${localidad.capacidad} libres`;
        titulo.append(nombre, info);
        caja.appendChild(titulo);

        if (admin) {
            const porcentaje = (n) => (localidad.capacidad ? (n / localidad.capacidad) * 100 : 0);
            const barra = document.createElement('div');
            barra.className = 'barra';
            barra.innerHTML = `
                <span style="width:${porcentaje(localidad.vendidos)}%; background: var(--color-zona)"></span>
                <span style="width:${porcentaje(localidad.bloqueados)}%; background: var(--apartado)"></span>`;
            caja.appendChild(barra);
            return caja;
        }

        const cantidad = opciones.cupos.get(localidad.id) || 0;
        const contador = document.createElement('div');
        contador.className = 'contador';

        const menos = document.createElement('button');
        menos.type = 'button';
        menos.textContent = '−';
        menos.setAttribute('aria-label', `Quitar un lugar de ${localidad.nombre}`);
        menos.disabled = cantidad === 0;
        menos.addEventListener('click', () => opciones.alCambiarCupo(localidad, cantidad - 1));

        const valor = document.createElement('output');
        valor.textContent = cantidad;

        const mas = document.createElement('button');
        mas.type = 'button';
        mas.textContent = '+';
        mas.setAttribute('aria-label', `Agregar un lugar en ${localidad.nombre}`);
        mas.disabled = cantidad >= localidad.disponibles || !opciones.puedeAgregar();
        mas.addEventListener('click', () => opciones.alCambiarCupo(localidad, cantidad + 1));

        contador.append(menos, valor, mas);
        caja.appendChild(contador);
        return caja;
    },

    leyenda(admin) {
        const leyenda = document.createElement('div');
        leyenda.className = 'leyenda';
        const muestra = (clase, texto) => `<span><i class="muestra ${clase}"></i>${texto}</span>`;
        leyenda.innerHTML = admin
            ? muestra('', 'Libre') + muestra('muestra--apartada', 'Apartado (en pago)') + muestra('muestra--vendida', 'Vendido')
            : muestra('', 'Disponible') + muestra('muestra--elegida', 'Tu selección') + muestra('muestra--ocupada', 'Ocupado');
        return leyenda;
    },

    // ---------- Utilidades SVG ----------

    svg(etiqueta, atributos = {}, padre = null) {
        const el = document.createElementNS('http://www.w3.org/2000/svg', etiqueta);
        Object.entries(atributos).forEach(([k, v]) => el.setAttribute(k, v));
        if (padre) padre.appendChild(el);
        return el;
    },

    texto(padre, x, y, contenido, clase, color = null) {
        const t = this.svg('text', { x: x.toFixed(1), y: y.toFixed(1), class: clase }, padre);
        if (color) t.style.setProperty('--color-zona', color);
        t.textContent = contenido;
        return t;
    },

    /**
     * Texto que sigue la curva de las filas (en forma de U): se escribe sobre un arco invisible
     * con el mismo centro que el escenario, centrado abajo.
     */
    textoCurvo(svg, padre, radio, contenido, clase, color, id) {
        let defs = svg.querySelector('defs');
        if (!defs) defs = this.svg('defs', {}, svg);

        const mitad = 1.2;   // el arco guía es más largo que el texto; el texto queda centrado en él
        const p = (t) => `${(radio * Math.sin(t)).toFixed(1)} ${(radio * Math.cos(t)).toFixed(1)}`;
        this.svg('path', { id, d: `M ${p(-mitad)} A ${radio} ${radio} 0 0 0 ${p(mitad)}` }, defs);

        const texto = this.svg('text', { class: clase }, padre);
        if (color) texto.style.setProperty('--color-zona', color);
        const camino = this.svg('textPath', { href: `#${id}`, startOffset: '50%' }, texto);
        camino.textContent = contenido;
        return texto;
    },

    /** Sector de anillo entre dos radios, abierto ±mitad (radianes) alrededor de "hacia abajo". */
    sector(r1, r2, mitad) {
        const p = (r, t) => `${(r * Math.sin(t)).toFixed(1)} ${(r * Math.cos(t)).toFixed(1)}`;
        return `M ${p(r2, -mitad)} A ${r2} ${r2} 0 0 0 ${p(r2, mitad)} `
             + `L ${p(r1, mitad)} A ${r1} ${r1} 0 0 1 ${p(r1, -mitad)} Z`;
    },
};
