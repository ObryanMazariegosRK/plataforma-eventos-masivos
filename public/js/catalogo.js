
'use strict';

document.addEventListener('DOMContentLoaded', () => {

    // =====================================================
    // PULSO - CATÁLOGO DE EVENTOS
    // =====================================================

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

    // Fotos de teatro para los eventos sin afiche, en tamaño de tarjeta (800 px de alto, WebP):
    // las originales de 2000 px tardaban en decodificarse y trababan el scroll en celular.
    // Son dos fotos; cada entrada muestra otro encuadre (object-position) para que no se repitan.
    const FOTOS_TEATRO = [
        { imagen: '/img/landing/tarjetas/auditorio-general.webp', encuadre: '50% 50%' },
        { imagen: '/img/landing/tarjetas/auditorio-butacas.webp', encuadre: '25% 50%' },
        { imagen: '/img/landing/tarjetas/auditorio-general.webp', encuadre: '10% 50%' },
        { imagen: '/img/landing/tarjetas/auditorio-butacas.webp', encuadre: '80% 50%' },
        { imagen: '/img/landing/tarjetas/auditorio-general.webp', encuadre: '90% 50%' }
    ];

    const RESPALDO_EVENTO_ILUSTRATIVA =
        '/img/landing/tarjetas/auditorio-general.webp';

    let controladorBusqueda = null;
    let tarjetasDestacadas = [];
    let indiceActivo = 0;
    // Con 1 o 2 eventos no hay efecto 3D: las tarjetas se muestran lado a lado, sin flechas
    let modoPlano = false;
    let inicioToque = null;

    // =====================================================
    // UTILIDADES
    // =====================================================

    function elemento(etiqueta, clase = '', texto = null) {
        const nodo = document.createElement(etiqueta);

        if (clase) {
            nodo.className = clase;
        }

        if (texto !== null && texto !== undefined) {
            nodo.textContent = String(texto);
        }

        return nodo;
    }

    function fechaValida(fecha) {
        const valor = new Date(fecha);

        return Number.isNaN(valor.getTime())
            ? null
            : valor;
    }

    function formatearFecha(fecha) {
        const valor = fechaValida(fecha);

        if (!valor) {
            return 'Fecha por confirmar';
        }

        return new Intl.DateTimeFormat('es-GT', {
            dateStyle: 'long',
            timeStyle: 'short',
            timeZone: 'America/Guatemala'
        }).format(valor);
    }

    function parteFecha(fecha, parte) {
        const valor = fechaValida(fecha);

        if (!valor) {
            return parte === 'month' ? '---' : '--';
        }

        return new Intl.DateTimeFormat('es-GT', {
            [parte]: parte === 'day' ? '2-digit' : 'short',
            timeZone: 'America/Guatemala'
        })
            .format(valor)
            .replace('.', '')
            .toUpperCase();
    }

    function imagenConRespaldo(
        url,
        texto,
        respaldo = RESPALDO_EVENTO_ILUSTRATIVA,
        encuadre = ''
    ) {
        const imagen = elemento('img', 'destacado-imagen');

        if (encuadre) {
            imagen.style.objectPosition = encuadre;
        }

        imagen.alt = texto;
        imagen.loading = 'lazy';
        imagen.decoding = 'async';
        imagen.src = url;

        imagen.addEventListener('error', () => {
            if (imagen.dataset.fallback !== '1') {
                imagen.dataset.fallback = '1';
                imagen.src = respaldo;
            }
        });

        return imagen;
    }

    function resolverImagenEvento(evento) {
        const origen = typeof evento.imagen_url === 'string'
            ? evento.imagen_url.trim()
            : '';

        const url = /^(https?:\/\/|\/(?!\/))/i.test(origen)
            ? origen
            : null;

        // Sin afiche: una foto de teatro, siempre la misma para el mismo evento.
        const teatro = FOTOS_TEATRO[Math.abs(Number(evento.id) || 0) % FOTOS_TEATRO.length];

        return {
            url: url || teatro.imagen,
            encuadre: url ? '' : teatro.encuadre,
            ilustrativa: !url
        };
    }

    // =====================================================
    // TARJETAS DE EVENTOS REALES
    // =====================================================

    function crearTarjeta(evento) {
        const tarjeta = elemento('article', 'tarjeta-evento');
        const thumb = elemento('div', 'evento-thumb');

        const foto = resolverImagenEvento(evento);

        const imagen = imagenConRespaldo(
            foto.url,
            foto.ilustrativa
                ? 'Fotografía de referencia'
                : `Afiche de ${evento.nombre || 'evento'}`,
            RESPALDO_EVENTO_ILUSTRATIVA,
            foto.encuadre
        );

        imagen.className = 'evento-foto';
        thumb.appendChild(imagen);

        if (foto.ilustrativa) {
            thumb.appendChild(
                elemento('span', 'foto-etiqueta', 'Foto ilustrativa')
            );
        }

        thumb.appendChild(
            elemento(
                'span',
                'evento-fecha-box',
                `${parteFecha(evento.fecha_evento, 'day')} ${parteFecha(evento.fecha_evento, 'month')}`
            )
        );

        const informacion = elemento('div', 'evento-informacion');
        const agotado = evento.estado === 'agotado';

        const estado = elemento(
            'span',
            `evento-estado${agotado ? ' agotado' : ''}`,
            agotado ? 'Agotado' : 'En venta'
        );

        const titulo = elemento(
            'h3',
            '',
            evento.nombre || 'Evento sin nombre'
        );

        const fecha = elemento(
            'p',
            'evento-meta',
            formatearFecha(evento.fecha_evento)
        );

        const lugar = elemento(
            'p',
            'evento-meta',
            `${evento.recinto || 'Recinto por confirmar'} · ${evento.ciudad || 'Ciudad por confirmar'}`
        );

        const descripcion = elemento(
            'p',
            'evento-descripcion',
            evento.descripcion || 'Sin descripción disponible.'
        );

        descripcion.hidden = true;

        const boton = elemento(
            'button',
            'btn-descripcion',
            'Ver descripción'
        );

        boton.type = 'button';
        boton.setAttribute('aria-expanded', 'false');

        boton.addEventListener('click', () => {
            descripcion.hidden = !descripcion.hidden;

            boton.setAttribute(
                'aria-expanded',
                String(!descripcion.hidden)
            );

            boton.textContent = descripcion.hidden
                ? 'Ver descripción'
                : 'Ocultar descripción';
        });

        const verEvento = elemento(
            'a',
            'btn-detalle',
            'Ver evento'
        );

        verEvento.href =
            `/eventos/${encodeURIComponent(evento.id)}/experiencia`;   // recorrido + plano de asientos

        informacion.append(
            estado,
            titulo,
            fecha,
            lugar,
            descripcion,
            boton,
            verEvento
        );

        tarjeta.append(thumb, informacion);

        return tarjeta;
    }

    // =====================================================
    // CARRUSEL DE EVENTOS
    // =====================================================

    // Solo eventos reales a la venta: los 5 más próximos (la API los ordena por fecha)
    function crearDatosCarrusel(eventos) {
        return eventos.slice(0, 5).map(evento => {
            const foto = resolverImagenEvento(evento);

            return {
                demo: false,
                titulo: evento.nombre || 'Evento sin nombre',
                subtitulo:
                    `${evento.ciudad || 'Ciudad por confirmar'} · ${formatearFecha(evento.fecha_evento)}`,
                imagen: foto.url,
                encuadre: foto.encuadre,
                ilustrativa: foto.ilustrativa,
                respaldo: RESPALDO_EVENTO_ILUSTRATIVA,
                evento
            };
        });
    }

    function crearTarjetaDestacada(datos, i) {
        const tarjeta = elemento(
            'article',
            `destacado-tarjeta${datos.demo ? ' tarjeta-demo' : ' tarjeta-real'}`
        );

        tarjeta.dataset.indice = String(i);
        tarjeta.setAttribute('aria-roledescription', 'tarjeta');

        tarjeta.setAttribute(
            'aria-label',
            datos.demo
                ? `${datos.titulo}, vista de muestra`
                : `${datos.titulo}, evento publicado`
        );

        tarjeta.tabIndex = -1;

        const foto = imagenConRespaldo(
            datos.imagen,
            datos.demo || datos.ilustrativa
                ? `Imagen ilustrativa: ${datos.titulo}`
                : `Afiche de ${datos.titulo}`,
            datos.respaldo || RESPALDO_EVENTO_ILUSTRATIVA,
            datos.encuadre
        );

        // El carrusel está a una pantalla de la portada: sus fotos se piden desde el inicio y
        // se decodifican fuera del hilo principal, para que no frenen el scroll al llegar a él
        foto.loading = 'eager';
        foto.decoding = 'async';
        tarjeta.appendChild(foto);

        const cuerpo = elemento('div', 'destacado-info');

        cuerpo.appendChild(
            elemento(
                'span',
                `destacado-etiqueta${datos.demo ? ' es-demo' : ''}`,
                datos.demo
                    ? 'Muestra'
                    : 'En cartelera'
            )
        );

        cuerpo.appendChild(
            elemento('h3', '', datos.titulo)
        );

        cuerpo.appendChild(
            elemento('p', 'destacado-subtitulo', datos.subtitulo)
        );

        if (!datos.demo) {
            if (datos.ilustrativa) {
                cuerpo.appendChild(
                    elemento(
                        'span',
                        'nota-foto',
                        'Foto ilustrativa'
                    )
                );
            }

            const enlace = elemento(
                'a',
                'destacado-boton',
                'Ver evento →'
            );

            enlace.href =
                `/eventos/${encodeURIComponent(datos.evento.id)}/experiencia`;   // recorrido + plano de asientos

            cuerpo.appendChild(enlace);
        }

        tarjeta.appendChild(cuerpo);

        tarjeta.addEventListener('click', event => {
            if (event.target.closest('a')) {
                return;
            }

            if (!modoPlano && indiceActivo !== i) {
                seleccionarTarjeta(i);
            }
        });

        return tarjeta;
    }

    function distanciaCircular(indice, activo, total) {
        let distancia = indice - activo;

        if (distancia > total / 2) {
            distancia -= total;
        }

        if (distancia < -total / 2) {
            distancia += total;
        }

        return distancia;
    }

    function actualizarCarrusel(anunciar = true) {
        if (!tarjetasDestacadas.length || !listaDestacados) {
            return;
        }

        const total = tarjetasDestacadas.length;

        [...listaDestacados.children].forEach(
            (tarjeta, indice) => {
                const posicion = distanciaCircular(
                    indice,
                    indiceActivo,
                    total
                );

                tarjeta.dataset.posicion = String(posicion);

                const activa = posicion === 0;

                tarjeta.setAttribute(
                    'aria-hidden',
                    String(!activa)
                );

                tarjeta.tabIndex = activa ? 0 : -1;

                const enlace = tarjeta.querySelector('a');

                if (enlace) {
                    enlace.tabIndex = activa ? 0 : -1;
                }
            }
        );

        if (puntos) {
            [...puntos.children].forEach((punto, indice) => {
                punto.classList.toggle(
                    'activo',
                    indice === indiceActivo
                );

                punto.setAttribute(
                    'aria-current',
                    indice === indiceActivo
                        ? 'true'
                        : 'false'
                );
            });
        }

        if (anunciar && estadoCarrusel) {
            estadoCarrusel.textContent =
                `${indiceActivo + 1} de ${total}: ${tarjetasDestacadas[indiceActivo].titulo}`;
        }
    }

    function seleccionarTarjeta(indice) {
        if (!tarjetasDestacadas.length) {
            return;
        }

        indiceActivo =
            (indice + tarjetasDestacadas.length) %
            tarjetasDestacadas.length;

        actualizarCarrusel();
    }

    function moverCarrusel(direccion) {
        if (!modoPlano && tarjetasDestacadas.length > 1) {
            seleccionarTarjeta(indiceActivo + direccion);
        }
    }

    function mostrarDestacados(eventos) {
        if (!listaDestacados) {
            return;
        }

        tarjetasDestacadas = crearDatosCarrusel(eventos);
        indiceActivo = 0;   // el evento más próximo, al centro
        modoPlano = tarjetasDestacadas.length <= 2;

        // Sin eventos a la venta no se muestra la sección (la cartelera de abajo lo explica)
        const seccion = document.getElementById('destacados');
        if (seccion) {
            seccion.hidden = tarjetasDestacadas.length === 0;
        }
        if (!tarjetasDestacadas.length) {
            return;
        }

        listaDestacados.replaceChildren();
        listaDestacados.classList.toggle('escenario-destacados--plano', modoPlano);
        listaDestacados.dataset.cantidad = String(tarjetasDestacadas.length);

        tarjetasDestacadas.forEach((datos, i) => {
            listaDestacados.appendChild(
                crearTarjetaDestacada(datos, i)
            );
        });

        const barra = document.getElementById('barraCarrusel');
        if (barra) {
            barra.hidden = modoPlano;
        }

        if (modoPlano) {
            // Todas visibles y accesibles: no hay tarjeta "activa"
            [...listaDestacados.children].forEach(tarjeta => {
                tarjeta.dataset.posicion = 'plano';
                tarjeta.removeAttribute('aria-hidden');
                tarjeta.tabIndex = -1;
                const enlace = tarjeta.querySelector('a');
                if (enlace) enlace.tabIndex = 0;
            });
            return;
        }

        if (puntos) {
            puntos.replaceChildren();

            tarjetasDestacadas.forEach((datos, i) => {
                const boton = elemento(
                    'button',
                    'punto-carrusel'
                );

                boton.type = 'button';

                boton.setAttribute(
                    'aria-label',
                    `Mostrar tarjeta ${i + 1}: ${datos.titulo}`
                );

                boton.addEventListener(
                    'click',
                    () => seleccionarTarjeta(i)
                );

                puntos.appendChild(boton);
            });
        }

        actualizarCarrusel(false);
    }

    btnAnterior?.addEventListener(
        'click',
        () => moverCarrusel(-1)
    );

    btnSiguiente?.addEventListener(
        'click',
        () => moverCarrusel(1)
    );

    coverflow?.addEventListener('keydown', event => {
        if (event.key === 'ArrowLeft') {
            event.preventDefault();
            moverCarrusel(-1);
        }

        if (event.key === 'ArrowRight') {
            event.preventDefault();
            moverCarrusel(1);
        }
    });

    coverflow?.addEventListener(
        'touchstart',
        event => {
            inicioToque =
                event.changedTouches[0]?.clientX ?? null;
        },
        { passive: true }
    );

    coverflow?.addEventListener(
        'touchend',
        event => {
            if (inicioToque === null) {
                return;
            }

            const distancia =
                (event.changedTouches[0]?.clientX ?? inicioToque)
                - inicioToque;

            if (Math.abs(distancia) > 42) {
                moverCarrusel(distancia < 0 ? 1 : -1);
            }

            inicioToque = null;
        },
        { passive: true }
    );

    coverflow?.addEventListener('pointermove', event => {
        if (
            window.matchMedia(
                '(prefers-reduced-motion: reduce)'
            ).matches
        ) {
            return;
        }

        const tarjeta = event.target.closest(
            '.destacado-tarjeta[data-posicion="0"]'
        );

        if (!tarjeta) {
            return;
        }

        const rect = tarjeta.getBoundingClientRect();

        tarjeta.style.setProperty(
            '--brillo-x',
            `${((event.clientX - rect.left) / rect.width) * 100}%`
        );

        tarjeta.style.setProperty(
            '--brillo-y',
            `${((event.clientY - rect.top) / rect.height) * 100}%`
        );
    });

    // =====================================================
    // API PÚBLICA Y BÚSQUEDA
    // =====================================================

    // ---- Cartelera paginada: 6 funciones por tanda; "Ver más funciones" trae la siguiente ----
    const verMas = document.getElementById('verMasEventos');
    let filtrosActuales = {};
    let paginaActual = 0;
    let totalEventos = 0;
    let mostrados = 0;

    // agregar = true: la tanda se suma debajo de las que ya están (botón "Ver más")
    function mostrarEventos(eventos, total, agregar = false) {
        if (!lista || !contador) {
            return;
        }

        if (!agregar) {
            lista.replaceChildren();
            mostrados = 0;
        }

        totalEventos = total;
        contador.textContent =
            total === 1 ? '1 función' : `${total} funciones`;

        if (!total) {
            lista.appendChild(
                elemento(
                    'p',
                    'mensaje-vacio',
                    'No encontramos eventos con esos filtros. Prueba otra búsqueda.'
                )
            );
        }

        // Escalonadas por columna (3 por fila en escritorio)
        eventos.forEach((evento, i) => {
            const tarjeta = crearTarjeta(evento);
            lista.appendChild(tarjeta);
            revelar(tarjeta, (i % 3) * 140);
        });
        mostrados += eventos.length;

        actualizarVerMas();
    }

    function actualizarVerMas(cargando = false) {
        if (!verMas) {
            return;
        }

        const quedan = totalEventos - mostrados;
        verMas.hidden = quedan <= 0;
        verMas.disabled = cargando;
        verMas.replaceChildren(
            cargando ? 'Cargando…' : 'Ver más funciones',
            ...(cargando ? [] : [elemento('span', '', ` · quedan ${quedan}`)])
        );
    }

    async function pedirPagina(filtros, pagina, señal) {
        const parametros = new URLSearchParams({ pagina: String(pagina) });

        Object.entries(filtros).forEach(([clave, valor]) => {
            if (valor) {
                parametros.set(clave, valor);
            }
        });

        const respuesta = await fetch(
            `/api/eventos?${parametros.toString()}`,
            {
                headers: {
                    Accept: 'application/json'
                },
                signal: señal
            }
        );

        if (!respuesta.ok) {
            throw new Error(
                `Error HTTP ${respuesta.status}`
            );
        }

        const resultado = await respuesta.json();

        if (
            resultado.success !== true ||
            !Array.isArray(resultado.data) ||
            !resultado.meta
        ) {
            throw new Error(
                'Respuesta inesperada de /api/eventos'
            );
        }

        return resultado;
    }

    // Primera tanda (al abrir o al buscar): reemplaza la cartelera
    async function cargarEventos(filtros = {}, inicial = false) {
        if (!lista || !contador || !mensaje) {
            return;
        }

        controladorBusqueda?.abort();

        const controladorActual = new AbortController();
        controladorBusqueda = controladorActual;
        filtrosActuales = filtros;

        mensaje.textContent = '';
        contador.textContent = 'Buscando…';
        if (verMas) verMas.hidden = true;

        lista.replaceChildren(
            elemento(
                'p',
                'mensaje-carga',
                'Cargando la cartelera…'
            )
        );

        try {
            const resultado = await pedirPagina(filtros, 1, controladorActual.signal);

            paginaActual = 1;
            mostrarEventos(resultado.data, resultado.meta.total);

            // El carrusel usa los primeros de esta misma tanda (no hace otra consulta)
            if (inicial) {
                mostrarDestacados(resultado.data);
            }

        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }

            console.error(
                'Error al cargar eventos:',
                error
            );

            lista.replaceChildren(
                elemento(
                    'p',
                    'mensaje-vacio',
                    'No pudimos cargar los eventos. Intenta nuevamente.'
                )
            );

            contador.textContent = 'Sin resultados';

            mensaje.textContent =
                'Comprueba tu conexión e inténtalo otra vez.';

            if (inicial) {
                mostrarDestacados([]);
            }

        } finally {
            if (controladorBusqueda === controladorActual) {
                controladorBusqueda = null;
            }
        }
    }

    // "Ver más funciones": siguiente tanda, debajo de las que ya se ven
    verMas?.addEventListener('click', async () => {
        actualizarVerMas(true);
        mensaje.textContent = '';

        try {
            const resultado = await pedirPagina(filtrosActuales, paginaActual + 1);
            paginaActual += 1;
            mostrarEventos(resultado.data, resultado.meta.total, true);
        } catch (error) {
            console.error('Error al cargar más eventos:', error);
            mensaje.textContent = 'No pudimos cargar más funciones. Inténtalo otra vez.';
            actualizarVerMas();
        }
    });

    formulario?.addEventListener('submit', event => {
        event.preventDefault();

        const filtros = {
            nombre:
                document.getElementById('nombre')?.value.trim() || '',
            ciudad:
                document.getElementById('ciudad')?.value.trim() || '',
            fecha:
                document.getElementById('fecha')?.value || ''
        };

        cargarEventos(filtros, false);
    });

    // =====================================================
    // EFECTOS VISUALES
    // =====================================================

    // Las secciones y las tarjetas aparecen con calma al entrar en pantalla (desvanecido y
    // subiendo un poco). Sin IntersectionObserver o con "reducir movimiento" se ven directo.
    const observador = 'IntersectionObserver' in window
        ? new IntersectionObserver(
            entradas => {
                entradas.forEach(entrada => {
                    if (entrada.isIntersecting) {
                        entrada.target.classList.add('visible');
                        observador.unobserve(entrada.target);
                    }
                });
            },
            {
                threshold: 0.12,
                rootMargin: '0px 0px -6% 0px'   // un poco antes del borde: se nota que "entra"
            }
        )
        : null;

    if (observador) {
        document.documentElement.classList.add('animaciones-activas');
    }

    // retraso en ms: para que las tarjetas de una misma fila aparezcan una tras otra
    function revelar(nodo, retraso = 0) {
        if (!nodo) {
            return;
        }

        nodo.classList.add('reveal');
        nodo.style.setProperty('--retraso', `${retraso}ms`);
        observador?.observe(nodo);
    }

    document.querySelectorAll('.reveal').forEach(nodo => revelar(nodo));
    revelar(document.querySelector('.destacados-titulo'));
    // El carrusel no lleva animación de aparición: sumada al 3D era lo que frenaba el scroll en celular
    revelar(document.getElementById('barraCarrusel'), 300);

    // =====================================================
    // MENÚ DE SESIÓN DEL CATÁLOGO
    // =====================================================

    async function iniciarMenuSesion() {
        const nav = document.querySelector(
            '.navbar .nav-enlaces'
        );

        if (!nav) {
            return;
        }

        // Evita duplicar el menú si otro script ya lo creó.
        if (nav.dataset.sesionPulso === 'integrada') {
            return;
        }

        nav.dataset.sesionPulso = 'integrada';

        const token = localStorage.getItem('auth_token');

        if (!token) {
            return;
        }

        const enlaceLogin = nav.querySelector(
            'a[href="/login"]'
        );

        const enlaceRegistro = nav.querySelector(
            'a[href="/registro"]'
        );

        if (!enlaceLogin || !enlaceRegistro) {
            return;
        }

        // Validar la sesión con el backend.
        let respuesta;

        try {
            respuesta = await fetch('/api/auth/perfil', {
                method: 'GET',
                headers: {
                    Accept: 'application/json',
                    Authorization: `Bearer ${token}`
                },
                cache: 'no-store'
            });

        } catch (error) {
            console.warn(
                'No se pudo consultar la sesión:',
                error
            );

            return;
        }

        if (respuesta.status === 401) {
            localStorage.removeItem('auth_token');
            return;
        }

        if (!respuesta.ok) {
            return;
        }

        const datos = await respuesta
            .json()
            .catch(() => null);

        const usuario =
            datos?.usuario ??
            datos?.data ??
            datos;

        if (
            !usuario ||
            typeof usuario !== 'object' ||
            !usuario.rol
        ) {
            return;
        }

        const nombre =
            String(usuario.nombre || 'Mi cuenta').trim();

        const apellido =
            String(usuario.apellido || '').trim();

        const rol =
            String(usuario.rol).trim().toLowerCase();

        const iniciales =
            `${nombre.charAt(0)}${apellido.charAt(0)}`
                .trim()
                .toUpperCase() || 'U';

        // =================================================
        // ESTILOS DEL MENÚ
        // =================================================

        if (!document.getElementById('pulso-estilos-cuenta')) {
            const estilos = document.createElement('style');

            estilos.id = 'pulso-estilos-cuenta';

            estilos.textContent = `
                .pulso-cuenta {
                    position: relative;
                    display: inline-flex;
                    align-items: center;
                }

                .pulso-cuenta summary {
                    list-style: none;
                    cursor: pointer;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    padding: 6px 4px;
                    color: #f2ede4;
                    font-size: 13px;
                    font-weight: 400;
                }

                .pulso-cuenta summary::-webkit-details-marker {
                    display: none;
                }

                .pulso-cuenta summary:focus-visible {
                    outline: 1px solid #c8b48c;
                    outline-offset: 4px;
                }

                .pulso-avatar {
                    width: 32px;
                    height: 32px;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    border: 1px solid rgba(200, 180, 140, .55);
                    border-radius: 50%;
                    color: #c8b48c;
                    font-family: "Cormorant Garamond", serif;
                    font-size: 15px;
                    letter-spacing: .04em;
                }

                .pulso-menu {
                    position: absolute;
                    right: 0;
                    top: calc(100% + 12px);
                    z-index: 1000;
                    width: 230px;
                    padding: 8px 0;
                    border: 1px solid rgba(242, 237, 228, .16);
                    background: rgba(21, 19, 17, .97);
                    backdrop-filter: blur(12px);
                    box-shadow: 0 24px 50px rgba(0, 0, 0, .5);
                }

                .pulso-menu a,
                .pulso-menu button,
                .pulso-menu span {
                    display: block;
                    box-sizing: border-box;
                    width: 100%;
                    padding: 11px 18px;
                    border: 0;
                    background: transparent;
                    color: #f2ede4;
                    font: inherit;
                    font-size: 13px;
                    text-align: left;
                    text-decoration: none;
                }

                .pulso-menu a:hover,
                .pulso-menu button:hover {
                    background: rgba(200, 180, 140, .1);
                    color: #c8b48c;
                    cursor: pointer;
                }

                .pulso-menu button {
                    margin-top: 6px;
                    border-top: 1px solid rgba(242, 237, 228, .12);
                    padding-top: 14px;
                }

                .pulso-menu .pulso-pendiente {
                    color: rgba(242, 237, 228, .38);
                    cursor: not-allowed;
                }

                @media (max-width: 650px) {
                    .pulso-cuenta summary > span:last-child {
                        display: none;
                    }
                }
            `;

            document.head.appendChild(estilos);
        }

        // =================================================
        // ELEMENTOS DEL MENÚ
        // =================================================

        function crearEnlace(texto, ruta) {
            const enlace = document.createElement('a');

            enlace.href = ruta;
            enlace.textContent = texto;

            return enlace;
        }

        const cuenta = document.createElement('details');
        cuenta.className = 'pulso-cuenta';

        const botonCuenta = document.createElement('summary');

        const avatar = document.createElement('span');
        avatar.className = 'pulso-avatar';
        avatar.textContent = iniciales;

        const textoNombre = document.createElement('span');
        textoNombre.textContent = nombre;

        botonCuenta.append(avatar, textoNombre);
        cuenta.appendChild(botonCuenta);

        const menu = document.createElement('div');
        menu.className = 'pulso-menu';

        // Perfil existente.
        menu.appendChild(
            crearEnlace('Mi perfil', '/perfil')
        );

        // Administradores y agentes operativos (organizador) pueden regresar a su panel.
        if (rol === 'admin' || rol === 'organizador') {
            menu.appendChild(
                crearEnlace(
                    'Panel administrativo',
                    '/admin'
                )
            );
        }

        // Historial de compras (reservas pagadas, con espacio para ver los boletos)
        const compras = crearEnlace('Mis compras', '/mis-compras');

        // Los boletos (con su QR) se abren desde cada compra
        menu.append(compras);

        // =================================================
        // CERRAR SESIÓN
        // =================================================

        const botonSalir = document.createElement('button');

        botonSalir.type = 'button';
        botonSalir.textContent = 'Cerrar sesión';

        botonSalir.addEventListener(
            'click',
            async () => {
                botonSalir.disabled = true;
                botonSalir.textContent =
                    'Cerrando sesión...';

                try {
                    await fetch('/api/auth/logout', {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            Authorization: `Bearer ${token}`
                        }
                    });

                } catch (error) {
                    console.warn(
                        'No se pudo contactar al servidor para cerrar sesión.',
                        error
                    );

                } finally {
                    localStorage.removeItem('auth_token');

                    window.location.replace('/catalogo');
                }
            }
        );

        menu.appendChild(botonSalir);
        cuenta.appendChild(menu);

        // Sustituir enlaces solo tras validar la sesión.
        enlaceLogin.remove();
        enlaceRegistro.replaceWith(cuenta);

        document.addEventListener('click', event => {
            if (!cuenta.contains(event.target)) {
                cuenta.open = false;
            }
        });
    }

    // =====================================================
    // FONDO DE LA PORTADA (BANNER DEL ADMIN)
    // =====================================================

    // Imagen o video corto elegido en el panel (Admin → Banner). Si no hay,
    // o no carga, queda la foto del teatro. El tono oscuro lo da catalogo.css.
    async function cargarBanner() {
        const fondo = document.getElementById('portadaFondo');

        if (!fondo) {
            return;
        }

        const respaldo = fondo.dataset.respaldo;
        let banner = null;

        try {
            const respuesta = await fetch('/api/catalogo/banner', {
                headers: { Accept: 'application/json' }
            });

            banner = respuesta.ok ? (await respuesta.json()).data : null;
        } catch (error) {
            console.warn('No se pudo consultar el banner:', error);
        }

        const mostrar = (medio) => {
            medio.className = 'portada-fondo__medio';
            fondo.replaceChildren(medio);
        };

        const ponerFoto = (url) => {
            const imagen = new Image();
            imagen.alt = '';
            imagen.decoding = 'async';
            imagen.addEventListener('load', () => imagen.classList.add('listo'), { once: true });
            imagen.addEventListener('error', () => {
                if (url !== respaldo) ponerFoto(respaldo);
            }, { once: true });
            imagen.src = url;
            mostrar(imagen);
        };

        if (banner?.tipo === 'video') {
            const video = document.createElement('video');
            // muted + playsinline: requisitos del navegador para reproducir solo
            video.muted = true;
            video.loop = true;
            video.autoplay = true;
            video.playsInline = true;
            video.setAttribute('playsinline', '');
            video.preload = 'auto';

            // Con "reducir movimiento" el video se queda quieto en su primer cuadro
            const quieto = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            if (quieto) {
                video.autoplay = false;
            }

            video.addEventListener('loadeddata', () => video.classList.add('listo'), { once: true });
            video.addEventListener('error', () => ponerFoto(respaldo), { once: true });
            video.src = banner.url;
            mostrar(video);
            return;
        }

        ponerFoto(banner?.url || respaldo);
    }

    // =====================================================
    // INICIALIZACIÓN
    // =====================================================

    // =====================================================
    // PORTADA QUE SE APAGA AL BAJAR
    // =====================================================

    // Al bajar, la portada se oscurece poco a poco (--oscuro: 0 → 1 a lo largo de toda su
    // altura) y, más abajo, los destacados se atenúan un poco al pasarlos (--atenuado).
    // Los valores siguen al scroll con suavizado, así no cambian de golpe con la rueda.
    function iniciarPortadaQueSeApaga() {
        const portada = document.querySelector('.portada');
        const destacados = document.getElementById('destacados');

        if (!portada || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }

        const limitar = (valor) => Math.min(1, Math.max(0, valor));
        const efectos = [
            {
                nodo: portada,
                variable: '--oscuro',
                meta: () => limitar(window.scrollY / (portada.offsetHeight * .9)),
                actual: 0
            },
            {
                nodo: destacados,
                variable: '--atenuado',
                // empieza cuando la sección ya subió un tercio y termina cuando casi sale
                meta: () => {
                    if (!destacados || destacados.hidden) return 0;
                    const caja = destacados.getBoundingClientRect();
                    return limitar((-caja.top - caja.height * .33) / (caja.height * .5));
                },
                actual: 0
            }
        ].filter(efecto => efecto.nodo);

        let animando = false;

        function paso() {
            let quieto = true;

            efectos.forEach(efecto => {
                const meta = efecto.meta();
                efecto.actual += (meta - efecto.actual) * .08;   // más pequeño = más lento y suave

                if (Math.abs(meta - efecto.actual) < .001) {
                    efecto.actual = meta;
                } else {
                    quieto = false;
                }

                efecto.nodo.style.setProperty(efecto.variable, efecto.actual.toFixed(4));
            });

            animando = !quieto;

            if (animando) {
                requestAnimationFrame(paso);
            }
        }

        function alBajar() {
            if (!animando) {
                animando = true;
                requestAnimationFrame(paso);
            }
        }

        window.addEventListener('scroll', alBajar, { passive: true });
        window.addEventListener('resize', alBajar, { passive: true });
        alBajar();   // por si la página abre ya bajada (recargar a media página)
    }

    // Sin sesión: "Iniciar sesión" y "Registrarse" recuerdan esta página para volver a ella
    if (!localStorage.getItem('auth_token')) {
        const aqui = encodeURIComponent(location.pathname + location.search + location.hash);
        document.querySelectorAll('.nav-enlaces a[href="/login"], .nav-enlaces a[href="/registro"]').forEach(enlace => {
            enlace.href = `${enlace.getAttribute('href')}?volver=${aqui}`;
        });
    }

    cargarBanner();
    iniciarPortadaQueSeApaga();
    cargarEventos({}, true);
    iniciarMenuSesion();

});
