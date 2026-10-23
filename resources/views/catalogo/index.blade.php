<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    @include('partials.sesion')
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="dark">
    <meta name="theme-color" content="#0d0c0b">
    <meta name="description" content="{{ config('marca.nombre') }}: teatro, conciertos y noches para recordar. Elige tu evento y tu butaca.">

    <title>Cartelera · {{ config('marca.nombre') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;1,400&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('css/catalogo.css') }}?v={{ filemtime(public_path('css/catalogo.css')) }}">

    <script src="{{ asset('js/catalogo.js') }}?v={{ filemtime(public_path('js/catalogo.js')) }}" defer></script>
    @include('partials.avisos')
    @include('partials.iconos')
</head>

<body class="pagina-catalogo">

    {{-- Cabecera: catalogo.js cambia "Iniciar sesión / Registrarse" por el menú de la cuenta --}}
    <header class="navbar">
        <div class="contenedor nav-contenido">
            <a class="logo" href="{{ route('catalogo.index') }}" aria-label="{{ config('marca.nombre') }}, ir a la cartelera">
                {{ config('marca.nombre') }}
            </a>

            <nav class="nav-enlaces" aria-label="Navegación principal">
                <a href="#eventos" class="activo">Cartelera</a>
                <a href="/login">Iniciar sesión</a>
                <a href="/registro" class="btn-registro">Registrarse</a>
            </nav>
        </div>
    </header>

    <main>

        {{-- Portada a pantalla completa: el banner se luce solo, sin tarjetas encima --}}
        <section class="portada" aria-labelledby="tituloPortada">

            {{-- Fondo: el banner que elige el admin (imagen o video); si no hay, la foto del teatro.
                 catalogo.js lo llena; el tono oscuro lo pone catalogo.css --}}
            <div class="portada-fondo" id="portadaFondo" data-respaldo="{{ asset('img/landing/auditorio-general.jpg') }}" aria-hidden="true"></div>

            <div class="contenedor destacados-encabezado">
                <span class="eyebrow">Temporada {{ now()->year }}</span>

                <h1 id="tituloPortada" class="titulo-principal">
                    Una noche que se <em>recuerda.</em>
                </h1>

                <p>
                    Teatro, conciertos y funciones en vivo.
                    Elige tu evento y luego tu butaca, frente al escenario.
                </p>
            </div>

            <a class="portada__bajar" href="#destacados">
                <span>Descubre la cartelera</span>
                <span class="portada__linea" aria-hidden="true"></span>
            </a>
        </section>

        {{-- Destacados: carrusel con los próximos eventos a la venta (solo eventos reales; catalogo.js) --}}
        <section id="destacados" class="seccion-destacados" aria-labelledby="tituloDestacados" hidden>
            <div class="contenedor portada-contenido">
                <div class="destacados-titulo">
                    <span class="label-seccion">Destacados</span>
                    <h2 id="tituloDestacados">Lo que viene <em>pronto</em></h2>
                </div>

                <div class="coverflow"
                     id="coverflow"
                     tabindex="0"
                     role="region"
                     aria-roledescription="carrusel"
                     aria-label="Carrusel de eventos destacados">

                    <div id="listaDestacados" class="escenario-destacados"></div>
                </div>

                <div class="carrusel-barra" id="barraCarrusel">
                    <button id="btnAnterior" class="control-carrusel" type="button" aria-label="Ver tarjeta anterior">
                        <span aria-hidden="true">←</span>
                    </button>

                    <div id="puntosCarrusel" class="carrusel-puntos" aria-label="Seleccionar tarjeta del carrusel"></div>

                    <button id="btnSiguiente" class="control-carrusel" type="button" aria-label="Ver tarjeta siguiente">
                        <span aria-hidden="true">→</span>
                    </button>
                </div>

                <p id="estadoCarrusel" class="sr-only" role="status" aria-live="polite"></p>

            </div>
        </section>

        {{-- Cartelera completa --}}
        <section id="eventos" class="seccion-eventos" aria-labelledby="tituloEventos">
            <div class="contenedor">
                <div class="eventos-encabezado reveal">
                    <div>
                        <span class="label-seccion">En cartelera</span>
                        <h2 id="tituloEventos">Próximas <em>funciones</em></h2>
                    </div>

                    <span id="contadorEventos" class="contador-eventos" role="status">Consultando…</span>
                </div>

                <div id="listaEventos" class="grid-eventos" aria-live="polite">
                    <p class="mensaje-carga">Cargando la cartelera…</p>
                </div>

                {{-- Siguiente tanda de la cartelera (catalogo.js; GET /api/eventos?pagina=N) --}}
                <div class="cartelera-mas">
                    <button type="button" id="verMasEventos" class="ver-mas" hidden>Ver más funciones</button>
                </div>

                <p id="mensajeEstado" class="mensaje-estado" role="status" aria-live="polite"></p>
            </div>
        </section>
    </main>


    @include('partials.pie-pagina')

</body>
</html>
