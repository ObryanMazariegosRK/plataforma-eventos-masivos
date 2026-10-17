'use strict';

document.addEventListener('DOMContentLoaded', () => {
    // ==========================================================
    // CATÁLOGO DE EVENTOS MASIVOS — JS SIN REACT NI DEPENDENCIAS
    // ==========================================================
    const formulario = document.getElementById('formBuscar');
    const lista = document.getElementById('listaEventos');
    const contador = document.getElementById('contadorEventos');
    const mensaje = document.getElementById('mensajeEstado');
    const listaDestacados = document.getElementById('listaDestacados');
    const coverflow = document.getElementById('coverflow');
    const puntos = document.getElementById('puntosCarrusel');
    const estadoCarrusel = document.getElementById('estadoCarrusel');
    const btnAnterior = document.getElementById('btnAnterior');
    const btnSiguiente = document.getElementById('btnSiguiente');

    // El panel administrativo será el único que creará o modificará eventos.
    // Cuando su API publique `imagen_url`, se mostrará directamente aquí.
    // Ejemplo válido: https://dominio.com/storage/eventos/afiche.jpg
    // o /storage/eventos/afiche.jpg. No se crea ni edita nada en MySQL.
    const IMAGEN_EVENTO_ILUSTRATIVA = 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?auto=format&fit=crop&w=820&q=80';
    const RESPALDO_EVENTO_ILUSTRATIVA = '/img/eventos/demo-04.jpg';

    // Fotografía decorativa: estas NO son publicaciones de MySQL.
    // Si no hay internet, se usan carteles locales del paquete.
    const DEMOS = [
        { titulo: 'La música nos une', imagen: 'https://images.unsplash.com/photo-1459749411175-04bf5292ceea?auto=format&fit=crop&w=800&q=80', respaldo: '/img/eventos/demo-01.jpg' },
        { titulo: 'Luces de otro mundo', imagen: 'https://images.unsplash.com/photo-1501386761578-eac5c94b800a?auto=format&fit=crop&w=800&q=80', respaldo: '/img/eventos/demo-02.jpg' },
        { titulo: 'Una noche inolvidable', imagen: 'https://images.unsplash.com/photo-1470225620780-dba8ba36b745?auto=format&fit=crop&w=800&q=80', respaldo: '/img/eventos/demo-03.jpg' },
        { titulo: 'Siente el escenario', imagen: 'https://images.unsplash.com/photo-1493225457124-a3eb161ffa5f?auto=format&fit=crop&w=800&q=80', respaldo: '/img/eventos/demo-04.jpg' },
        { titulo: 'Que empiece la emoción', imagen: 'https://images.unsplash.com/photo-1506157786151-b8491531f063?auto=format&fit=crop&w=800&q=80', respaldo: '/img/eventos/demo-05.jpg' }
    ];

    let controladorBusqueda = null;
    let tarjetasDestacadas = [];
    let indiceActivo = 2;
    let inicioToque = null;

    function elemento(etiqueta, clase = '', texto) {
        const nodo = document.createElement(etiqueta);
        if (clase) nodo.className = clase;
        if (texto !== undefined && texto !== null) nodo.textContent = String(texto);
        return nodo;
    }

    function fechaValida(fecha) {
        const valor = new Date(fecha);
        return Number.isNaN(valor.getTime()) ? null : valor;
    }

    function formatearFecha(fecha) {
        const valor = fechaValida(fecha);
        if (!valor) return 'Fecha por confirmar';
        return new Intl.DateTimeFormat('es-GT', {
            dateStyle: 'long', timeStyle: 'short', timeZone: 'America/Guatemala'
        }).format(valor);
    }

    function parteFecha(fecha, parte) {
        const valor = fechaValida(fecha);
        if (!valor) return parte === 'month' ? '---' : '--';
        return new Intl.DateTimeFormat('es-GT', {
            [parte]: parte === 'day' ? '2-digit' : 'short',
            timeZone: 'America/Guatemala'
        }).format(valor).replace('.', '').toUpperCase();
    }

    function imagenConRespaldo(url, texto, respaldo = RESPALDO_EVENTO_ILUSTRATIVA) {
        const imagen = elemento('img', 'destacado-imagen');
        imagen.alt = texto;
        imagen.loading = 'lazy';
        imagen.decoding = 'async';
        imagen.src = url;
        imagen.addEventListener('error', () => {
            // El atributo evita un bucle si también falta el archivo local.
            if (imagen.dataset.fallback !== '1') {
                imagen.dataset.fallback = '1';
                imagen.src = respaldo;
            }
        });
        return imagen;
    }

    // Imagen administrada: si la API aún no la incluye, usar foto de referencia.
    // Se aceptan URLs https/http y rutas locales desde la raíz del sitio.
    function resolverImagenEvento(evento) {
        const origen = typeof evento.imagen_url === 'string'
            ? evento.imagen_url.trim() : '';
        const url = /^(https?:\/\/|\/(?!\/))/i.test(origen) ? origen : null;
        return {
            url: url || IMAGEN_EVENTO_ILUSTRATIVA,
            ilustrativa: !url
        };
    }

    // ==========================================================
    // TARJETAS NORMALES, CON DATOS REALES DE /api/eventos
    // ==========================================================
    function crearTarjeta(evento) {
        const tarjeta = elemento('article', 'tarjeta-evento');
        const thumb = elemento('div', 'evento-thumb');
        const foto = resolverImagenEvento(evento);
        const imagen = imagenConRespaldo(
            foto.url,
            foto.ilustrativa ? 'Fotografía de referencia' : `Afiche de ${evento.nombre || 'evento'}`,
            RESPALDO_EVENTO_ILUSTRATIVA
        );
        imagen.className = 'evento-foto';
        thumb.appendChild(imagen);
        if (foto.ilustrativa) {
            thumb.appendChild(elemento('span', 'foto-etiqueta', 'FOTO ILUSTRATIVA'));
        }
        thumb.appendChild(elemento('span', 'evento-fecha-box', `${parteFecha(evento.fecha_evento, 'day')} ${parteFecha(evento.fecha_evento, 'month')}`));

        const informacion = elemento('div', 'evento-informacion');
        const agotado = evento.estado === 'agotado';
        const estado = elemento('span', `evento-estado${agotado ? ' agotado' : ''}`, agotado ? 'Agotado' : 'Publicado');
        const titulo = elemento('h3', '', evento.nombre || 'Evento sin nombre');
        const fecha = elemento('p', 'evento-meta', `◷ ${formatearFecha(evento.fecha_evento)}`);
        const lugar = elemento('p', 'evento-meta', `⌖ ${evento.recinto || 'Recinto por confirmar'} · ${evento.ciudad || 'Ciudad por confirmar'}`);
        const descripcion = elemento('p', 'evento-descripcion', evento.descripcion || 'Sin descripción disponible.');
        descripcion.hidden = true;
        const boton = elemento('button', 'btn-descripcion', 'Ver descripción +');
        boton.type = 'button';
        boton.setAttribute('aria-expanded', 'false');
        boton.addEventListener('click', () => {
            descripcion.hidden = !descripcion.hidden;
            boton.setAttribute('aria-expanded', String(!descripcion.hidden));
            boton.textContent = descripcion.hidden ? 'Ver descripción +' : 'Ocultar descripción −';
        });
        const verEvento = elemento('a', 'btn-detalle', 'Ver evento ↗');
        verEvento.href = `/eventos/${encodeURIComponent(evento.id)}`;
        informacion.append(estado, titulo, fecha, lugar, descripcion, boton, verEvento);
        tarjeta.append(thumb, informacion);
        return tarjeta;
    }

    // ==========================================================
    // COVERFLOW: 5 TARJETAS CON PROFUNDIDAD Y CONTROL REAL
    // ==========================================================
    function crearDatosCarrusel(eventos) {
        const tarjetas = new Array(5).fill(null);
        const posicionesReales = [2, 3, 1, 4, 0];
        eventos.slice(0, 5).forEach((evento, i) => {
            const foto = resolverImagenEvento(evento);
            tarjetas[posicionesReales[i]] = {
                demo: false,
                titulo: evento.nombre || 'Evento sin nombre',
                subtitulo: `${evento.ciudad || 'Ciudad por confirmar'} · ${formatearFecha(evento.fecha_evento)}`,
                imagen: foto.url,
                ilustrativa: foto.ilustrativa,
                respaldo: RESPALDO_EVENTO_ILUSTRATIVA,
                evento
            };
        });
        let posDemo = 0;
        return tarjetas.map(item => item || {
            demo: true,
            titulo: DEMOS[posDemo].titulo,
            subtitulo: 'Inspiración visual · No disponible para compra',
            imagen: DEMOS[posDemo].imagen,
            respaldo: DEMOS[posDemo++].respaldo
        });
    }

    function crearTarjetaDestacada(datos, i) {
        const tarjeta = elemento('article', `destacado-tarjeta${datos.demo ? ' tarjeta-demo' : ' tarjeta-real'}`);
        tarjeta.dataset.indice = String(i);
        tarjeta.setAttribute('aria-roledescription', 'tarjeta');
        tarjeta.setAttribute('aria-label', datos.demo ? `${datos.titulo}, vista de muestra` : `${datos.titulo}, evento publicado`);
        tarjeta.tabIndex = -1;
        tarjeta.appendChild(imagenConRespaldo(
            datos.imagen,
            datos.demo || datos.ilustrativa ? 'Imagen ilustrativa: ' + datos.titulo : 'Afiche de ' + datos.titulo,
            datos.respaldo || RESPALDO_EVENTO_ILUSTRATIVA
        ));
        const cuerpo = elemento('div', 'destacado-info');
        cuerpo.appendChild(elemento('span', `destacado-etiqueta${datos.demo ? ' es-demo' : ''}`,
            datos.demo ? 'VISTA DE MUESTRA' : 'EVENTO PUBLICADO'));
        cuerpo.appendChild(elemento('h3', '', datos.titulo));
        cuerpo.appendChild(elemento('p', 'destacado-subtitulo', datos.subtitulo));
        if (!datos.demo) {
            if (datos.ilustrativa) {
                cuerpo.appendChild(elemento('span', 'nota-foto', 'Fotografía ilustrativa · Afiche pendiente del administrador'));
            }
            const enlace = elemento('a', 'destacado-boton', 'Ver evento ↗');
            enlace.href = `/eventos/${encodeURIComponent(datos.evento.id)}`;
            cuerpo.appendChild(enlace);
        }
        tarjeta.appendChild(cuerpo);
        tarjeta.addEventListener('click', (ev) => {
            if (ev.target.closest('a')) return;
            if (indiceActivo !== i) seleccionarTarjeta(i);
        });
        return tarjeta;
    }

    function distanciaCircular(indice, activo, total) {
        let distancia = indice - activo;
        if (distancia > total / 2) distancia -= total;
        if (distancia < -total / 2) distancia += total;
        return distancia;
    }

    function actualizarCarrusel(anunciar = true) {
        if (!tarjetasDestacadas.length) return;
        const n = tarjetasDestacadas.length;
        [...listaDestacados.children].forEach((tarjeta, indice) => {
            const posicion = distanciaCircular(indice, indiceActivo, n);
            tarjeta.dataset.posicion = String(posicion);
            const activa = posicion === 0;
            tarjeta.setAttribute('aria-hidden', String(!activa));
            tarjeta.tabIndex = activa ? 0 : -1;
            // Los enlaces de tarjetas laterales no participan en el tabulador.
            const enlace = tarjeta.querySelector('a');
            if (enlace) enlace.tabIndex = activa ? 0 : -1;
        });
        if (puntos) {
            [...puntos.children].forEach((punto, indice) => {
                punto.classList.toggle('activo', indice === indiceActivo);
                punto.setAttribute('aria-current', indice === indiceActivo ? 'true' : 'false');
            });
        }
        if (anunciar && estadoCarrusel) {
            estadoCarrusel.textContent = `${indiceActivo + 1} de ${n}: ${tarjetasDestacadas[indiceActivo].titulo}`;
        }
    }

    function seleccionarTarjeta(indice) {
        indiceActivo = (indice + tarjetasDestacadas.length) % tarjetasDestacadas.length;
        actualizarCarrusel();
    }

    function moverCarrusel(direccion) {
        if (tarjetasDestacadas.length > 1) seleccionarTarjeta(indiceActivo + direccion);
    }

    function mostrarDestacados(eventos) {
        if (!listaDestacados) return;
        tarjetasDestacadas = crearDatosCarrusel(eventos);
        indiceActivo = 2;
        listaDestacados.replaceChildren();
        tarjetasDestacadas.forEach((datos, i) => {
            listaDestacados.appendChild(crearTarjetaDestacada(datos, i));
        });
        if (puntos) {
            puntos.replaceChildren();
            tarjetasDestacadas.forEach((datos, i) => {
                const boton = elemento('button', 'punto-carrusel');
                boton.type = 'button';
                boton.setAttribute('aria-label', `Mostrar tarjeta ${i + 1}: ${datos.titulo}`);
                boton.addEventListener('click', () => seleccionarTarjeta(i));
                puntos.appendChild(boton);
            });
        }
        actualizarCarrusel(false);
    }

    btnAnterior?.addEventListener('click', () => moverCarrusel(-1));
    btnSiguiente?.addEventListener('click', () => moverCarrusel(1));
    coverflow?.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowLeft') { event.preventDefault(); moverCarrusel(-1); }
        if (event.key === 'ArrowRight') { event.preventDefault(); moverCarrusel(1); }
    });
    coverflow?.addEventListener('touchstart', (e) => {
        inicioToque = e.changedTouches[0]?.clientX ?? null;
    }, { passive: true });
    coverflow?.addEventListener('touchend', (e) => {
        if (inicioToque === null) return;
        const distancia = (e.changedTouches[0]?.clientX ?? inicioToque) - inicioToque;
        if (Math.abs(distancia) > 42) moverCarrusel(distancia < 0 ? 1 : -1);
        inicioToque = null;
    }, { passive: true });

    // Brillo suave siguiendo el cursor sobre la tarjeta activa.
    coverflow?.addEventListener('pointermove', (event) => {
        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
        const tarjeta = event.target.closest('.destacado-tarjeta[data-posicion="0"]');
        if (!tarjeta) return;
        const rect = tarjeta.getBoundingClientRect();
        tarjeta.style.setProperty('--brillo-x', `${((event.clientX - rect.left) / rect.width) * 100}%`);
        tarjeta.style.setProperty('--brillo-y', `${((event.clientY - rect.top) / rect.height) * 100}%`);
    });

    // ==========================================================
    // API, FILTROS Y MENSAJES — IGUALES CONTRATOS QUE ANTES
    // ==========================================================
    function mostrarEventos(eventos) {
        if (!lista || !contador) return;
        lista.replaceChildren();
        contador.textContent = `${eventos.length} evento(s)`;
        if (!eventos.length) {
            lista.appendChild(elemento('p', 'mensaje-vacio', 'No encontramos eventos con esos filtros. Prueba otra búsqueda.'));
            return;
        }
        eventos.forEach(evento => lista.appendChild(crearTarjeta(evento)));
    }

    async function cargarEventos(filtros = {}, inicial = false) {
        if (!lista || !contador || !mensaje) return;
        controladorBusqueda?.abort();
        const controladorActual = new AbortController();
        controladorBusqueda = controladorActual;
        const parametros = new URLSearchParams();
        Object.entries(filtros).forEach(([clave, valor]) => {
            if (valor) parametros.set(clave, valor);
        });
        mensaje.textContent = '';
        contador.textContent = 'Buscando...';
        lista.replaceChildren(elemento('p', 'mensaje-carga', 'Cargando eventos...'));

        try {
            const respuesta = await fetch('/api/eventos?' + parametros.toString(), {
                headers: { Accept: 'application/json' },
                signal: controladorActual.signal
            });
            if (!respuesta.ok) throw new Error(`Error HTTP ${respuesta.status}`);
            const resultado = await respuesta.json();
            if (resultado.success !== true || !Array.isArray(resultado.data)) {
                throw new Error('Respuesta inesperada de /api/eventos');
            }
            mostrarEventos(resultado.data);
            // Los destacados representan la portada completa, no los filtros del buscador.
            if (inicial) mostrarDestacados(resultado.data);
        } catch (error) {
            if (error.name === 'AbortError') return;
            console.error('Error al cargar eventos:', error);
            lista.replaceChildren(elemento('p', 'mensaje-vacio', 'No pudimos cargar los eventos. Intenta nuevamente.'));
            contador.textContent = 'Sin resultados';
            mensaje.textContent = 'Comprueba tu conexión e inténtalo otra vez.';
            if (inicial) mostrarDestacados([]);
        } finally {
            if (controladorBusqueda === controladorActual) controladorBusqueda = null;
        }
    }

    formulario?.addEventListener('submit', event => {
        event.preventDefault();
        const filtros = {
            nombre: document.getElementById('nombre')?.value.trim() || '',
            ciudad: document.getElementById('ciudad')?.value.trim() || '',
            fecha: document.getElementById('fecha')?.value || ''
        };
        cargarEventos(filtros, false);
    });

    // Efectos de aparición al hacer scroll inspirados en Animated Content.
    if ('IntersectionObserver' in window) {
        document.documentElement.classList.add('animaciones-activas');
        const observador = new IntersectionObserver(entradas => {
            entradas.forEach(entrada => {
                if (entrada.isIntersecting) {
                    entrada.target.classList.add('visible');
                    observador.unobserve(entrada.target);
                }
            });
        }, { threshold: 0.1 });
        document.querySelectorAll('.reveal').forEach(nodo => observador.observe(nodo));
    }

    // Sólo los datos publicados por la API cuentan como eventos reales.
    // Las otras tarjetas son decorativas y NO se pueden reservar.
    cargarEventos({}, true);
});
