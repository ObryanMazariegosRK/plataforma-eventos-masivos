'use strict';

// Formulario de evento → "Costos del evento": líneas de concepto, categoría y monto.
// admin.js lo usa así:
//   AdminCostos.preparar(eventoId | null)  al abrir el formulario (null = evento nuevo)
//   AdminCostos.leer()                     al guardar un evento nuevo (van en payload.costos)
//   AdminCostos.guardar(eventoId)          después de guardar un evento existente
//                                          (PUT /api/admin/eventos/{id}/costos, solo si cambió algo)

window.AdminCostos = (() => {
    const quetzales = new Intl.NumberFormat('es-GT', { style: 'currency', currency: 'GTQ' });
    const $ = (id) => document.getElementById(id);

    let cargados = true;     // al editar: false si no se pudieron leer (así no se borran por error)
    let original = '[]';     // para saber si hubo cambios

    function categorias() {
        try {
            return JSON.parse($('evento-costos-panel')?.dataset.categorias || '[]');
        } catch {
            return [];
        }
    }

    function crear(etiqueta, atributos = {}) {
        const nodo = document.createElement(etiqueta);
        Object.assign(nodo, atributos);
        return nodo;
    }

    function linea(costo = {}) {
        const fila = crear('div', { className: 'evento-costos__linea' });

        const concepto = crear('input', {
            type: 'text', maxLength: 150, required: true, placeholder: 'Concepto (ej. Alquiler del teatro)',
            value: costo.concepto || '', className: 'evento-costos__concepto',
        });
        concepto.setAttribute('aria-label', 'Concepto del costo');

        const categoria = crear('select', { className: 'evento-costos__categoria' });
        categoria.setAttribute('aria-label', 'Categoría del costo');
        categorias().forEach((c) => categoria.add(new Option(c.etiqueta, c.valor)));
        categoria.value = costo.categoria || 'otros';

        const monto = crear('input', {
            type: 'number', min: '0.01', step: '0.01', max: '9999999999.99', required: true, inputMode: 'decimal',
            placeholder: 'Q0.00', value: costo.monto ?? '', className: 'evento-costos__monto',
        });
        monto.setAttribute('aria-label', 'Monto en quetzales');

        const quitar = crear('button', { type: 'button', className: 'evento-costos__quitar', textContent: '✕' });
        quitar.setAttribute('aria-label', 'Quitar este costo');
        quitar.addEventListener('click', () => {
            fila.remove();
            actualizar();
        });

        fila.append(concepto, categoria, monto, quitar);
        return fila;
    }

    function lineas() {
        return [...($('evento-costos-lista')?.querySelectorAll('.evento-costos__linea') || [])];
    }

    function leer() {
        return lineas()
            .map((fila) => ({
                concepto: fila.querySelector('.evento-costos__concepto').value.trim(),
                categoria: fila.querySelector('.evento-costos__categoria').value,
                monto: fila.querySelector('.evento-costos__monto').value.trim(),
            }))
            .filter((c) => c.concepto || c.monto);
    }

    function actualizar() {
        const lista = $('evento-costos-lista');
        if (!lista) return;

        const total = leer().reduce((suma, c) => suma + (Number(c.monto) || 0), 0);
        $('evento-costos-total').textContent = 'Total ' + quetzales.format(total);

        lista.querySelector('.evento-costos__vacio')?.remove();
        if (!lineas().length) {
            lista.append(crear('p', { className: 'evento-costos__vacio', textContent: 'Aún no hay costos registrados.' }));
        }
    }

    function mensaje(texto) {
        const nodo = $('evento-costos-mensaje');
        if (!nodo) return;
        nodo.hidden = !texto;
        nodo.textContent = texto || '';
    }

    function pintar(costos) {
        $('evento-costos-lista').replaceChildren(...costos.map(linea));
        original = JSON.stringify(leer());
        actualizar();
    }

    async function pedir(ruta, opciones = {}) {
        const respuesta = await fetch('/api/admin' + ruta, {
            ...opciones,
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                Authorization: 'Bearer ' + localStorage.getItem('auth_token'),
            },
        });
        const datos = await respuesta.json().catch(() => ({}));
        if (!respuesta.ok) {
            const detalle = datos.errors ? Object.values(datos.errors).flat()[0] : datos.message;
            throw new Error(detalle || 'No se pudieron consultar los costos.');
        }
        return datos;
    }

    async function preparar(eventoId) {
        if (!$('evento-costos-lista')) return;
        mensaje('');
        pintar([]);
        cargados = eventoId === null;

        if (eventoId === null) return;

        try {
            const { data } = await pedir(`/eventos/${eventoId}/costos`);
            pintar(data.costos.map((c) => ({ ...c, monto: Number(c.monto).toFixed(2) })));
            cargados = true;
        } catch (error) {
            mensaje(`${error.message} Los costos no se modificarán al guardar.`);
        }
    }

    async function guardar(eventoId) {
        const costos = leer();
        if (!cargados || JSON.stringify(costos) === original) return;

        try {
            await pedir(`/eventos/${eventoId}/costos`, { method: 'PUT', body: JSON.stringify({ costos }) });
        } catch (error) {
            throw new Error(`El evento se guardó, pero no sus costos: ${error.message}`);
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        $('evento-costos-agregar')?.addEventListener('click', () => {
            const nueva = linea();
            $('evento-costos-lista').append(nueva);
            actualizar();
            nueva.querySelector('input').focus();
        });
        $('evento-costos-lista')?.addEventListener('input', actualizar);
        actualizar();
    });

    return { preparar, leer, guardar };
})();
