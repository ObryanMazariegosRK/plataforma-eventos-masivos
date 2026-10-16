<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#102536">
    <meta name="description" content="Consulta los detalles y localidades de un evento en PULSO.">
    <title>Detalle del evento | PULSO</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/catalogo.css') }}">
    <link rel="stylesheet" href="{{ asset('css/detalle-evento.css') }}">
    <script src="{{ asset('js/detalle-evento.js') }}" defer></script>
</head>
<body>
    <header class="navbar">
        <div class="contenedor nav-contenido">
            <a class="logo" href="{{ route('catalogo.index') }}" aria-label="PULSO, ir al catálogo">
                <span class="logo-icono" aria-hidden="true">✦</span>
                <span>PULSO<strong>.</strong></span>
            </a>
            <nav class="nav-enlaces" aria-label="Navegación principal">
                <a href="{{ route('catalogo.index') }}">Eventos</a>
                <a href="/login">Iniciar sesión</a>
                <a href="/registro" class="btn-registro">Registrarse <span aria-hidden="true">↗</span></a>
            </nav>
        </div>
    </header>

    <main id="detallePagina" data-evento-id="{{ request()->route('id') }}">
        <section class="detalle-portada">
            <div class="contenedor">
                <a href="{{ route('catalogo.index') }}" class="detalle-volver">← Volver a todos los eventos</a>
                <div id="detalleAviso" class="detalle-aviso" role="status" aria-live="polite">Consultando información del evento...</div>
                <div id="detalleContenido" hidden>
                    <div class="detalle-principal">
                        <div class="detalle-foto-contenedor">
                            <img id="detalleImagen"
                                 class="detalle-foto"
                                 src="{{ asset('img/eventos/demo-04.jpg') }}"
                                 alt="Imagen ilustrativa de un concierto">
                            <span id="detalleFotoNota" class="detalle-foto-nota">FOTO ILUSTRATIVA</span>
                        </div>
                        <div class="detalle-resumen">
                            <div class="detalle-eyebrow"><span class="detalle-punto"></span> EXPERIENCIAS EN VIVO</div>
                            <span id="detalleEstado" class="detalle-estado">Evento publicado</span>
                            <h1 id="detalleNombre">Evento</h1>
                            <p class="detalle-bajada">La experiencia comienza mucho antes de que suene la primera canción.</p>
                            <div class="detalle-informacion">
                                <div class="detalle-dato">
                                    <span class="detalle-dato-icono" aria-hidden="true">◷</span>
                                    <div><span class="detalle-dato-label">FECHA Y HORA</span><strong id="detalleFecha">Por confirmar</strong></div>
                                </div>
                                <div class="detalle-dato">
                                    <span class="detalle-dato-icono" aria-hidden="true">⌖</span>
                                    <div><span class="detalle-dato-label">LUGAR DEL EVENTO</span><strong id="detalleLugar">Por confirmar</strong><span id="detalleDireccion" class="detalle-direccion"></span></div>
                                </div>
                            </div>
                            <a class="detalle-ir-zonas" href="#zonas">Consultar zonas y precios <span aria-hidden="true">↓</span></a>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section id="detalleInformacionExtra" class="detalle-cuerpo" hidden>
            <div class="contenedor detalle-columnas">
                <section class="detalle-panel" aria-labelledby="tituloDescripcion">
                    <span class="detalle-sobretitulo">CONOCE LA EXPERIENCIA</span>
                    <h2 id="tituloDescripcion">Sobre este evento</h2>
                    <p id="detalleDescripcion">Descripción por confirmar.</p>
                    <div class="detalle-recordatorio">
                        <strong>Una experiencia para recordar.</strong>
                        <span>Verifica la fecha, la localidad y las condiciones antes de reservar.</span>
                    </div>
                </section>

                <section id="zonas" class="detalle-panel detalle-panel-zonas" aria-labelledby="tituloZonas">
                    <span class="detalle-sobretitulo">LOCALIDADES Y PRECIOS</span>
                    <h2 id="tituloZonas">Elige tu lugar.</h2>
                    <p class="detalle-zonas-explicacion">Estas tarifas son las configuradas para el evento. La disponibilidad real se confirmará al integrar Reservas.</p>
                    <div id="detalleLocalidades" class="detalle-localidades" aria-live="polite"></div>
                    <div class="detalle-reserva-pendiente">
                        <button type="button" disabled aria-describedby="detalleReservaExplicacion">Reservar entradas — Próximamente</button>
                        <p id="detalleReservaExplicacion">La selección de entradas se habilitará cuando esté conectado el módulo de Reservas.</p>
                    </div>
                </section>
            </div>
        </section>
    </main>

    <footer class="footer">
        <div class="contenedor footer-contenido">
            <div>
                <h3><span aria-hidden="true">✦</span> PULSO<span class="marca-punto">.</span></h3>
                <p>Los mejores momentos no se cuentan. Se viven.</p>
            </div>
            <div class="footer-derecha">
                <p>Plataforma de Eventos Masivos</p>
                <p>Universidad Mariano Gálvez</p>
            </div>
        </div>
        <div class="footer-copy">© 2026 PULSO · Proyecto académico de Análisis de Sistemas II</div>
    </footer>
</body>
</html>
