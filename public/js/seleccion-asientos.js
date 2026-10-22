// Selección de asientos del cliente: elegir butacas/cupos, total, refresco en vivo y reservar.
// Marcado: resources/views/asientos/_selector.blade.php. Usa Auth (autenticacion.js) y MapaAsientos (asientos.js).
(() => {
    const pagina = document.getElementById('seleccionAsientos');
    if (!pagina) return;
    const eventoId = Number(pagina.dataset.evento);
    const MAXIMO = Number(pagina.dataset.maximo);   // boletos por persona en este evento
    const MINUTOS = Number(pagina.dataset.minutos);
    const CLAVE = `seleccion-evento-${eventoId}`;   // la selección sobrevive a ir a iniciar sesión y volver

    const mapa = document.getElementById('mapa');
    const lista = document.getElementById('lista');
    const totalEl = document.getElementById('total');
    const boton = document.getElementById('btnReservar');
    const aviso = document.getElementById('aviso');

    let datos = null;
    let seleccion = new Set();      // ids de inventario (asientos numerados)
    let cupos = new Map();          // localidad_id → cantidad (zonas generales)

    // Lugares que aún puede apartar: el máximo menos lo que ya pagó o tiene apartado (GET mi-cupo)
    let restantes = MAXIMO;
    let usados = 0;

    function pintarAyuda() {
        const cupo = usados > 0
            ? `Hasta ${MAXIMO} boletos por persona · te ${restantes === 1 ? 'queda' : 'quedan'} ${restantes}.`
            : `Hasta ${MAXIMO} boletos por persona.`;
        document.getElementById('ayuda').textContent =
            `${cupo} Los apartamos para ti por ${MINUTOS} minutos mientras pagas.`;
    }
    pintarAyuda();
    const cuenta = document.getElementById('enlaceCuenta');   // solo existe en la página propia del mapa
    if (cuenta && Auth.obtenerToken()) {
        cuenta.textContent = 'Mi perfil';
        cuenta.href = '/perfil';
    }
    // a dónde regresar tras iniciar sesión (p. ej. la experiencia, directo al plano)
    const VOLVER = pagina.dataset.volver || location.pathname;

    // ---- Selección ----
    const cantidadElegida = () => seleccion.size + [...cupos.values()].reduce((a, b) => a + b, 0);
    const puedeAgregar = () => cantidadElegida() < restantes;

    function mostrarAviso(texto, tipo = 'error') {
        aviso.textContent = texto;
        aviso.className = `aviso aviso--visible${tipo === 'info' ? ' aviso--info' : ''}`;
    }

    function alAlternarAsiento(asiento) {
        aviso.className = 'aviso';
        if (seleccion.has(asiento.inventario_id)) {
            seleccion.delete(asiento.inventario_id);
        } else if (puedeAgregar()) {
            seleccion.add(asiento.inventario_id);
        } else {
            mostrarAviso(restantes < MAXIMO
                ? `Ya tienes ${usados} ${usados === 1 ? 'boleto' : 'boletos'} para esta función: puedes elegir ${restantes} más (máximo ${MAXIMO} por persona).`
                : `Puedes elegir como máximo ${MAXIMO} boletos por persona.`, 'info');
        }
        actualizar();
    }

    function alCambiarCupo(localidad, cantidad) {
        aviso.className = 'aviso';
        if (cantidad <= 0) cupos.delete(localidad.id);
        else cupos.set(localidad.id, cantidad);
        actualizar();
    }

    // ---- Resumen y total ----
    function lineas() {
        const items = [];
        datos.localidades.forEach((localidad) => {
            localidad.asientos.forEach((asiento) => {
                if (seleccion.has(asiento.inventario_id)) {
                    items.push({ localidad, titulo: `Fila ${asiento.fila} · Asiento ${asiento.numero}`, precio: localidad.precio,
                                 quitar: () => alAlternarAsiento(asiento) });
                }
            });
            const cantidad = cupos.get(localidad.id);
            if (cantidad) {
                items.push({ localidad, titulo: `${cantidad} × lugar general`, precio: localidad.precio * cantidad,
                             quitar: () => alCambiarCupo(localidad, 0) });
            }
        });
        return items;
    }

    let clavesPrevias = new Set();   // para animar solo lo recién agregado

    function dibujarResumen() {
        const items = lineas();
        const claves = new Set();
        lista.replaceChildren();

        if (items.length === 0) {
            const vacia = document.createElement('li');
            vacia.className = 'lista__vacia';
            vacia.textContent = 'Toca un asiento del mapa para agregarlo.';
            lista.appendChild(vacia);
        }

        items.forEach((item) => {
            const li = document.createElement('li');
            li.style.setProperty('--color-zona', item.localidad.color || '#c8b48c');
            const clave = `${item.localidad.id}:${item.titulo}`;
            claves.add(clave);
            if (!clavesPrevias.has(clave)) li.classList.add('lista__nueva');

            const punto = document.createElement('span');
            punto.className = 'lista__punto';
            const nombre = document.createElement('span');
            nombre.className = 'lista__nombre';
            nombre.textContent = item.localidad.nombre;
            const detalle = document.createElement('small');
            detalle.textContent = item.titulo;
            nombre.appendChild(detalle);
            const precio = document.createElement('span');
            precio.className = 'lista__precio';
            precio.textContent = MapaAsientos.moneda(item.precio);
            const quitar = document.createElement('button');
            quitar.type = 'button';
            quitar.className = 'lista__quitar';
            quitar.textContent = '×';
            quitar.setAttribute('aria-label', `Quitar ${item.localidad.nombre} ${item.titulo}`);
            quitar.addEventListener('click', item.quitar);

            li.append(punto, nombre, precio, quitar);
            lista.appendChild(li);
        });

        clavesPrevias = claves;
        animarTotal(items.reduce((suma, item) => suma + item.precio, 0));
        boton.disabled = items.length === 0 || restantes === 0;
        boton.textContent = restantes === 0
            ? 'Límite alcanzado'
            : items.length ? `Reservar ${cantidadElegida()} y pagar` : 'Reservar y pagar';
    }

    // El total "cuenta" hasta el nuevo valor en lugar de saltar
    let totalMostrado = 0;
    function animarTotal(destino) {
        const inicio = totalMostrado;
        const comienzo = performance.now();
        const paso = (ahora) => {
            const t = Math.min(1, (ahora - comienzo) / 350);
            totalMostrado = inicio + (destino - inicio) * (1 - Math.pow(1 - t, 3));
            totalEl.textContent = MapaAsientos.moneda(totalMostrado);
            if (t < 1) requestAnimationFrame(paso);
        };
        requestAnimationFrame(paso);
    }

    // Huella de lo que muestra el plano: si el refresco trae lo mismo, no se vuelve a dibujar
    // (redibujar ~80 asientos cada 8 s trababa el scroll en celular)
    const huellaDatos = () => JSON.stringify([
        restantes,
        datos.localidades.map((l) => [l.id, l.disponibles, l.asientos.map((a) => a.estado)]),
    ]);
    let huellaDibujada = '';

    function actualizar() {
        huellaDibujada = huellaDatos();
        MapaAsientos.dibujar(mapa, datos, { modo: 'cliente', seleccion, cupos, puedeAgregar, alAlternarAsiento, alCambiarCupo });
        dibujarResumen();
        try {
            sessionStorage.setItem(CLAVE, JSON.stringify({ seleccion: [...seleccion], cupos: [...cupos] }));
        } catch { /* sin almacenamiento: no pasa nada */ }
    }

    // ---- Datos (y refresco en vivo) ----
    async function cargar() {
        const respuesta = await Auth.api(`/eventos/${eventoId}/disponibilidad`, { metodo: 'GET' });
        if (!respuesta.ok) {
            mapa.innerHTML = '';
            const p = document.createElement('p');
            p.className = 'cargando';
            p.textContent = respuesta.status === 404
                ? 'Este evento no está a la venta.'
                : 'No se pudo cargar el mapa. Intenta de nuevo en un momento.';
            mapa.appendChild(p);
            return false;
        }
        datos = respuesta.data;
        return true;
    }

    // Quita de la selección lo que otra persona tomó mientras tanto
    function depurarSeleccion() {
        const perdidos = [];
        datos.localidades.forEach((localidad) => {
            localidad.asientos.forEach((asiento) => {
                if (seleccion.has(asiento.inventario_id) && asiento.estado !== 'disponible') {
                    seleccion.delete(asiento.inventario_id);
                    perdidos.push(`${asiento.fila}${asiento.numero}`);
                }
            });
            const cantidad = cupos.get(localidad.id);
            if (cantidad && cantidad > localidad.disponibles) {
                cupos.set(localidad.id, localidad.disponibles);
                if (!localidad.disponibles) cupos.delete(localidad.id);
                perdidos.push(localidad.nombre);
            }
        });
        if (perdidos.length) {
            mostrarAviso(`Alguien más apartó: ${perdidos.join(', ')}. Elige otro lugar.`, 'info');
        }
    }

    // Cuántos boletos más puede apartar (solo con sesión; sin sesión se muestra el máximo)
    async function cargarCupo() {
        if (!Auth.obtenerToken()) return;
        const respuesta = await Auth.api(`/eventos/${eventoId}/mi-cupo`, { metodo: 'GET', conToken: true });
        if (!respuesta.ok) return;

        restantes = respuesta.data.disponibles;
        usados = respuesta.data.usados;
        pintarAyuda();

        const limite = document.getElementById('limite');
        limite.hidden = restantes > 0;
        document.getElementById('limiteTexto').textContent =
            `Ya tienes ${usados} boletos para esta función, el máximo por persona.`;

        // Una selección guardada (p. ej. antes de iniciar sesión) que ya no cabe: se vacía y se avisa
        if (cantidadElegida() > restantes) {
            seleccion.clear();
            cupos.clear();
            if (restantes > 0) {
                mostrarAviso(`Ya tienes ${usados} ${usados === 1 ? 'boleto' : 'boletos'} para esta función: puedes elegir ${restantes} más.`, 'info');
            }
        }
    }

    async function iniciar() {
        if (!(await cargar())) return;

        const llenar = (selector, valor) => document.querySelectorAll(selector).forEach((n) => { n.textContent = valor; });
        llenar('[data-nombre-evento]', datos.evento.nombre);
        // la base de datos guarda la hora en UTC: la 'Z' evita que el navegador la tome como hora local
        const fecha = datos.evento.fecha_evento
            ? new Date(datos.evento.fecha_evento.replace(' ', 'T') + 'Z').toLocaleString('es-GT', { dateStyle: 'full', timeStyle: 'short' })
            : '';
        llenar('[data-datos-evento]', [datos.evento.recinto, fecha].filter(Boolean).join(' · '));

        try {
            const guardado = JSON.parse(sessionStorage.getItem(CLAVE) || 'null');
            if (guardado) {
                seleccion = new Set(guardado.seleccion);
                cupos = new Map(guardado.cupos);
            }
        } catch { /* selección guardada inválida: se ignora */ }

        await cargarCupo();
        depurarSeleccion();
        actualizar();

        // en vivo: cada 8 s se ve lo que otros van apartando (y si se liberó cupo propio
        // porque venció una reserva sin pagar)
        setInterval(async () => {
            if (document.hidden || !(await cargar())) return;
            await cargarCupo();
            if (huellaDatos() === huellaDibujada) return;   // nada cambió: no se toca el plano
            depurarSeleccion();
            actualizar();
        }, 8000);
    }

    // ---- Reservar ----
    boton.addEventListener('click', async () => {
        if (!Auth.obtenerToken()) {
            const volver = encodeURIComponent(VOLVER);
            const mensaje = encodeURIComponent('Inicia sesión para reservar. Guardamos tu selección.');
            location.href = `/login?volver=${volver}&tipo=aviso&mensaje=${mensaje}`;
            return;
        }

        Auth.cargando(boton, true, 'Apartando tus lugares…');
        const respuesta = await Auth.api('/reservas', {
            conToken: true,
            cuerpo: {
                evento_id: eventoId,
                ...(seleccion.size ? { inventario_ids: [...seleccion] } : {}),
                ...(cupos.size ? { cupos: [...cupos].map(([localidad_id, cantidad]) => ({ localidad_id, cantidad })) } : {}),
            },
        });

        if (respuesta.ok) {
            try { sessionStorage.removeItem(CLAVE); } catch { /* nada */ }
            // Directo a la pasarela (sin la página intermedia "Pagar reserva")
            Auth.cargando(boton, true, 'Preparando tu pago…');
            const error = await Auth.irAPagar(respuesta.data.id);
            if (error) {
                // La reserva ya existe: en /pagar se ve el error y se puede reintentar
                location.href = `/pagar?reserva=${respuesta.data.id}`;
            }
            return;
        }

        Auth.cargando(boton, false);
        if (respuesta.status === 401) {
            location.href = `/login?volver=${encodeURIComponent(VOLVER)}&tipo=aviso&mensaje=${encodeURIComponent('Tu sesión expiró. Inicia sesión de nuevo.')}`;
            return;
        }

        // Lo más común: alguien apartó un asiento un instante antes. Se refresca el mapa
        // (y el cupo, por si la otra pestaña de esta misma persona ya apartó lugares).
        mostrarAviso(Auth.mensajeDeError(respuesta), respuesta.data?.error === 'limite_por_persona' ? 'info' : 'error');
        await cargarCupo();
        if (await cargar()) {
            depurarSeleccion();
            actualizar();
        }
    });

    iniciar();
})();
