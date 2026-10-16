'use strict';

document.addEventListener('DOMContentLoaded', async () => {
    const pagina = document.getElementById('detallePagina');
    const aviso = document.getElementById('detalleAviso');
    const contenido = document.getElementById('detalleContenido');
    const extra = document.getElementById('detalleInformacionExtra');
    const zonas = document.getElementById('detalleLocalidades');
    const imagen = document.getElementById('detalleImagen');
    const etiquetaFoto = document.getElementById('detalleFotoNota');
    const imagenRespaldo = '/img/eventos/demo-04.jpg';

    const texto = (id, valor) => {
        const nodo = document.getElementById(id);
        if (nodo) nodo.textContent = valor ?? '';
    };

    const crear = (etiqueta, clase, contenidoTexto) => {
        const nodo = document.createElement(etiqueta);
        if (clase) nodo.className = clase;
        if (contenidoTexto !== undefined) nodo.textContent = contenidoTexto;
        return nodo;
    };

    const fechaGT = fecha => {
        const fechaObj = new Date(fecha);
        if (Number.isNaN(fechaObj.getTime())) return 'Fecha por confirmar';
        return new Intl.DateTimeFormat('es-GT', {
            dateStyle: 'full', timeStyle: 'short', timeZone: 'America/Guatemala'
        }).format(fechaObj);
    };

    function prepararImagen(evento) {
        const origen = typeof evento.imagen_url === 'string' ? evento.imagen_url.trim() : '';
        const imagenValida = /^(https?:\/\/|\/(?!\/))/i.test(origen);
        imagen.src = imagenValida ? origen : imagenRespaldo;
        imagen.alt = imagenValida ? `Afiche de ${evento.nombre}` : 'Fotografía ilustrativa de concierto';
        etiquetaFoto.hidden = imagenValida;
        imagen.onerror = () => {
            imagen.onerror = null;
            imagen.src = imagenRespaldo;
            imagen.alt = 'Fotografía ilustrativa de concierto';
            etiquetaFoto.hidden = false;
        };
    }

    function mostrarZonas(localidades) {
        zonas.replaceChildren();
        if (!localidades.length) {
            zonas.appendChild(crear(
                'p', 'detalle-sin-localidades',
                'El administrador todavía no ha configurado localidades y precios para este evento.'
            ));
            return;
        }
        const moneda = new Intl.NumberFormat('es-GT', { style: 'currency', currency: 'GTQ' });
        localidades.forEach(localidad => {
            const fila = crear('div', 'detalle-localidad');
            const identidad = crear('div', '');
            identidad.append(
                crear('strong', '', localidad.nombre || 'Localidad'),
                crear('span', 'tipo', localidad.tipo ? `Tipo: ${localidad.tipo}` : 'Tipo no especificado')
            );
            const valor = Number(localidad.precio);
            const precio = crear('span', 'precio',
                Number.isFinite(valor) ? moneda.format(valor) : 'Precio pendiente'
            );
            fila.append(identidad, precio);
            zonas.appendChild(fila);
        });
    }

    try {
        const id = pagina?.dataset.eventoId;
        if (!/^\d+$/.test(id || '')) throw new Error('Identificador de evento inválido');
        const respuesta = await fetch(`/api/eventos/${id}`, {
            headers: { 'Accept': 'application/json' }
        });
        if (respuesta.status === 404) throw new Error('Este evento no está publicado o ya no está disponible.');
        if (!respuesta.ok) throw new Error('No pudimos consultar el evento. Inténtalo nuevamente.');
        const cuerpo = await respuesta.json();
        if (cuerpo.success !== true || !cuerpo.data) throw new Error('Respuesta inesperada del servidor.');
        const evento = cuerpo.data;

        document.title = `${evento.nombre || 'Evento'} | PULSO`;
        texto('detalleNombre', evento.nombre || 'Evento sin nombre');
        texto('detalleEstado', evento.estado === 'agotado' ? 'Entradas agotadas' : 'Evento publicado');
        document.getElementById('detalleEstado')?.classList.toggle('agotado', evento.estado === 'agotado');
        texto('detalleFecha', fechaGT(evento.fecha_evento));
        texto('detalleLugar', [evento.recinto, evento.ciudad].filter(Boolean).join(' · ') || 'Lugar por confirmar');
        texto('detalleDireccion', evento.direccion || '');
        texto('detalleDescripcion', evento.descripcion || 'El organizador todavía no ha agregado una descripción.');
        prepararImagen(evento);
        mostrarZonas(Array.isArray(evento.localidades) ? evento.localidades : []);

        aviso.hidden = true;
        contenido.hidden = false;
        extra.hidden = false;
    } catch (error) {
        aviso.classList.add('error');
        aviso.textContent = error.message || 'No pudimos cargar este evento.';
    }
});
