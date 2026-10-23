'use strict';

// Campana de avisos en la cabecera de la tienda (junto al menú de la cuenta que arma catalogo.js,
// que avisa con "tienda:sesion" cuando la sesión es válida). Lista los avisos de la persona
// (notificaciones.js → GET /api/notificaciones), los marca como leídos al abrirlos y permite
// activar las notificaciones push en este dispositivo. El conteo se refresca cada minuto.

document.addEventListener('tienda:sesion', (evento) => {
    const cuenta = evento.detail?.cuenta;
    if (!cuenta || !window.Avisos || document.querySelector('.campana')) return;

    const crear = (etiqueta, clase, texto) => {
        const nodo = document.createElement(etiqueta);
        if (clase) nodo.className = clase;
        if (texto !== undefined) nodo.textContent = texto;
        return nodo;
    };

    const POR_PAGINA = 8;
    let pagina = 0;
    let cargando = false;

    // ---- Botón ----
    const campana = crear('div', 'campana');
    const boton = crear('button', 'campana__boton');
    boton.type = 'button';
    boton.setAttribute('aria-haspopup', 'dialog');
    boton.setAttribute('aria-expanded', 'false');
    boton.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 8-3 10h18c0-2-3-3-3-10"/><path d="M10 21a2 2 0 0 0 4 0"/></svg>';
    const contador = crear('span', 'campana__contador');
    contador.hidden = true;
    boton.append(contador);

    // ---- Panel ----
    const panel = crear('section', 'campana__panel');
    panel.id = 'campanaPanel';
    panel.hidden = true;
    panel.setAttribute('role', 'dialog');
    panel.setAttribute('aria-label', 'Tus avisos');
    boton.setAttribute('aria-controls', panel.id);

    const cabeza = crear('header', 'campana__cabeza');
    const titulo = crear('h2', '', 'Avisos');
    const todas = crear('button', 'campana__todas', 'Marcar todo como leído');
    todas.type = 'button';
    todas.hidden = true;
    cabeza.append(titulo, todas);

    const lista = crear('ol', 'campana__lista');
    const vacio = crear('p', 'campana__vacio', 'Aquí verás tus boletos listos, recordatorios y cualquier cambio en tus funciones.');
    vacio.hidden = true;
    const verMas = crear('button', 'campana__mas', 'Ver avisos anteriores');
    verMas.type = 'button';
    verMas.hidden = true;

    // Push en este dispositivo
    const push = crear('div', 'campana__push');
    const pushTexto = crear('div');
    const pushTitulo = crear('strong', '', 'Avisos en este dispositivo');
    const pushNota = crear('span', 'campana__push-nota', 'Recibe tus boletos y recordatorios aunque no tengas Soirée abierta.');
    pushTexto.append(pushTitulo, pushNota);
    const interruptor = crear('button', 'campana__interruptor');
    interruptor.type = 'button';
    interruptor.setAttribute('role', 'switch');
    interruptor.setAttribute('aria-checked', 'false');
    interruptor.setAttribute('aria-label', 'Activar avisos en este dispositivo');
    push.append(pushTexto, interruptor);

    panel.append(cabeza, lista, vacio, verMas, push);
    campana.append(boton, panel);
    cuenta.before(campana);

    function pintarContador(noLeidas) {
        contador.hidden = noLeidas === 0;
        contador.textContent = noLeidas > 9 ? '9+' : String(noLeidas);
        boton.setAttribute('aria-label', noLeidas ? `Avisos: ${noLeidas} sin leer` : 'Avisos');
        todas.hidden = noLeidas === 0;
    }

    function item(aviso) {
        const li = crear('li', aviso.leida ? 'campana__aviso' : 'campana__aviso es-nuevo');
        const enlace = crear('a');
        enlace.href = aviso.url || '#';
        const icono = crear('span', 'campana__icono', Avisos.icono(aviso.tipo));
        icono.setAttribute('aria-hidden', 'true');
        const texto = crear('span', 'campana__texto');
        texto.append(
            crear('strong', '', aviso.titulo),
            crear('span', 'campana__mensaje', aviso.mensaje.split(/\n\s*\n/)[0]),
            crear('time', '', Avisos.hace(aviso.fecha))
        );
        enlace.append(icono, texto);
        enlace.addEventListener('click', async (e) => {
            if (!aviso.leida) {
                e.preventDefault();
                try {
                    pintarContador(await Avisos.leer(aviso.id));
                } catch {
                    // si falla, igual se abre el aviso
                }
                if (aviso.url) location.href = aviso.url;
                else li.classList.remove('es-nuevo');
            } else if (!aviso.url) {
                e.preventDefault();
            }
        });
        li.append(enlace);
        return li;
    }

    async function cargar(siguiente = false) {
        if (cargando) return;
        cargando = true;
        try {
            const datos = await Avisos.listar(siguiente ? pagina + 1 : 1, POR_PAGINA);
            pagina = datos.meta.pagina;
            const nuevos = datos.avisos.map(item);
            if (siguiente) lista.append(...nuevos);
            else lista.replaceChildren(...nuevos);
            vacio.hidden = datos.meta.total > 0;
            verMas.hidden = !datos.meta.hay_mas;
            pintarContador(datos.no_leidas);
        } catch (error) {
            if (error.status === 401) campana.remove();
        } finally {
            cargando = false;
        }
    }

    async function pintarPush() {
        const estado = await Avisos.push.estado();
        interruptor.setAttribute('aria-checked', String(estado === 'activo'));
        interruptor.disabled = estado === 'no_soportado' || estado === 'sin_llave';
        pushNota.textContent = {
            activo: 'Activados: te avisaremos aunque no tengas Soirée abierta.',
            bloqueado: 'Bloqueados en este navegador: actívalos desde el candado 🔒 de la barra de direcciones.',
            no_soportado: Avisos.push.porQueNoSoportado(),
            sin_llave: 'Los avisos en el dispositivo aún no están configurados.',
        }[estado] || 'Recibe tus boletos y recordatorios aunque no tengas Soirée abierta.';
        push.classList.toggle('es-aviso', estado === 'bloqueado' || estado === 'no_soportado');
    }

    function abrir(abierto) {
        panel.hidden = !abierto;
        boton.setAttribute('aria-expanded', String(abierto));
        campana.classList.toggle('esta-abierta', abierto);
        if (abierto) {
            cuenta.open = false;
            cargar();
            pintarPush();
        }
    }

    boton.addEventListener('click', () => abrir(panel.hidden));
    verMas.addEventListener('click', () => cargar(true));
    todas.addEventListener('click', async () => {
        try {
            pintarContador(await Avisos.leerTodas());
            lista.querySelectorAll('.es-nuevo').forEach((li) => li.classList.remove('es-nuevo'));
        } catch {
            // se reintenta al volver a abrir
        }
    });
    interruptor.addEventListener('click', async () => {
        interruptor.disabled = true;
        try {
            if (interruptor.getAttribute('aria-checked') === 'true') await Avisos.push.desactivar();
            else await Avisos.push.activar();
        } catch (error) {
            pushNota.textContent = error.message;
            push.classList.add('es-aviso');
            interruptor.disabled = false;
            return;
        }
        await pintarPush();
    });
    document.addEventListener('click', (e) => {
        if (!panel.hidden && !campana.contains(e.target)) abrir(false);
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !panel.hidden) {
            abrir(false);
            boton.focus();
        }
    });

    cargar();
    setInterval(() => {
        if (document.visibilityState === 'visible' && panel.hidden) cargar();
    }, 60000);
});
