
document.addEventListener('DOMContentLoaded', () => {
    const formulario = document.getElementById('formBuscar');
    const lista = document.getElementById('listaEventos');
    const contador = document.getElementById('contadorEventos');
    const mensaje = document.getElementById('mensajeEstado');

    // Crear elementos HTML de forma segura
    function elemento(etiqueta, clase, texto) {
        const nodo = document.createElement(etiqueta);

        if (clase) nodo.className = clase;
        if (texto !== undefined) nodo.textContent = texto;

        return nodo;
    }

    // Formatear fecha para Guatemala
    function formatearFecha(fecha) {
        const valor = new Date(fecha);

        if (Number.isNaN(valor.getTime())) {
            return 'Fecha no disponible';
        }

        return new Intl.DateTimeFormat('es-GT', {
            dateStyle: 'long',
            timeStyle: 'short',
            timeZone: 'America/Guatemala'
        }).format(valor);
    }

    // Construir una tarjeta por evento
    function crearTarjeta(evento) {
        const tarjeta = elemento('article', 'tarjeta-evento');

        const imagen = elemento('div', 'evento-imagen', '🎫');
        imagen.setAttribute('aria-hidden', 'true');

        const informacion = elemento('div', 'evento-informacion');

        const estado = elemento(
            'span',
            'evento-estado' + (evento.estado === 'agotado' ? ' agotado' : ''),
            evento.estado === 'agotado' ? 'Agotado' : 'Publicado'
        );

        const nombre = elemento('h3', '', evento.nombre);
        const fecha = elemento(
            'p',
            '',
            '📅 ' + formatearFecha(evento.fecha_evento)
        );
        const lugar = elemento(
            'p',
            '',
            '📍 ' + evento.recinto + ' — ' + evento.ciudad
        );

        const descripcion = elemento(
            'p',
            '',
            evento.descripcion || 'Sin descripción disponible.'
        );

        descripcion.hidden = true;

        const boton = elemento('button', 'btn-detalle', 'Ver descripción');
        boton.type = 'button';
        boton.setAttribute('aria-expanded', 'false');

        boton.addEventListener('click', () => {
            descripcion.hidden = !descripcion.hidden;

            const expandido = !descripcion.hidden;
            boton.setAttribute('aria-expanded', String(expandido));
            boton.textContent = expandido
                ? 'Ocultar descripción'
                : 'Ver descripción';
        });

        informacion.append(
            estado,
            nombre,
            fecha,
            lugar,
            descripcion,
            boton
        );

        tarjeta.append(imagen, informacion);
        return tarjeta;
    }

    // Mostrar resultados
    function mostrarEventos(eventos) {
        lista.replaceChildren();
        contador.textContent = `${eventos.length} evento(s)`;

        if (eventos.length === 0) {
            lista.append(
                elemento(
                    'p',
                    'mensaje-carga',
                    'No encontramos eventos con esos filtros.'
                )
            );
            return;
        }

        eventos.forEach(evento => {
            lista.appendChild(crearTarjeta(evento));
        });
    }

    // Consultar API de Laravel
    async function cargarEventos(filtros = {}) {
        const parametros = new URLSearchParams();

        Object.entries(filtros).forEach(([clave, valor]) => {
            if (valor) parametros.set(clave, valor);
        });

        mensaje.textContent = '';
        contador.textContent = 'Buscando...';
        lista.replaceChildren(
            elemento('p', 'mensaje-carga', 'Cargando eventos...')
        );

        try {
            const respuesta = await fetch(
                '/api/eventos?' + parametros.toString(),
                {
                    headers: {
                        'Accept': 'application/json'
                    }
                }
            );

            if (!respuesta.ok) {
                throw new Error('Error HTTP: ' + respuesta.status);
            }

            const resultado = await respuesta.json();

            if (!resultado.success || !Array.isArray(resultado.data)) {
                throw new Error('Respuesta inesperada del servidor');
            }

            mostrarEventos(resultado.data);

        } catch (error) {
            console.error('Error al cargar catálogo:', error);

            lista.replaceChildren();
            contador.textContent = 'Sin resultados';

            mensaje.textContent =
                'No pudimos cargar los eventos. Intenta nuevamente.';
        }
    }

    // Buscar al enviar el formulario
    formulario.addEventListener('submit', event => {
        event.preventDefault();

        const filtros = {
            nombre: document.getElementById('nombre').value.trim(),
            ciudad: document.getElementById('ciudad').value.trim(),
            fecha: document.getElementById('fecha').value
        };

        cargarEventos(filtros);
    });

    // Cargar eventos al abrir la página
    cargarEventos();
});
