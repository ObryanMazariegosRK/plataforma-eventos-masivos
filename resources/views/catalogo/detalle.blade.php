<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="dark">
    <meta name="theme-color" content="#0d0c0b">
    <meta name="description" content="Fecha, lugar, localidades y precios del evento en {{ config('marca.nombre') }}.">
    <title>Evento · {{ config('marca.nombre') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;1,400&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/catalogo.css') }}?v={{ filemtime(public_path('css/catalogo.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/detalle-evento.css') }}?v={{ filemtime(public_path('css/detalle-evento.css')) }}">
    <script src="{{ asset('js/detalle-evento.js') }}?v={{ filemtime(public_path('js/detalle-evento.js')) }}" defer></script>
    @include('partials.iconos')
</head>
<body data-marca="{{ config('marca.nombre') }}">
    @php($eventoId = request()->route('id'))

    <header class="navbar">
        <div class="contenedor nav-contenido">
            <a class="logo" href="{{ route('catalogo.index') }}" aria-label="{{ config('marca.nombre') }}, ir a la cartelera">{{ config('marca.nombre') }}</a>
            <nav class="nav-enlaces" aria-label="Navegación principal">
                <a href="{{ route('catalogo.index') }}">Cartelera</a>
                <a href="/login">Iniciar sesión</a>
                <a href="/registro" class="btn-registro">Registrarse</a>
            </nav>
        </div>
    </header>

    <main id="detallePagina" data-evento-id="{{ $eventoId }}">
        <section class="detalle-portada">
            <div class="contenedor">
                <a href="{{ route('catalogo.index') }}" class="detalle-volver">← Cartelera</a>
                <div id="detalleAviso" class="detalle-aviso" role="status" aria-live="polite">Consultando el evento…</div>
                <div id="detalleContenido" hidden>
                    <div class="detalle-principal">
                        <div class="detalle-foto-contenedor">
                            <img id="detalleImagen"
                                 class="detalle-foto"
                                 src="{{ asset('img/landing/auditorio-general.jpg') }}"
                                 alt="Imagen ilustrativa de un teatro">
                            <span id="detalleFotoNota" class="detalle-foto-nota">Foto ilustrativa</span>
                        </div>
                        <div class="detalle-resumen">
                            <div class="detalle-eyebrow">En cartelera</div>
                            <span id="detalleEstado" class="detalle-estado">En venta</span>
                            <h1 id="detalleNombre">Evento</h1>
                            <p class="detalle-bajada">La función empieza mucho antes de que se abra el telón.</p>
                            <div class="detalle-informacion">
                                <div class="detalle-dato">
                                    <div><span class="detalle-dato-label">Fecha y hora</span><strong id="detalleFecha">Por confirmar</strong></div>
                                </div>
                                <div class="detalle-dato">
                                    <div><span class="detalle-dato-label">Lugar</span><strong id="detalleLugar">Por confirmar</strong><span id="detalleDireccion" class="detalle-direccion"></span></div>
                                </div>
                            </div>
                            <div class="detalle-acciones">
                                {{-- Plano de asientos (módulo Inventario): elegir lugares y reservar --}}
                                <a class="detalle-ir-zonas" href="{{ route('eventos.experiencia', ['eventoId' => $eventoId]) }}#asientos">Elegir asientos</a>
                                <a class="detalle-ir-zonas detalle-ir-zonas--linea" href="#zonas">Zonas y precios</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="detalleInformacionExtra" class="detalle-cuerpo" hidden>
            <div class="contenedor detalle-columnas">
                <section class="detalle-panel" aria-labelledby="tituloDescripcion">
                    <span class="detalle-sobretitulo">La función</span>
                    <h2 id="tituloDescripcion">Sobre este <em>evento</em></h2>
                    <p id="detalleDescripcion">Descripción por confirmar.</p>
                    <div class="detalle-recordatorio">
                        <strong>Una noche para recordar.</strong>
                        <span>Verifica la fecha y la localidad antes de reservar. Tus butacas quedan apartadas mientras completas el pago.</span>
                    </div>
                </section>

                <section id="zonas" class="detalle-panel detalle-panel-zonas" aria-labelledby="tituloZonas">
                    <span class="detalle-sobretitulo">Localidades y precios</span>
                    <h2 id="tituloZonas">Elige tu <em>lugar</em></h2>
                    <div id="detalleLocalidades" class="detalle-localidades" aria-live="polite"></div>
                    <div class="detalle-reserva">
                        <a class="detalle-ir-zonas" href="{{ route('eventos.experiencia', ['eventoId' => $eventoId]) }}#asientos">Ver el plano y reservar</a>
                    </div>
                </section>
            </div>
        </section>
    </main>

    @include('partials.pie-pagina')

</body>
</html>
