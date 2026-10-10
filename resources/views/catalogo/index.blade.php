
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Catálogo de Eventos | Eventos Masivos</title>

    <link rel="stylesheet"
          href="{{ asset('css/catalogo.css') }}">

    <script src="{{ asset('js/catalogo.js') }}" defer></script>
</head>

<body>

    <!-- BARRA DE NAVEGACIÓN -->
    <header class="navbar">
        <div class="contenedor nav-contenido">

            <a href="/catalogo" class="logo">
                <span class="logo-icono">✦</span>
                EVENTOS MASIVOS
            </a>

            <nav class="nav-enlaces">
                <a href="/catalogo" class="activo">
                    Eventos
                </a>

                <a href="/login">
                    Iniciar sesión
                </a>

                <a href="/registro" class="btn-registro">
                    Registrarse
                </a>
            </nav>

        </div>
    </header>


    <!-- SECCIÓN PRINCIPAL -->
    <section class="hero">
        <div class="contenedor hero-contenido">

            <span class="hero-etiqueta">
                ✨ Vive experiencias inolvidables
            </span>

            <h1>
                Descubre tu próximo
                <span>gran evento</span>
            </h1>

            <p>
                Encuentra conciertos, festivales y
                espectáculos increíbles.
                ¡Tu próxima experiencia comienza aquí!
            </p>

            <a href="#eventos" class="btn-hero">
                Explorar eventos →
            </a>

        </div>
    </section>


    <!-- BUSCADOR DE EVENTOS -->
    <section class="seccion-busqueda">
        <div class="contenedor">

            <div class="buscador-panel">

                <h2>Encuentra tu evento ideal</h2>

                <p>
                    Busca por nombre, ciudad o fecha.
                </p>

                <form id="formBuscar" class="form-busqueda">

                    <div class="campo">
                        <label for="nombre">
                            Nombre del evento
                        </label>

                        <input
                            type="text"
                            id="nombre"
                            name="nombre"
                            placeholder="Ej. Concierto">
                    </div>

                    <div class="campo">
                        <label for="ciudad">
                            Ciudad
                        </label>

                        <input
                            type="text"
                            id="ciudad"
                            name="ciudad"
                            placeholder="Ej. Guatemala">
                    </div>

                    <div class="campo">
                        <label for="fecha">
                            Fecha
                        </label>

                        <input
                            type="date"
                            id="fecha"
                            name="fecha">
                    </div>

                    <button
                        type="submit"
                        class="btn-buscar">
                        🔍 Buscar eventos
                    </button>

                </form>

            </div>

        </div>
    </section>


    <!-- LISTADO DE EVENTOS -->
    <main id="eventos" class="seccion-eventos">

        <div class="contenedor">

            <div class="eventos-encabezado">

                <div>
                    <span class="subtitulo">
                        NUESTRAS EXPERIENCIAS
                    </span>

                    <h2>Próximos eventos</h2>

                    <p>
                        Descubre los eventos disponibles
                        y encuentra tu próxima aventura.
                    </p>
                </div>

                <span id="contadorEventos"
                      class="contador-eventos">
                    Consultando eventos...
                </span>

            </div>


            <!-- AQUÍ JAVASCRIPT MOSTRARÁ LOS EVENTOS -->
            <div id="listaEventos"
                 class="grid-eventos"
                 aria-live="polite">

                <p class="mensaje-carga">
                    Cargando eventos disponibles...
                </p>

            </div>

            <p id="mensajeEstado"
               class="mensaje-estado"
               role="status"
               aria-live="polite"></p>

        </div>

    </main>


    <!-- PIE DE PÁGINA -->
    <footer class="footer">

        <div class="contenedor footer-contenido">

            <div>
                <h3>✦ EVENTOS MASIVOS</h3>

                <p>
                    Vive momentos únicos con nosotros.
                </p>
            </div>

            <div>
                <p>
                    Plataforma de Eventos Masivos
                </p>

                <p>
                    Universidad Mariano Gálvez
                </p>
            </div>

        </div>

        <div class="footer-copy">
            © 2026 Eventos Masivos.
            Proyecto de Análisis de Sistemas II.
        </div>

    </footer>

</body>
</html>
