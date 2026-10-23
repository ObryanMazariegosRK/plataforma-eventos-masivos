'use strict';

// Formulario de evento → "Costos del evento": un campo por concepto (recinto*, artistas*,
// producción, publicidad, personal, otros). admin.js lo usa así:
//   AdminCostos.preparar(eventoId | null)  al abrir el formulario (null = evento nuevo)
//   AdminCostos.leer()                     al guardar un evento nuevo (van en payload.costos)
//   AdminCostos.guardar(eventoId)          después de guardar un evento existente
//                                          (PUT /api/admin/eventos/{id}/costos, solo si cambió algo)
// Cada cambio avisa con el evento "admin:costos-cambio" ({ total }) para sugerir los precios.

window.AdminCostos = (() => {
    const quetzales = new Intl.NumberFormat('es-GT', { style: 'currency', currency: 'GTQ' });
    const $ = (id) => document.getElementById(id);

    let cargados = true;     // al editar: false si no se pudieron leer (así no se borran por error)
    let original = '[]';     // para saber si hubo cambios

    const campos = () => [...($('evento-costos-campos')?.querySelectorAll('input[data-categoria]') || [])];

    // Solo los conceptos con monto (el recinto y los artistas los exige el formulario)
    function leer() {
        return campos()
            .map((input) => ({
                categoria: input.dataset.categoria,
                concepto: input.dataset.concepto,
                monto: input.value.trim(),
            }))
            .filter((c) => Number(c.monto) > 0)
            .map((c) => ({ ...c, monto: Number(c.monto).toFixed(2) }));
    }

    function total() {
        return leer().reduce((suma, c) => suma + Number(c.monto), 0);
    }

    function actualizar() {
        if (!$('evento-costos-total')) return;
        const suma = total();
        $('evento-costos-total').textContent = 'Total ' + quetzales.format(suma);
        document.dispatchEvent(new CustomEvent('admin:costos-cambio', { detail: { total: suma } }));
    }

    function mensaje(texto) {
        const nodo = $('evento-costos-mensaje');
        if (!nodo) return;
        nodo.hidden = !texto;
        nodo.textContent = texto || '';
    }

    // Pone los montos en los campos (varios costos de una misma categoría se suman)
    function pintar(costos) {
        const porCategoria = {};
        costos.forEach((c) => {
            porCategoria[c.categoria] = (porCategoria[c.categoria] || 0) + Number(c.monto);
        });
        campos().forEach((input) => {
            const monto = porCategoria[input.dataset.categoria];
            input.value = monto ? monto.toFixed(2) : '';
        });
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
        if (!$('evento-costos-campos')) return;
        mensaje('');
        pintar([]);
        cargados = eventoId === null;

        if (eventoId === null) return;

        try {
            const { data } = await pedir(`/eventos/${eventoId}/costos`);
            pintar(data.costos);
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
        $('evento-costos-campos')?.addEventListener('input', actualizar);
        // Al abrir el formulario (admin.js): costos del evento o vacíos si es nuevo
        document.addEventListener('admin:formulario-evento', (evento) => preparar(evento.detail?.eventoId ?? null));
    });

    return { preparar, leer, guardar, total, pintar };
})();
