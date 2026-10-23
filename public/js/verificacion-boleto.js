'use strict';

// Tarjeta del "portero": qué boleto se escaneó y si puede entrar. La usan la página /b
// (QR leído con la cámara del teléfono) y Panel → Control de acceso (admin-acceso.js).
//   VerificacionBoleto.consultar(codigoOUrl) → POST /api/boletos/consultar
//   VerificacionBoleto.tarjeta(datos, { alTerminar }) → <article> con el botón "Registrar entrada"
// Registrar la entrada (POST /api/boletos/validar) es siempre un toque aparte: escanear no gasta el boleto.

window.VerificacionBoleto = (() => {
    const zona = { timeZone: 'America/Guatemala' };
    const fechaFuncion = new Intl.DateTimeFormat('es-GT', { ...zona, weekday: 'long', day: 'numeric', month: 'long' });
    const hora = new Intl.DateTimeFormat('es-GT', { ...zona, hour: 'numeric', minute: '2-digit' });
    const diaCorto = new Intl.DateTimeFormat('es-GT', { ...zona, weekday: 'short', day: 'numeric', month: 'short' });

    const crear = (etiqueta, clase, texto) => {
        const nodo = document.createElement(etiqueta);
        if (clase) nodo.className = clase;
        if (texto !== undefined) nodo.textContent = texto;
        return nodo;
    };
    const numero = (id) => `N° ${String(id).padStart(6, '0')}`;

    async function pedir(ruta, cuerpo) {
        const respuesta = await fetch(`/api${ruta}`, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                Authorization: `Bearer ${localStorage.getItem('auth_token')}`,
            },
            body: JSON.stringify(cuerpo),
        });
        const datos = await respuesta.json().catch(() => ({}));
        return { ok: respuesta.ok, status: respuesta.status, datos };
    }

    async function consultar(codigo) {
        const { ok, status, datos } = await pedir('/boletos/consultar', { codigo });
        if (!ok) {
            const error = new Error(status === 429
                ? 'Demasiados escaneos seguidos. Espera unos segundos.'
                : (datos.message || 'No se pudo consultar el boleto.'));
            error.status = status;
            throw error;
        }
        return datos.data;
    }

    // Estado grande de arriba: verde (pasa), ámbar (válido pero no es hoy), rojo (no pasa)
    function estado(datos) {
        if (!datos.encontrado) return { tono: 'no', icono: '✕', titulo: 'Código no reconocido', detalle: datos.motivo };
        if (datos.boleto.estado === 'usado') {
            const cuando = new Date(datos.boleto.usado_en);
            return { tono: 'no', icono: '✕', titulo: 'Ya entró', detalle: `Este boleto se usó a las ${hora.format(cuando)} del ${diaCorto.format(cuando)}.` };
        }
        if (!datos.puede_entrar) return { tono: 'no', icono: '✕', titulo: 'No puede entrar', detalle: datos.motivo };
        if (!datos.es_hoy) {
            return { tono: 'aviso', icono: '!', titulo: 'Boleto válido, pero no es para hoy', detalle: `La función es el ${fechaFuncion.format(new Date(datos.evento.fecha))}.` };
        }
        return { tono: 'si', icono: '✓', titulo: 'Puede entrar', detalle: 'Boleto válido para una persona.' };
    }

    function tarjeta(datos, { alTerminar } = {}) {
        const e = estado(datos);
        const articulo = crear('article', `verificacion verificacion--${e.tono}`);
        articulo.setAttribute('aria-live', 'polite');

        const banda = crear('div', 'verificacion__estado');
        const icono = crear('span', 'verificacion__icono', e.icono);
        icono.setAttribute('aria-hidden', 'true');
        const textos = crear('div');
        textos.append(crear('strong', 'verificacion__titulo', e.titulo), crear('span', 'verificacion__detalle', e.detalle));
        banda.append(icono, textos);
        articulo.append(banda);

        if (datos.encontrado) {
            const cuerpo = crear('div', 'verificacion__cuerpo');
            const fecha = new Date(datos.evento.fecha);
            cuerpo.append(
                crear('span', 'verificacion__numero', `Boleto ${numero(datos.boleto.id)}`),
                crear('h2', 'verificacion__evento', datos.evento.nombre),
                crear('p', 'verificacion__fecha', `${fechaFuncion.format(fecha)} · ${hora.format(fecha)} · ${datos.evento.recinto}`)
            );
            const lugar = crear('dl', 'verificacion__lugar');
            const dato = (titulo, valor) => {
                const caja = crear('div');
                caja.append(crear('dt', '', titulo), crear('dd', '', valor));
                return caja;
            };
            lugar.style.setProperty('--color-zona', datos.lugar.color || '#c8b48c');
            lugar.append(dato('Zona', datos.lugar.localidad));
            if (datos.lugar.numero !== null) lugar.append(dato('Fila', datos.lugar.fila), dato('Asiento', String(datos.lugar.numero)));
            lugar.append(dato('Titular', datos.boleto.titular));
            cuerpo.append(lugar);
            articulo.append(cuerpo);
        }

        const acciones = crear('div', 'verificacion__acciones');
        const mensaje = crear('p', 'verificacion__mensaje');
        mensaje.setAttribute('role', 'status');
        mensaje.hidden = true;

        if (datos.puede_entrar) {
            const registrar = crear('button', 'verificacion__boton es-principal', 'Registrar entrada');
            registrar.type = 'button';
            registrar.addEventListener('click', async () => {
                registrar.disabled = true;
                registrar.textContent = 'Registrando…';
                const { ok, datos: respuesta } = await pedir('/boletos/validar', { codigo_qr: datos.codigo, evento_id: datos.evento.id });
                if (ok) {
                    articulo.className = 'verificacion verificacion--si verificacion--registrada';
                    articulo.querySelector('.verificacion__icono').textContent = '✓';
                    articulo.querySelector('.verificacion__titulo').textContent = 'Entrada registrada';
                    articulo.querySelector('.verificacion__detalle').textContent =
                        `Adelante. Registrada a las ${hora.format(new Date(respuesta.boleto?.usado_en || Date.now()))}.`;
                    registrar.remove();
                    alTerminar?.('registrada');
                    return;
                }
                // Otro portero pudo registrarlo un instante antes: se vuelve a consultar para mostrar el motivo
                try {
                    articulo.replaceWith(tarjeta(await consultar(datos.codigo), { alTerminar }));
                } catch {
                    registrar.disabled = false;
                    registrar.textContent = 'Registrar entrada';
                    mensaje.textContent = respuesta.mensaje || 'No se pudo registrar la entrada. Inténtalo de nuevo.';
                    mensaje.hidden = false;
                }
            });
            acciones.append(registrar);
        }

        articulo.append(mensaje, acciones);
        return articulo;
    }

    return { consultar, tarjeta };
})();
