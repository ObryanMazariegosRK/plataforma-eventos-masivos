'use strict';

// Sala de espera de la fila virtual (/eventos/{id}/fila).
//   1. Pide el turno de la persona (GET /api/cola-virtual/mi-turno); si no tiene, entra a la fila
//      (POST /api/cola-virtual/ingresar). Nunca vuelve a entrar sola: tras un turno vencido o ya
//      usado, la persona decide con un botón (así no gasta su segunda oportunidad sin querer).
//   2. Cada 3 s vuelve a consultar: el servidor aprovecha para avanzar la fila (habilita a los
//      siguientes en cuanto hay lugar), así que no hay que esperar al minuto del Scheduler.
//   3. Cuando le toca ("habilitado") pasa al mapa de asientos, que muestra su tiempo para reservar.

(() => {
    const raiz = document.getElementById('filaVirtual');
    if (!raiz) return;

    const eventoId = Number(raiz.dataset.evento);
    const ESTA_PAGINA = `/eventos/${eventoId}/fila`;
    const MAPA = `/eventos/${eventoId}/asientos`;
    const estadoEl = document.getElementById('filaEstado');
    const nota = document.getElementById('filaNota');

    let turno = null;
    let consultando = false;
    let yendoAlMapa = false;
    let esperoAqui = false;   // vio la fila: solo así tiene sentido el "¡Es tu turno!"
    let lugaresLibres = null;

    const crear = (etiqueta, clase, texto) => {
        const nodo = document.createElement(etiqueta);
        if (clase) nodo.className = clase;
        if (texto !== undefined) nodo.textContent = texto;
        return nodo;
    };

    function boton(texto, alTocar, principal = true) {
        const b = crear('button', principal ? 'fila__boton' : 'fila__boton fila__boton--secundario', texto);
        b.type = 'button';
        b.addEventListener('click', alTocar);
        return b;
    }

    function enlace(texto, href) {
        const a = crear('a', 'fila__boton fila__boton--secundario', texto);
        a.href = href;
        return a;
    }

    // ---- Evento: nombre, fecha, foto de fondo y si de verdad usa la fila ----
    async function cargarEvento() {
        const respuesta = await Auth.api(`/eventos/${eventoId}/disponibilidad`, { metodo: 'GET' });
        if (!respuesta.ok) {
            pintar([crear('p', 'fila__texto', 'Este evento no está a la venta.'), enlace('Ver la cartelera', '/catalogo')]);
            return false;
        }
        const { evento, resumen } = respuesta.data;
        lugaresLibres = resumen?.disponibles ?? null;

        // Sin fila (se la quitaron o nunca la tuvo): directo al mapa
        if (!evento.cola_virtual) {
            location.replace(MAPA);
            return false;
        }

        document.getElementById('filaEvento').textContent = evento.nombre;
        document.title = `Fila virtual · ${evento.nombre}`;
        const fecha = evento.fecha_evento
            ? new Date(evento.fecha_evento.replace(' ', 'T') + 'Z').toLocaleString('es-GT', { dateStyle: 'full', timeStyle: 'short' })
            : '';
        document.getElementById('filaDatos').textContent = [evento.recinto, fecha].filter(Boolean).join(' · ');
        const foto = (evento.imagenes || []).find(Boolean);
        if (foto) document.getElementById('filaFondo').style.backgroundImage = `url("${foto}")`;
        return true;
    }

    // ---- Turno ----
    async function consultar() {
        if (consultando || yendoAlMapa) return;
        consultando = true;
        try {
            const respuesta = await Auth.api(`/cola-virtual/mi-turno/${eventoId}`, { metodo: 'GET', conToken: true });
            if (respuesta.status === 401) return irAlLogin();
            if (respuesta.status === 404) return entrar();   // todavía no tiene turno
            if (!respuesta.ok) return;
            turno = respuesta.data;
            pintarTurno();
        } finally {
            consultando = false;
        }
    }

    async function entrar() {
        pintar([crear('p', 'fila__texto', 'Tomando tu lugar en la fila…')]);
        const respuesta = await Auth.api('/cola-virtual/ingresar', { conToken: true, cuerpo: { evento_id: eventoId } });
        if (respuesta.status === 401) return irAlLogin();
        if (!respuesta.ok) {
            // Error del servidor o demasiados intentos: mensaje claro y la opción de reintentar
            const texto = respuesta.status >= 500
                ? 'No pudimos tomar tu lugar en la fila. Inténtalo de nuevo en unos segundos.'
                : Auth.mensajeDeError(respuesta);
            const nodos = [crear('p', 'fila__texto', texto)];
            if (respuesta.status >= 500 || respuesta.status === 429) nodos.push(boton('Intentar de nuevo', entrar));
            nodos.push(enlace('Volver a la cartelera', '/catalogo'));
            pintar(nodos);
            nota.hidden = true;
            return;
        }
        consultando = false;
        await consultar();
    }

    function irAlLogin() {
        const mensaje = encodeURIComponent('Este evento vende con fila virtual: inicia sesión para tomar tu turno.');
        location.href = `/login?volver=${encodeURIComponent(ESTA_PAGINA)}&tipo=aviso&mensaje=${mensaje}`;
    }

    function pintar(nodos) {
        estadoEl.replaceChildren(...nodos);
    }

    const minutos = (n) => `${n} ${n === 1 ? 'minuto' : 'minutos'}`;

    function pintarTurno() {
        nota.hidden = !['esperando', 'habilitado'].includes(turno.estado);
        const ultima = turno.intento >= 2;

        if (turno.estado === 'esperando') {
            esperoAqui = true;
            // Lugar real = personas delante + 1 (turno.posicion es un consecutivo del evento que solo crece)
            const delante = turno.personas_delante;
            const numero = crear('p', 'fila__numero', String(delante));
            const leyenda = crear('p', 'fila__leyenda', delante === 1 ? 'persona delante de ti' : 'personas delante de ti');
            // Peor caso: cada grupo de "simultaneos" personas usa toda su ventana
            const tandas = Math.ceil((delante + 1) / Math.max(1, turno.simultaneos || 10));
            const estimado = delante === 0
                ? 'Eres el siguiente: en cuanto se libere un lugar, entras.'
                : `Como mucho unos ${minutos(tandas * (turno.ventana_minutos || 5))} (suele ser menos: muchos terminan antes).`;
            const nodos = [numero, leyenda, crear('p', 'fila__texto', estimado), crear('p', 'fila__posicion', `Eres el #${delante + 1} en la fila`)];
            if (lugaresLibres === 0) {
                nodos.push(crear('p', 'fila__alerta', 'Por ahora no quedan lugares libres. Si alguien libera los suyos, la fila avanzará.'));
            }
            if (ultima) nodos.push(crear('p', 'fila__alerta', 'Es tu última oportunidad: cuando te toque, tendrás tu tiempo completo para reservar.'));
            pintar(nodos);
            return;
        }

        if (turno.estado === 'habilitado') {
            yendoAlMapa = true;
            // Entró sin esperar (había lugar): directo al mapa, sin el aviso
            if (!esperoAqui) {
                location.replace(MAPA);
                return;
            }
            const ventana = turno.ventana_minutos || 5;
            pintar([
                crear('p', 'fila__exito', '¡Es tu turno!'),
                crear('p', 'fila__texto', `Tienes ${minutos(ventana)} para elegir tus asientos y reservarlos. Te llevamos al mapa…`),
                boton('Elegir mis asientos', () => location.assign(MAPA)),
            ]);
            setTimeout(() => location.assign(MAPA), 1800);
            return;
        }

        if (turno.estado === 'expirado') {
            if (ultima) {
                pintar([
                    crear('p', 'fila__titulo-estado', 'Se acabó tu tiempo'),
                    crear('p', 'fila__texto', 'Ya usaste tus dos oportunidades para este evento.'),
                    enlace('Volver a la cartelera', '/catalogo'),
                ]);
                return;
            }
            pintar([
                crear('p', 'fila__titulo-estado', 'Se acabó tu tiempo'),
                crear('p', 'fila__texto', 'Tu turno venció sin reservar. Tienes una oportunidad más: vuelves al final de la fila.'),
                boton('Volver a la fila (última oportunidad)', entrar),
                enlace('Ahora no', '/catalogo'),
            ]);
            return;
        }

        if (turno.estado === 'atendido') {
            pintar([
                crear('p', 'fila__exito', 'Ya apartaste tus lugares'),
                crear('p', 'fila__texto', 'Si todavía no pagas, termina el pago desde el mapa antes de que venza tu reserva. Para comprar más lugares, vuelve a hacer fila.'),
                enlace('Ir a pagar o ver mis lugares', MAPA),
                boton('Hacer fila para comprar más', entrar, false),
            ]);
        }
    }

    async function iniciar() {
        if (!Auth.obtenerToken()) return irAlLogin();
        if (!(await cargarEvento())) return;
        await consultar();
        setInterval(() => { if (!document.hidden) consultar(); }, 3000);
        document.addEventListener('visibilitychange', () => { if (!document.hidden) consultar(); });
        // Cada 20 s se refresca si quedan lugares (para avisar si el evento se agotó)
        setInterval(async () => {
            if (document.hidden || !turno || turno.estado !== 'esperando') return;
            const r = await Auth.api(`/eventos/${eventoId}/disponibilidad`, { metodo: 'GET' });
            if (r.ok) lugaresLibres = r.data.resumen?.disponibles ?? null;
        }, 20000);
    }

    iniciar();
})();
