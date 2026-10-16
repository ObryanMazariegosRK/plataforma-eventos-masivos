
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <meta name="theme-color" content="#102333">

    <meta name="description"
          content="PULSO. Descubre conciertos, festivales y experiencias inolvidables. No solo vayas, vívelo.">

    <title>Explora eventos | PULSO.</title>

    <!-- FUENTES -->
    <link rel="preconnect"
          href="https://fonts.googleapis.com">

    <link rel="preconnect"
          href="https://fonts.gstatic.com"
          crossorigin>

    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap"
        rel="stylesheet">

    <!-- CSS -->
    <link rel="stylesheet"
          href="{{ asset('css/catalogo.css') }}">

    <!-- JAVASCRIPT -->
    <script src="{{ asset('js/catalogo.js') }}"
            defer></script>
</head>

<body>

    <!-- =====================================
         NAVBAR - PULSO
    ===================================== -->

    <header class="navbar">

        <div class="contenedor nav-contenido">

            <a class="logo"
               href="/catalogo"
               aria-label="PULSO, ir al inicio">

                <span class="logo-icono"
                      aria-hidden="true">✦</span>

                <span>
                    PULSO<strong>.</strong>
                </span>

            </a>

            <nav class="nav-enlaces"
                 aria-label="Navegación principal">

                <a href="#eventos" class="activo">
                    Eventos
                </a>

                <a href="/login">
                    Iniciar sesión
                </a>

                <a href="/registro"
                   class="btn-registro">

                    Registrarse
                    <span aria-hidden="true">↗</span>

                </a>

            </nav>

        </div>

    </header>


    <main>

        <!-- =====================================
             PORTADA / CARRUSEL 3D
        ===================================== -->

        <section id="destacados"
                 class="seccion-destacados"
                 aria-labelledby="tituloPortada">

            <div class="hero-orbita hero-orbita-a"
                 aria-hidden="true"></div>

            <div class="hero-orbita hero-orbita-b"
                 aria-hidden="true"></div>

            <div class="contenedor portada-contenido">

                <div class="destacados-encabezado">

                    <span class="eyebrow">

                        <span class="eyebrow-punto"
                              aria-hidden="true"></span>

                        TU PRÓXIMA EXPERIENCIA COMIENZA AQUÍ

                    </span>

                    <h1 id="tituloPortada"
                        class="titulo-principal">

                        No solo vayas.

                        <span class="palabra-animada">
                            Vívelo.
                        </span>

                    </h1>

                    <p>
                        La música, la emoción y los mejores
                        momentos están más cerca de lo que
                        imaginas. Descubre tu próximo evento.
                    </p>

                </div>


                <!-- =====================================
                     CARRUSEL DE EVENTOS
                     CARGADO DESDE LA API
                ===================================== -->

                <div class="coverflow"
                     id="coverflow"
                     tabindex="0"
                     role="region"
                     aria-roledescription="carrusel"
                     aria-label="Carrusel de eventos destacados">

                    <div id="listaDestacados"
                         class="escenario-destacados">

                        <p class="mensaje-carga">
                            Preparando experiencias...
                        </p>

                    </div>

                </div>


                <!-- CONTROLES DEL CARRUSEL -->

                <div class="carrusel-barra">

                    <button id="btnAnterior"
                            class="control-carrusel"
                            type="button"
                            aria-label="Ver tarjeta anterior">

                        ←

                    </button>

                    <div id="puntosCarrusel"
                         class="carrusel-puntos"
                         aria-label="Seleccionar tarjeta del carrusel">
                    </div>

                    <button id="btnSiguiente"
                            class="control-carrusel"
                            type="button"
                            aria-label="Ver tarjeta siguiente">

                        →

                    </button>

                </div>

                <p id="estadoCarrusel"
                   class="sr-only"
                   role="status"
                   aria-live="polite"></p>


                <!-- BOTONES PRINCIPALES -->

                <div class="destacados-acciones">

                    <a href="#eventos"
                       class="btn-explorar">

                        Explorar eventos

                        <span aria-hidden="true">↗</span>

                    </a>

                    <a href="#busqueda"
                       class="enlace-secundario">

                        Buscar por ciudad

                        <span aria-hidden="true">↓</span>

                    </a>

                </div>


                <p class="nota-demo">
                    Las tarjetas marcadas como
                    «Vista de muestra» ilustran el diseño,
                    no son eventos a la venta.
                </p>

            </div>

        </section>


        <!-- =====================================
             FRANJA DE EXPERIENCIAS
        ===================================== -->

        <div class="franja-experiencias"
             aria-hidden="true">

            <div class="franja-track">

                <span>CONCIERTOS</span>
                <span>✳</span>

                <span>FESTIVALES</span>
                <span>✳</span>

                <span>EXPERIENCIAS</span>
                <span>✳</span>

                <span>ESPECTÁCULOS</span>
                <span>✳</span>

                <span>CONCIERTOS</span>
                <span>✳</span>

                <span>FESTIVALES</span>
                <span>✳</span>

                <span>EXPERIENCIAS</span>
                <span>✳</span>

                <span>ESPECTÁCULOS</span>
                <span>✳</span>

            </div>

        </div>


        <!-- =====================================
             BUSCADOR DE EVENTOS
        ===================================== -->

        <section id="busqueda"
                 class="seccion-busqueda reveal"
                 aria-labelledby="tituloBusqueda">

            <div class="contenedor">

                <div class="buscador-panel">

                    <div class="buscador-titulo">

                        <span class="label-seccion">
                            ENCUENTRA TU PLAN
                        </span>

                        <h2 id="tituloBusqueda">
                            ¿Qué hacemos hoy?
                        </h2>

                        <p>
                            Busca por nombre, ciudad o fecha.
                            Nosotros encontramos el evento.
                        </p>

                    </div>


                    <form id="formBuscar"
                          class="form-busqueda">

                        <!-- NOMBRE -->

                        <div class="campo">

                            <label for="nombre">
                                Nombre del evento
                            </label>

                            <input
                                type="text"
                                id="nombre"
                                name="nombre"
                                placeholder="Concierto, festival..."
                                autocomplete="off">

                        </div>


                        <!-- CIUDAD -->

                        <div class="campo">

                            <label for="ciudad">
                                Ciudad
                            </label>

                            <input
                                type="text"
                                id="ciudad"
                                name="ciudad"
                                placeholder="Ej. Guatemala"
                                autocomplete="address-level2">

                        </div>


                        <!-- FECHA -->

                        <div class="campo">

                            <label for="fecha">
                                Fecha
                            </label>

                            <input
                                type="date"
                                id="fecha"
                                name="fecha">

                        </div>


                        <!-- BOTÓN BUSCAR -->

                        <button type="submit"
                                class="btn-buscar">

                            Buscar eventos

                            <span aria-hidden="true">↗</span>

                        </button>

                    </form>

                </div>

            </div>

        </section>


        <!-- =====================================
             PRÓXIMOS EVENTOS
        ===================================== -->

        <section id="eventos"
                 class="seccion-eventos"
                 aria-labelledby="tituloEventos">

            <div class="contenedor">

                <div class="eventos-encabezado reveal">

                    <div>

                        <span class="label-seccion">
                            TODO LO QUE VIENE
                        </span>

                        <h2 id="tituloEventos">
                            Próximos <span>eventos.</span>
                        </h2>

                        <p>
                            Explora las experiencias publicadas
                            y encuentra tu próxima historia.
                        </p>

                    </div>


                    <span id="contadorEventos"
                          class="contador-eventos"
                          role="status">

                        Consultando eventos...

                    </span>

                </div>


                <!-- LISTADO DESDE MYSQL -->

                <div id="listaEventos"
                     class="grid-eventos"
                     aria-live="polite">

                    <p class="mensaje-carga">
                        Cargando eventos disponibles...
                    </p>

                </div>


                <!-- MENSAJES -->

                <p id="mensajeEstado"
                   class="mensaje-estado"
                   role="status"
                   aria-live="polite"></p>

            </div>

        </section>

    </main>


    <!-- =====================================
         FOOTER - PULSO
    ===================================== -->

    <footer class="footer">

        <div class="contenedor footer-contenido">

            <div>

                <h3>
                    <span aria-hidden="true">✦</span>
                    PULSO.
                </h3>

                <p>
                    Los mejores momentos no se cuentan.
                    Se viven.
                </p>

            </div>


            <div class="footer-derecha">

                <p>
                    Plataforma de Eventos Masivos
                </p>

                <p>
                    Universidad Mariano Gálvez
                </p>

            </div>

        </div>


        <div class="footer-copy">

            © 2026 PULSO. ·
            Proyecto de Análisis de Sistemas II

        </div>

    </footer>

</body>
</html>
