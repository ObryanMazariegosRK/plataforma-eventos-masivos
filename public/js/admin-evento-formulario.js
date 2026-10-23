'use strict';

// Formulario de evento (panel → Eventos). Cuatro ayudas que trabajan sobre el formulario de admin.js:
//   1. Fotos: tres casillas que suben la foto al elegirla (POST /api/admin/eventos/imagenes) y
//      dejan su URL en el campo oculto que admin.js envía (imagen_url, imagen_url_2, imagen_url_3).
//   2. Plano: "Ver plano del recinto" dibuja el recinto elegido con el mismo plano de la venta.
//   3. Precios sugeridos: con los costos, el margen deseado y la ocupación esperada sugiere el
//      precio de cada localidad y proyecta ingresos, ganancia y punto de equilibrio.
//   4. Borrador: lo que se escribe en un evento NUEVO se guarda en el navegador y se recupera al
//      volver, hasta guardarlo o descartarlo.
// admin.js avisa con "admin:formulario-evento" (al abrir) y "admin:evento-guardado".

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('evento-form');
    if (!form) return;

    const $ = (id) => document.getElementById(id);
    const q = new Intl.NumberFormat('es-GT', { style: 'currency', currency: 'GTQ' });
    const qEntero = new Intl.NumberFormat('es-GT', { style: 'currency', currency: 'GTQ', maximumFractionDigits: 0 });
    const pct = new Intl.NumberFormat('es-GT', { style: 'percent', maximumFractionDigits: 0 });
    const crear = (etiqueta, clase, texto) => {
        const nodo = document.createElement(etiqueta);
        if (clase) nodo.className = clase;
        if (texto !== undefined) nodo.textContent = texto;
        return nodo;
    };
    const token = () => localStorage.getItem('auth_token');

    let modoNuevo = false;          // el borrador solo existe para eventos nuevos
    let restaurando = false;        // mientras se recupera el borrador no se vuelve a guardar

    // =====================================================
    // 1. Fotos
    // =====================================================
    const MAX_MB = Number((document.querySelector('.evento-fotos legend span')?.textContent.match(/(\d+)\s*MB/) || [])[1] || 8);
    const casillas = [...form.querySelectorAll('.evento-foto')];
    const subiendo = new Set();

    function mostrarFoto(casilla) {
        const url = casilla.querySelector('input[type="hidden"]').value;
        const vista = casilla.querySelector('.evento-foto__vista');
        casilla.classList.toggle('tiene-foto', Boolean(url));
        vista.hidden = !url;
        if (url) vista.src = url;
        else vista.removeAttribute('src');
    }

    async function subirFoto(casilla, archivo) {
        const estado = casilla.querySelector('.evento-foto__estado');
        if (!/^image\/(jpeg|png|webp)$/.test(archivo.type)) {
            estado.textContent = 'Usa una foto JPG, PNG o WebP.';
            return;
        }
        if (archivo.size > MAX_MB * 1024 * 1024) {
            estado.textContent = `La foto pesa más de ${MAX_MB} MB.`;
            return;
        }

        const datos = new FormData();
        datos.append('imagen', archivo);
        casilla.classList.add('subiendo');
        estado.textContent = 'Subiendo…';
        subiendo.add(casilla);

        try {
            const respuesta = await fetch('/api/admin/eventos/imagenes', {
                method: 'POST',
                headers: { Accept: 'application/json', Authorization: 'Bearer ' + token() },
                body: datos,
            });
            const cuerpo = await respuesta.json().catch(() => ({}));
            if (!respuesta.ok) {
                throw new Error(cuerpo.errors ? Object.values(cuerpo.errors).flat()[0] : (cuerpo.message || 'No se pudo subir la foto.'));
            }
            casilla.querySelector('input[type="hidden"]').value = cuerpo.data.url;
            estado.textContent = '';
            $('evento-fotos-mensaje').hidden = true;
            mostrarFoto(casilla);
            guardarBorrador();
        } catch (error) {
            estado.textContent = error.message;
        } finally {
            subiendo.delete(casilla);
            casilla.classList.remove('subiendo');
        }
    }

    casillas.forEach((casilla) => {
        const archivo = casilla.querySelector('.evento-foto__archivo');
        const zona = casilla.querySelector('.evento-foto__zona');
        archivo.addEventListener('change', () => {
            if (archivo.files[0]) subirFoto(casilla, archivo.files[0]);
            archivo.value = '';
        });
        zona.addEventListener('dragover', (evento) => {
            evento.preventDefault();
            zona.classList.add('arrastrando');
        });
        zona.addEventListener('dragleave', () => zona.classList.remove('arrastrando'));
        zona.addEventListener('drop', (evento) => {
            evento.preventDefault();
            zona.classList.remove('arrastrando');
            if (evento.dataTransfer.files[0]) subirFoto(casilla, evento.dataTransfer.files[0]);
        });
    });

    // Antes de que admin.js guarde: las tres fotos son obligatorias (los campos ocultos no los
    // revisa el navegador). Va en fase de captura para frenar el envío a tiempo.
    form.addEventListener('submit', (evento) => {
        const mensaje = $('evento-fotos-mensaje');
        const faltan = casillas.filter((c) => !c.querySelector('input[type="hidden"]').value);
        let texto = '';
        if (subiendo.size) texto = 'Espera a que terminen de subirse las fotos.';
        else if (faltan.length) texto = faltan.length === 1 ? 'Falta subir una foto del evento.' : `Faltan ${faltan.length} fotos del evento: las tres son obligatorias.`;
        if (!texto) return;

        evento.preventDefault();
        evento.stopImmediatePropagation();
        mensaje.textContent = texto;
        mensaje.hidden = false;
        $('evento-fotos').scrollIntoView({ behavior: 'smooth', block: 'center' });
    }, true);

    // =====================================================
    // 2. Plano del recinto
    // =====================================================
    const recinto = $('evento-recinto');
    const botonPlano = $('evento-ver-plano');
    const panelPlano = $('evento-plano');

    function cerrarPlano() {
        panelPlano.hidden = true;
        botonPlano.setAttribute('aria-expanded', 'false');
        botonPlano.textContent = 'Ver plano del recinto';
    }

    let datosPlano = null;

    // Cada zona del plano muestra el precio escrito o, si falta, el sugerido
    function dibujarPlano() {
        if (!datosPlano || panelPlano.hidden) return;
        const sugeridos = calcularSugeridos();
        const precios = new Map(camposPrecio().map((c) => [String(c.dataset.localidadId), Number(c.value) || sugeridos.get(c) || null]));
        const datos = {
            ...datosPlano,
            localidades: datosPlano.localidades.map((l) => ({ ...l, precio: precios.get(String(l.id)) ?? null })),
        };
        MapaAsientos.dibujar($('evento-plano-mapa'), datos, { modo: 'admin' });
        // En el formulario el plano va centrado (en la vista de asientos se alinea a la izquierda)
        $('evento-plano-mapa').querySelector('svg')?.setAttribute('preserveAspectRatio', 'xMidYMid meet');
    }

    async function abrirPlano() {
        panelPlano.hidden = false;
        botonPlano.setAttribute('aria-expanded', 'true');
        botonPlano.textContent = 'Ocultar plano';
        const mapa = $('evento-plano-mapa');
        if (mapa.dataset.recinto === recinto.value) return;

        mapa.replaceChildren(crear('p', 'evento-plano__estado', 'Cargando el plano…'));
        try {
            const respuesta = await fetch(`/api/admin/eventos/recintos/${encodeURIComponent(recinto.value)}/plano`, {
                headers: { Accept: 'application/json', Authorization: 'Bearer ' + token() },
            });
            const cuerpo = await respuesta.json().catch(() => ({}));
            if (!respuesta.ok) throw new Error(cuerpo.message || 'No se pudo cargar el plano.');
            datosPlano = cuerpo.data;
            mapa.dataset.recinto = recinto.value;
            dibujarPlano();
        } catch (error) {
            mapa.replaceChildren(crear('p', 'evento-plano__estado', error.message));
        }
    }

    botonPlano.addEventListener('click', () => (panelPlano.hidden ? abrirPlano() : cerrarPlano()));
    recinto.addEventListener('change', () => {
        botonPlano.disabled = !recinto.value;
        $('evento-plano-mapa').dataset.recinto = '';
        if (!panelPlano.hidden && recinto.value) abrirPlano();
        else cerrarPlano();
    });

    // =====================================================
    // 3. Precios sugeridos
    // =====================================================
    const panelPrecios = $('evento-localidades-panel');
    const lista = $('evento-localidades-lista');
    const comision = Number(panelPrecios.dataset.comision || 0.035);

    const sugerencia = crear('div', 'precios-sugerencia');
    sugerencia.innerHTML = `
        <div class="precios-sugerencia__controles">
            <label>Margen deseado <output id="sugerencia-margen-valor">30 %</output>
                <input type="range" id="sugerencia-margen" min="0" max="60" step="5" value="30"></label>
            <label>Ocupación esperada <output id="sugerencia-ocupacion-valor">70 %</output>
                <input type="range" id="sugerencia-ocupacion" min="30" max="100" step="5" value="70"></label>
            <button type="button" class="precios-sugerencia__usar" id="sugerencia-usar">Usar los precios sugeridos</button>
        </div>
        <p class="precios-sugerencia__proyeccion" id="sugerencia-proyeccion" aria-live="polite"></p>`;
    lista.before(sugerencia);

    // Peso de cada zona: las de adelante valen más que el balcón o la zona general
    function peso(input) {
        const nombre = (input.dataset.nombre || '').toLowerCase();
        if (/vip/.test(nombre)) return 1.6;
        if (/platea|palco|preferen/.test(nombre)) return 1.25;
        if (/balc|grader|general|popular/.test(nombre)) return 1;
        return input.dataset.tipo === 'numerada' ? 1.15 : 1;
    }

    const camposPrecio = () => [...lista.querySelectorAll('input.evento-localidad-precio')];
    const redondearA5 = (valor) => Math.max(5, Math.ceil(valor / 5) * 5);

    function calcularSugeridos() {
        const costos = window.AdminCostos?.total() || 0;
        const margen = Number($('sugerencia-margen').value) / 100;
        const ocupacion = Number($('sugerencia-ocupacion').value) / 100;
        const campos = camposPrecio();
        // Ingresos necesarios: después de la comisión deben cubrir los costos y dejar el margen
        const ingresos = costos / Math.max(0.05, 1 - comision - margen);
        const ponderado = campos.reduce((suma, c) => suma + Number(c.dataset.capacidad || 0) * peso(c), 0);
        const base = ponderado > 0 ? ingresos / (ocupacion * ponderado) : 0;

        return new Map(campos.map((c) => [c, costos > 0 && base > 0 ? redondearA5(base * peso(c)) : null]));
    }

    function pintarSugerencias() {
        const campos = camposPrecio();
        const costos = window.AdminCostos?.total() || 0;
        $('sugerencia-margen-valor').textContent = `${$('sugerencia-margen').value} %`;
        $('sugerencia-ocupacion-valor').textContent = `${$('sugerencia-ocupacion').value} %`;
        sugerencia.hidden = !campos.length;
        if (!campos.length) return;

        const sugeridos = calcularSugeridos();
        const editables = campos.some((c) => !c.disabled);
        $('sugerencia-usar').hidden = !editables || costos <= 0;

        campos.forEach((campo) => {
            const fila = campo.closest('.evento-precio-fila') || campo.parentElement;
            let chip = fila.querySelector('.precio-sugerido');
            if (!chip) {
                chip = crear('small', 'precio-sugerido');
                fila.append(chip);
            }
            const valor = sugeridos.get(campo);
            chip.replaceChildren();
            if (valor === null || campo.disabled) {
                chip.hidden = true;
                return;
            }
            chip.hidden = false;
            const usar = crear('button', '', 'Usar');
            usar.type = 'button';
            usar.addEventListener('click', () => {
                campo.value = valor.toFixed(2);
                campo.dispatchEvent(new Event('input', { bubbles: true }));
            });
            chip.append(document.createTextNode(`Sugerido ${qEntero.format(valor)} · `), usar);
        });

        pintarProyeccion(sugeridos);
    }

    // Con los precios escritos (o los sugeridos donde falten): ingresos, ganancia y equilibrio
    function pintarProyeccion(sugeridos) {
        const salida = $('sugerencia-proyeccion');
        const costos = window.AdminCostos?.total() || 0;
        const campos = camposPrecio();
        if (costos <= 0) {
            salida.textContent = 'Llena los costos del evento (arriba) para ver los precios sugeridos.';
            salida.dataset.tono = '';
            return;
        }

        const ocupacion = Number($('sugerencia-ocupacion').value) / 100;
        const aforo = campos.reduce((suma, c) => suma + Number(c.dataset.capacidad || 0), 0);
        const lleno = campos.reduce((suma, c) => suma + Number(c.dataset.capacidad || 0) * (Number(c.value) || sugeridos.get(c) || 0), 0);
        const ingresos = lleno * ocupacion;
        const ganancia = ingresos * (1 - comision) - costos;
        const precioPromedio = aforo ? lleno / aforo : 0;
        const equilibrio = precioPromedio > 0 ? Math.ceil(costos / (precioPromedio * (1 - comision))) : 0;
        const vendidos = Math.round(aforo * ocupacion);

        let texto = `Con ${pct.format(ocupacion)} de ocupación (${vendidos} de ${aforo} lugares): ingresos ${q.format(ingresos)}, `
            + `ganancia ${q.format(ganancia)}${ingresos > 0 ? ` (${pct.format(ganancia / ingresos)})` : ''}. `;
        if (equilibrio > aforo) {
            texto += `Ni vendiendo todo se cubren los costos: sube los precios o revisa los gastos.`;
            salida.dataset.tono = 'mal';
        } else {
            texto += `Cubres los costos al vender ${equilibrio} boletos (${pct.format(aforo ? equilibrio / aforo : 0)} del aforo).`;
            salida.dataset.tono = ganancia < 0 ? 'mal' : '';
        }
        salida.textContent = texto;
    }

    $('sugerencia-usar').addEventListener('click', () => {
        const sugeridos = calcularSugeridos();
        camposPrecio().forEach((campo) => {
            const valor = sugeridos.get(campo);
            if (valor !== null && !campo.disabled) campo.value = valor.toFixed(2);
        });
        pintarSugerencias();
        guardarBorrador();
    });
    sugerencia.addEventListener('input', () => {
        pintarSugerencias();
        guardarBorrador();
    });
    lista.addEventListener('input', () => {
        pintarProyeccion(calcularSugeridos());
        dibujarPlano();
    });
    document.addEventListener('admin:costos-cambio', () => {
        pintarSugerencias();
        dibujarPlano();
    });

    // admin.js vuelve a dibujar las localidades al cambiar de recinto: se agregan las sugerencias
    // y, si hay un borrador pendiente, sus precios
    let preciosPendientes = null;
    new MutationObserver(() => {
        if (!camposPrecio().length) {
            sugerencia.hidden = true;
            return;
        }
        if (preciosPendientes) {
            camposPrecio().forEach((campo) => {
                const valor = preciosPendientes[campo.dataset.localidadId];
                if (valor && !campo.disabled) campo.value = valor;
            });
            preciosPendientes = null;
        }
        pintarSugerencias();
    }).observe(lista, { childList: true });

    // =====================================================
    // 4. Borrador del evento nuevo
    // =====================================================
    const CLAVE = 'soiree_borrador_evento';
    const leerBorrador = () => {
        try {
            return JSON.parse(localStorage.getItem(CLAVE) || 'null');
        } catch {
            return null;
        }
    };
    let espera = null;

    function capturar() {
        const campo = (nombre) => form.elements.namedItem(nombre)?.value ?? '';
        return {
            nombre: campo('nombre'),
            recinto_id: campo('recinto_id'),
            fecha_evento: campo('fecha_evento'),
            descripcion: campo('descripcion'),
            imagenes: ['imagen_url', 'imagen_url_2', 'imagen_url_3'].map(campo),
            costos: Object.fromEntries([...form.querySelectorAll('#evento-costos-campos input')].map((i) => [i.dataset.categoria, i.value])),
            precios: Object.fromEntries(camposPrecio().map((c) => [c.dataset.localidadId, c.value])),
            margen: $('sugerencia-margen').value,
            ocupacion: $('sugerencia-ocupacion').value,
            guardado: Date.now(),
        };
    }

    function guardarBorrador() {
        if (!modoNuevo || restaurando || $('evento-form-panel')?.hidden) return;
        clearTimeout(espera);
        espera = setTimeout(() => {
            const datos = capturar();
            const vacio = !datos.nombre && !datos.descripcion && !datos.recinto_id && !datos.fecha_evento
                && !datos.imagenes.some(Boolean) && !Object.values(datos.costos).some(Boolean);
            try {
                if (vacio) localStorage.removeItem(CLAVE);
                else localStorage.setItem(CLAVE, JSON.stringify(datos));
            } catch {
                // sin almacenamiento: el formulario funciona igual, solo sin memoria
            }
        }, 400);
    }

    function aviso(borrador) {
        form.querySelector('.evento-borrador')?.remove();
        if (!borrador) return;
        const caja = crear('div', 'evento-borrador');
        caja.setAttribute('role', 'status');
        const cuando = new Intl.DateTimeFormat('es-GT', { day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' }).format(new Date(borrador.guardado));
        const descartar = crear('button', '', 'Descartar borrador');
        descartar.type = 'button';
        descartar.addEventListener('click', descartarBorrador);
        caja.append(crear('span', '', `Recuperamos lo que dejaste sin guardar (${cuando}).`), descartar);
        form.prepend(caja);
    }

    function aplicar(datos) {
        restaurando = true;
        const poner = (nombre, valor) => {
            const campo = form.elements.namedItem(nombre);
            if (campo) campo.value = valor ?? '';
        };
        poner('nombre', datos.nombre);
        poner('fecha_evento', datos.fecha_evento);
        poner('descripcion', datos.descripcion);
        ['imagen_url', 'imagen_url_2', 'imagen_url_3'].forEach((nombre, i) => poner(nombre, datos.imagenes?.[i]));
        casillas.forEach(mostrarFoto);
        form.querySelectorAll('#evento-costos-campos input').forEach((input) => {
            input.value = datos.costos?.[input.dataset.categoria] ?? '';
        });
        $('sugerencia-margen').value = datos.margen || 30;
        $('sugerencia-ocupacion').value = datos.ocupacion || 70;
        // El recinto carga sus localidades (admin.js); al llegar se ponen los precios del borrador
        preciosPendientes = datos.precios || null;
        if (recinto.value !== String(datos.recinto_id || '')) {
            recinto.value = datos.recinto_id || '';
            recinto.dispatchEvent(new Event('change'));
        }
        document.getElementById('evento-costos-campos')?.dispatchEvent(new Event('input', { bubbles: true }));
        restaurando = false;
    }

    function descartarBorrador() {
        try {
            localStorage.removeItem(CLAVE);
        } catch {
            // nada que borrar
        }
        aplicar({ costos: {}, precios: {}, imagenes: [] });
        aviso(null);
    }

    form.addEventListener('input', guardarBorrador);
    form.addEventListener('change', guardarBorrador);

    // =====================================================
    // Al abrir el formulario y al guardar (avisos de admin.js)
    // =====================================================
    document.addEventListener('admin:formulario-evento', (evento) => {
        modoNuevo = evento.detail?.eventoId == null;
        $('evento-fotos-mensaje').hidden = true;
        casillas.forEach((c) => {
            c.querySelector('.evento-foto__estado').textContent = '';
            mostrarFoto(c);
        });
        cerrarPlano();
        $('evento-plano-mapa').dataset.recinto = '';
        botonPlano.disabled = !recinto.value;
        aviso(null);

        // Después de que costos y localidades se preparen, se recupera el borrador
        setTimeout(() => {
            const borrador = modoNuevo ? leerBorrador() : null;
            if (borrador) {
                aplicar(borrador);
                aviso(borrador);
            }
            pintarSugerencias();
        }, 0);
    });

    document.addEventListener('admin:evento-guardado', (evento) => {
        if (!evento.detail?.creado) return;
        clearTimeout(espera);
        try {
            localStorage.removeItem(CLAVE);
        } catch {
            // nada que borrar
        }
    });
});
