'use strict';

// Panel → "Bitácora" (solo administrador): quién hizo qué y cuándo, del más reciente al más
// antiguo (GET /api/admin/bitacora). Los filtros buscan en el servidor; admin.js muestra la
// sección y avisa con "admin:recargar" cada vez que se entra a ella.

document.addEventListener('DOMContentLoaded', () => {
    const panel = document.getElementById('bitacora-panel');
    const formulario = document.getElementById('bitacora-filtros');
    const lista = document.getElementById('bitacora-lista');
    const resumen = document.getElementById('bitacora-resumen');
    const verMas = document.getElementById('bitacora-ver-mas');
    const selectorCategoria = document.getElementById('bitacora-categoria');

    if (!panel || !formulario) {
        return;
    }

    const ROLES = { admin: 'Administrador', organizador: 'Operador', cliente: 'Cliente' };
    const CAMPOS = {
        nombre: 'Nombre', apellido: 'Apellido', email: 'Correo', rol: 'Rol', estado: 'Estado',
        fecha_evento: 'Fecha del evento', recinto_id: 'Recinto (id)', recinto: 'Recinto', descripcion: 'Descripción',
        imagen_url: 'Imagen', precios: 'Precios', evento_id: 'Evento (id)', inventario_ids: 'Asientos (id)',
        cupos: 'Lugares generales', reserva_id: 'Reserva', pasarela: 'Pasarela', monto: 'Monto', total: 'Total',
        evento: 'Evento', pago_id: 'Pago', motivo: 'Motivo', decision: 'Decisión', tipo: 'Tipo', desde: 'Desde', hasta: 'Hasta',
    };
    const zona = { timeZone: 'America/Guatemala' };
    const dia = new Intl.DateTimeFormat('es-GT', { ...zona, weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    const claveDia = new Intl.DateTimeFormat('en-CA', { ...zona, year: 'numeric', month: '2-digit', day: '2-digit' });
    const hora = new Intl.DateTimeFormat('es-GT', { ...zona, hour: 'numeric', minute: '2-digit', second: '2-digit' });

    let pagina = 0;
    let total = 0;
    let mostrados = 0;
    let ultimoDia = null;
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
        const datos = await respuesta.json().catch(() => ({}));
        if (!respuesta.ok) {
            const detalle = datos.errors ? Object.values(datos.errors).flat()[0] : datos.message;
            throw new Error(detalle || 'No se pudo consultar la bitácora. Inténtalo de nuevo.');
        }
        return datos;
    }

    function filtros() {
        const parametros = new URLSearchParams();
        for (const [clave, valor] of new FormData(formulario)) {
            if (String(valor).trim()) parametros.set(clave, String(valor).trim());
        }
        return parametros;
    }

    // ---- Registros ----
    // La base guarda UTC ("2026-10-22 21:46:26"): se marca como UTC para mostrarla en Guatemala
    const fechaDe = (registro) => new Date(`${registro.ocurrido_en.replace(' ', 'T')}Z`);

    function encabezadoDeDia(fecha) {
        const hoy = claveDia.format(new Date());
        const ayer = claveDia.format(new Date(Date.now() - 864e5));
        const clave = claveDia.format(fecha);
        const texto = clave === hoy ? 'Hoy' : clave === ayer ? 'Ayer' : dia.format(fecha);
        return crear('li', 'bitacora-dia', texto);
    }

    const legible = (valor) => {
        if (valor === null || valor === undefined || valor === '') return '—';
        if (typeof valor === 'object') return JSON.stringify(valor);
        return String(valor);
    };

    // Tabla campo / antes / después con lo que cambió (o con lo que se envió, si no hay "antes")
    function detalle(registro) {
        const antes = registro.datos_antes || {};
        const despues = registro.datos_despues || {};
        const campos = [...new Set([...Object.keys(antes), ...Object.keys(despues)])];
        const contenedor = crear('div', 'bitacora-detalle');

        if (campos.length) {
            // Sin "antes" (crear, exportar...) basta con lo que se envió
            const comparar = Object.keys(antes).length > 0;
            const tabla = crear('table', 'bitacora-cambios');
            const cabecera = tabla.createTHead().insertRow();
            (comparar ? ['Campo', 'Antes', 'Después'] : ['Dato', 'Valor']).forEach((t) => cabecera.append(crear('th', '', t)));
            const cuerpo = tabla.createTBody();
            campos.forEach((campo) => {
                const fila = cuerpo.insertRow();
                const cambio = campo in antes && campo in despues && legible(antes[campo]) !== legible(despues[campo]);
                if (cambio) fila.className = 'es-cambio';
                fila.append(crear('td', '', CAMPOS[campo] || campo));
                if (comparar) fila.append(crear('td', '', campo in antes ? legible(antes[campo]) : '—'));
                fila.append(crear('td', '', campo in despues ? legible(despues[campo]) : '—'));
            });
            contenedor.append(tabla);
        }

        const tecnico = crear('dl', 'bitacora-tecnico');
        [['Acción', registro.accion], ['Registro', registro.entidad ? `${registro.entidad}${registro.entidad_id ? ` #${registro.entidad_id}` : ''}` : null],
            ['IP', registro.ip], ['Navegador', registro.user_agent]]
            .filter(([, valor]) => valor)
            .forEach(([nombre, valor]) => tecnico.append(crear('dt', '', nombre), crear('dd', '', valor)));
        contenedor.append(tecnico);

        return contenedor;
    }

    function elemento(registro) {
        const fecha = fechaDe(registro);
        const fallido = registro.resultado === 'fallido';
        const usuario = registro.usuario;
        const item = crear('li', `bitacora-registro${fallido ? ' es-fallido' : ''}`);

        const iniciales = usuario?.nombre
            ? usuario.nombre.split(/\s+/).slice(0, 2).map((p) => p[0]).join('').toUpperCase()
            : '?';
        const avatar = crear('span', 'bitacora-avatar', iniciales);
        avatar.setAttribute('aria-hidden', 'true');

        const cuerpo = crear('div', 'bitacora-cuerpo');
        const cabeza = crear('div', 'bitacora-cabeza');
        cabeza.append(
            crear('p', 'bitacora-descripcion', registro.descripcion),
            crear('time', 'bitacora-hora', hora.format(fecha)),
        );
        cabeza.lastChild.dateTime = fecha.toISOString();

        const meta = crear('div', 'bitacora-meta');
        meta.append(crear('strong', '', usuario?.nombre || usuario?.correo || 'Sin sesión'));
        if (usuario?.nombre && usuario.correo) meta.append(crear('span', 'bitacora-correo', usuario.correo));
        const rol = crear('span', 'bitacora-rol', ROLES[usuario?.rol] || 'Sin sesión');
        rol.dataset.rol = usuario?.rol || 'ninguno';
        const categoria = crear('span', 'bitacora-categoria', selectorCategoria.querySelector(`option[value="${registro.categoria}"]`)?.textContent || registro.categoria);
        const resultado = crear('span', 'so-event-status bitacora-resultado', fallido ? 'Fallido' : 'Exitoso');
        resultado.dataset.status = fallido ? 'cancelado' : 'publicado';
        meta.append(rol, categoria, resultado);

        const desplegable = crear('details', 'bitacora-mas');
        desplegable.append(crear('summary', '', 'Ver detalle'));
        // El detalle se arma solo la primera vez que se abre
        desplegable.addEventListener('toggle', () => {
            if (desplegable.open && desplegable.children.length === 1) desplegable.append(detalle(registro));
        });

        cuerpo.append(cabeza, meta, desplegable);
        item.append(avatar, cuerpo);
        return item;
    }

    function agregar(registros) {
        const nodos = [];
        registros.forEach((registro) => {
            const fecha = fechaDe(registro);
            const clave = claveDia.format(fecha);
            if (clave !== ultimoDia) {
                ultimoDia = clave;
                nodos.push(encabezadoDeDia(fecha));
            }
            nodos.push(elemento(registro));
        });
        lista.append(...nodos);
    }

    function pintarResumen() {
        const hay = Array.from(new FormData(formulario).values()).some((v) => String(v).trim());
        resumen.textContent = total === 0
            ? (hay ? 'No hay registros con estos filtros.' : 'La bitácora todavía está vacía.')
            : `${total} ${total === 1 ? 'registro' : 'registros'}${hay ? ' con estos filtros' : ''}`;

        const quedan = total - mostrados;
        verMas.hidden = quedan <= 0;
        verMas.textContent = `Ver más registros · quedan ${quedan}`;
    }

    // ---- Búsqueda (página 1 reemplaza; las siguientes se agregan) ----
    async function buscar(siguiente = false) {
        controlador?.abort();
        controlador = new AbortController();
        const parametros = filtros();
        parametros.set('pagina', String(siguiente ? pagina + 1 : 1));

        if (!siguiente) {
            resumen.textContent = 'Buscando…';
            lista.setAttribute('aria-busy', 'true');
        }
        verMas.disabled = true;

        try {
            const { data, meta } = await pedir(`/bitacora?${parametros}`, controlador.signal);
            pagina = meta.pagina;
            total = meta.total;

            if (selectorCategoria.options.length === 1) {
                meta.categorias.forEach((c) => selectorCategoria.add(new Option(c.etiqueta, c.valor)));
            }
            if (!siguiente) {
                lista.replaceChildren();
                mostrados = 0;
                ultimoDia = null;
            }
            agregar(data);
            mostrados += data.length;
            pintarResumen();
        } catch (error) {
            if (error.name === 'AbortError') return;
            resumen.textContent = error.message;
            verMas.hidden = true;
        } finally {
            lista.removeAttribute('aria-busy');
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

    // Cada vez que se entra a la sección se trae lo más reciente
    document.addEventListener('admin:recargar', (evento) => {
        if (evento.detail?.section !== 'bitacora') return;
        buscar();
    });
});
