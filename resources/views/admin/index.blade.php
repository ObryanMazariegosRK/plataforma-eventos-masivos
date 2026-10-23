<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <meta name="robots" content="noindex,nofollow">
    <meta name="theme-color" content="#100E0D">

    <title>Soirée | Administración</title>

    <!-- Tipografías -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Outfit:wght@400;500;600;700;800;900&family=Cormorant+Garamond:ital,wght@0,400;0,500;1,400&family=Inter:wght@300;400;500;600&display=swap"
          rel="stylesheet">

    <!-- Administración: una sola hoja de estilos consolidada -->
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v={{ filemtime(public_path('css/admin.css')) }}">
    <!-- Tema Soiree claro/oscuro (estilo de la tienda): solo apariencia, va encima de admin.css -->
    <!-- Plano de asientos (formulario de evento → "Ver plano del recinto") -->
    <link rel="stylesheet" href="{{ asset('css/asientos.css') }}?v={{ filemtime(public_path('css/asientos.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/admin-tema.css') }}?v={{ filemtime(public_path('css/admin-tema.css')) }}">
    <!-- Aplica el tema guardado antes de dibujar la página (sin destello al recargar) -->
    <script>
        try { document.documentElement.dataset.tema = localStorage.getItem('tema_panel') === 'oscuro' ? 'oscuro' : 'claro'; }
        catch { document.documentElement.dataset.tema = 'claro'; }
    </script>
    <script defer src="{{ asset('js/admin-tema.js') }}?v={{ filemtime(public_path('js/admin-tema.js')) }}"></script>

    <!-- JavaScript: conservar los módulos funcionales existentes -->
    <script defer src="{{ asset('js/admin.js') }}?v={{ filemtime(public_path('js/admin.js')) }}"></script>
    <script defer src="{{ asset('js/admin-catalogo-eventos.js') }}?v={{ filemtime(public_path('js/admin-catalogo-eventos.js')) }}"></script>
    <script defer src="{{ asset('js/admin-bitacora.js') }}?v={{ filemtime(public_path('js/admin-bitacora.js')) }}"></script>
    <script defer src="{{ asset('js/admin-reembolsos.js') }}?v={{ filemtime(public_path('js/admin-reembolsos.js')) }}"></script>
    <script defer src="{{ asset('js/asientos.js') }}?v={{ filemtime(public_path('js/asientos.js')) }}"></script>
    <script defer src="{{ asset('js/admin-evento-formulario.js') }}?v={{ filemtime(public_path('js/admin-evento-formulario.js')) }}"></script>
    <script defer src="{{ asset('js/admin-costos.js') }}?v={{ filemtime(public_path('js/admin-costos.js')) }}"></script>
    <script defer src="{{ asset('js/admin-finanzas.js') }}?v={{ filemtime(public_path('js/admin-finanzas.js')) }}"></script>
    <script defer src="{{ asset('js/admin-event-filters.js') }}?v=1"></script>
    <script defer src="{{ asset('js/admin-banner.js') }}?v={{ filemtime(public_path('js/admin-banner.js')) }}"></script>


</head>

<body class="tema-soiree">

<!-- =================================================
     ICONOS SVG
================================================= -->

<svg xmlns="http://www.w3.org/2000/svg"
     style="position:absolute;width:0;height:0;overflow:hidden"
     aria-hidden="true">

    <symbol id="i-home" viewBox="0 0 24 24">
        <path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z"/>
    </symbol>

    <symbol id="i-users" viewBox="0 0 24 24">
        <circle cx="9" cy="8" r="4"/>
        <path d="M2 21v-2a6 6 0 0 1 6-6h2a6 6 0 0 1 6 6v2zM16 4a4 4 0 0 1 0 8M18 14a5 5 0 0 1 4 5v2"/>
    </symbol>

    <symbol id="i-image" viewBox="0 0 24 24">
        <rect x="3" y="4" width="18" height="16" rx="2"/>
        <circle cx="9" cy="10" r="2"/>
        <path d="m21 16-5-5L5 20"/>
    </symbol>

    <symbol id="i-calendar" viewBox="0 0 24 24">
        <rect x="3" y="5" width="18" height="16" rx="2"/>
        <path d="M7 3v4M17 3v4M3 10h18"/>
    </symbol>

    <symbol id="i-ticket" viewBox="0 0 24 24">
        <path d="M4 5h16a1 1 0 0 1 1 1v4a2 2 0 0 0 0 4v4a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1v-4a2 2 0 0 0 0-4V6a1 1 0 0 1 1-1z"/>
        <path d="M13 5v14" stroke-dasharray="2 3"/>
    </symbol>

    <symbol id="i-card" viewBox="0 0 24 24">
        <rect x="2" y="5" width="20" height="14" rx="3"/>
        <path d="M2 10h20M6 15h5"/>
    </symbol>

    <symbol id="i-chart" viewBox="0 0 24 24">
        <path d="M4 20V12M10 20V7M16 20V10M22 20V3M2 21h21"/>
    </symbol>

    <symbol id="i-bell" viewBox="0 0 24 24">
        <path d="M18 8a6 6 0 0 0-12 0c0 7-3 8-3 10h18c0-2-3-3-3-10zM10 22h4"/>
    </symbol>

    <symbol id="i-refund" viewBox="0 0 24 24">
        <path d="M9 14 4 9l5-5"/>
        <path d="M4 9h10.5a5.5 5.5 0 0 1 0 11H11"/>
    </symbol>

    <symbol id="i-clock" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="9"/>
        <path d="M12 7v5l4 2"/>
    </symbol>

    <symbol id="i-settings" viewBox="0 0 24 24">
        <circle cx="12" cy="12" r="3"/>
        <path d="m10 2-.5 2-2 1-2-.5L3 8l1.5 1.5v3L3 14l2.5 3.5 2-.5 2 1 .5 2h4l.5-2 2-1 2 .5L21 14l-1.5-1.5v-3L21 8l-2.5-3.5-2 .5-2-1L14 2z"/>
    </symbol>

    <symbol id="i-search" viewBox="0 0 24 24">
        <circle cx="10.5" cy="10.5" r="6.5"/>
        <path d="m16 16 5 5"/>
    </symbol>

    <symbol id="i-download" viewBox="0 0 24 24">
        <path d="M12 3v13m-5-5 5 5 5-5M4 18v3h16v-3"/>
    </symbol>

    <symbol id="i-zap" viewBox="0 0 24 24">
        <path d="m13 2-9 12h7l-1 8 10-13h-7z"/>
    </symbol>

    <symbol id="i-shield" viewBox="0 0 24 24">
        <path d="M12 2 4 5v6c0 6 4 9 8 11 4-2 8-5 8-11V5z"/>
        <path d="m8 12 3 3 5-6"/>
    </symbol>

</svg>

<!-- =================================================
     ESTRUCTURA PRINCIPAL
================================================= -->

<div class="shell">

    {{-- Celular y tableta: barra con el botón que despliega el menú lateral (admin-tema.js) --}}
    <header class="barra-movil">
        <button type="button" class="barra-movil__menu" id="btnMenu"
                aria-controls="menu-lateral" aria-expanded="false" aria-label="Abrir menú">
            <span class="barra-movil__icono" aria-hidden="true"><span></span></span>
            <span>Menú</span>
        </button>
        <a class="barra-movil__marca" href="/admin">soirée<span>.</span></a>
        <span class="barra-movil__seccion" id="barraSeccion" aria-live="polite"></span>
    </header>
    <div class="menu-velo" id="menuVelo" hidden></div>

    <!-- BARRA LATERAL -->
    <aside class="sidebar"
           id="menu-lateral"
           aria-label="Navegación administrativa">

        <button type="button" class="menu-cerrar" id="btnCerrarMenu" aria-label="Cerrar menú">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6 6 18"/></svg>
        </button>

        <a class="brand"
           href="/admin"
           aria-label="Soirée Administración">

            <span class="brand-spark">✳</span>

            <span>
                soirée<span class="brand-dot">.</span>
            </span>
        </a>

        <div class="brand-subtitle">
            ADMINISTRACIÓN
        </div>

        <div class="sidebar-caption">
            MENÚ PRINCIPAL
        </div>

        <nav id="nav" aria-label="Secciones del panel">

            <button type="button"
                    class="nav-item selected"
                    data-section="resumen">

                <svg class="nav-icon">
                    <use href="#i-home"/>
                </svg>

                <span>Dashboard</span>
            </button>

            <button type="button"
                    class="nav-item"
                    data-section="eventos">

                <svg class="nav-icon">
                    <use href="#i-calendar"/>
                </svg>

                <span>Eventos</span>
            </button>

            <button type="button"
                    class="nav-item"
                    data-section="catalogo">

                <svg class="nav-icon">
                    <use href="#i-search"/>
                </svg>

                <span>Catálogo de eventos</span>
            </button>

            <button type="button"
                    class="nav-item"
                    data-section="reservas">

                <svg class="nav-icon">
                    <use href="#i-ticket"/>
                </svg>

                <span>Reservas</span>
            </button>

            <button type="button"
                    class="nav-item"
                    data-section="pagos">

                <svg class="nav-icon">
                    <use href="#i-card"/>
                </svg>

                <span>Pagos</span>
            </button>

            <button type="button"
                    class="nav-item"
                    data-section="reembolsos">

                <svg class="nav-icon">
                    <use href="#i-refund"/>
                </svg>

                <span>Reembolsos</span>
            </button>

            <button type="button"
                    class="nav-item"
                    data-section="usuarios">

                <svg class="nav-icon">
                    <use href="#i-users"/>
                </svg>

                <span>Usuarios</span>
            </button>

            <button type="button"
                    class="nav-item"
                    data-section="banner">

                <svg class="nav-icon">
                    <use href="#i-image"/>
                </svg>

                <span>Banner</span>
            </button>

            <div class="nav-divider"></div>

            <div class="sidebar-caption">
                HERRAMIENTAS
            </div>

            <button type="button"
                    class="nav-item"
                    data-section="notificaciones">

                <svg class="nav-icon">
                    <use href="#i-bell"/>
                </svg>

                <span>Notificaciones</span>
                <span id="so-notif-badge"
                      class="so-notif-badge"
                      aria-label="Notificaciones pendientes"
                      hidden>0</span>

            </button>

            <button type="button"
                    class="nav-item"
                    data-section="bitacora">

                <svg class="nav-icon">
                    <use href="#i-clock"/>
                </svg>

                <span>Bitácora</span>
            </button>

            <button type="button"
                    class="nav-item"
                    data-section="reportes">

                <svg class="nav-icon">
                    <use href="#i-chart"/>
                </svg>

                <span>Reportes</span>
            </button>

            <button type="button"
                    class="nav-item"
                    data-section="configuracion">

                <svg class="nav-icon">
                    <use href="#i-settings"/>
                </svg>

                <span>Configuración</span>

            </button>

        </nav>

        <!-- TARJETA PROMOCIONAL -->
        <div class="sidebar-promo">

            <div class="promo-shade">
                <strong>
                    La música<br>
                    nos conecta.
                </strong>

                <span>
                    Más personas.<br>
                    Más historias.
                </span>
            </div>

        </div>

        <div class="sidebar-bottom">
            <a href="/catalogo">
                ← Volver al catálogo
            </a>

            <small>Administrador · Soirée</small>
        </div>

    </aside>

    <!-- =================================================
         CONTENIDO PRINCIPAL
    ================================================= -->

    <main class="content">

        <!-- BARRA SUPERIOR -->
        <div class="utility-bar">

            <div class="utility-person">

                <span class="top-online">
                    <span class="online-indicator"></span>
                    SESIÓN ADMIN
                </span>

                {{-- Cambiar entre tema claro y oscuro (admin-tema.js) --}}
                <button type="button" class="boton-tema" id="btnTema" aria-label="Cambiar a tema oscuro" title="Cambiar a tema oscuro">
                    <svg class="icono-luna" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5z"/></svg>
                    <svg class="icono-sol" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2.5v2M12 19.5v2M4.6 4.6l1.4 1.4M18 18l1.4 1.4M2.5 12h2M19.5 12h2M4.6 19.4 6 18M18 6l1.4-1.4"/></svg>
                </button>

                <span class="avatar">AD</span>

                <span>Administrador</span>
            </div>

        </div>

        <!-- ENCABEZADO -->
        <header class="hero">

            <div class="hero-copy">

                <div class="eyebrow">
                    BIENVENIDO A SOIRÉE
                </div>

                <h1 id="page-title">
                    Panel de <em>Administración</em>
                </h1>

                <p id="page-subtitle">
                    Gestiona eventos, reservas y todo lo que hace
                    posible grandes experiencias.
                </p>

            </div>

            <span class="user-pill">

                <svg class="utility-icon">
                    <use href="#i-shield"/>
                </svg>

                Acceso administrador
            </span>

        </header>

        <!-- ESTADO DE CARGA Y ERRORES -->
        <div id="feedback"
             class="feedback"
             role="status"
             aria-live="polite">
            Validando sesión administrativa…
        </div>

        <!-- =================================================
             CONTENIDO DE RESUMEN Y TABLAS
        ================================================= -->

        <section id="dashboard" hidden>

            <!-- ===========================================
                 SOLO PARA LA SECCIÓN RESUMEN
            ============================================ -->

            <div id="overview-content">

                <!-- TARJETAS ESTADÍSTICAS -->
                <div id="summary"
                     class="metrics"
                     aria-label="Indicadores de la plataforma">
                </div>

                {{-- VENTAS Y GANANCIAS (admin-finanzas.js; GET /api/admin/finanzas, solo administrador).
                     El filtro de fechas de arriba aplica a todo lo de abajo. --}}
                <section id="finanzas-panel" class="finanzas" aria-labelledby="finanzas-titulo">
                    <header class="finanzas__cabecera">
                        <div>
                            <span class="finanzas__eyebrow">Finanzas</span>
                            <h2 id="finanzas-titulo">Ventas y ganancias</h2>
                            <p id="finanzas-periodo" class="finanzas__nota">Consultando…</p>
                        </div>
                    </header>

                    <form class="finanzas__filtro" id="finanzas-filtro" aria-label="Período">
                        <div class="finanzas__presets" role="group" aria-label="Períodos rápidos">
                            <button type="button" data-dias="7">7 días</button>
                            <button type="button" data-dias="30" aria-pressed="true">30 días</button>
                            <button type="button" data-dias="90">90 días</button>
                            <button type="button" data-anio>Este año</button>
                        </div>
                        <div class="finanzas__rango">
                            <label><span>Desde</span><input type="date" name="desde" id="finanzas-desde"></label>
                            <label><span>Hasta</span><input type="date" name="hasta" id="finanzas-hasta"></label>
                        </div>
                        <p class="finanzas__error" id="finanzas-error" role="alert" hidden></p>
                    </form>

                    <div class="finanzas__cifras" id="finanzas-cifras" aria-live="polite"></div>

                    <div class="finanzas__graficas">
                        <article class="finanzas__tarjeta" aria-labelledby="ventas-titulo">
                            <h3 id="ventas-titulo">Ventas</h3>
                            <p class="finanzas__nota" id="ventas-nota">Monto cobrado (pagos aprobados).</p>
                            <div class="grafica" id="grafica-ventas"></div>
                            <details class="finanzas__tabla">
                                <summary>Ver los datos en tabla</summary>
                                <div id="ventas-tabla"></div>
                            </details>
                        </article>

                        <article class="finanzas__tarjeta" aria-labelledby="ganancias-titulo">
                            <div class="finanzas__titulo-leyenda">
                                <h3 id="ganancias-titulo">Ganancia por evento</h3>
                                <ul class="leyenda" aria-label="Leyenda">
                                    <li><span class="leyenda__muestra leyenda__muestra--ganancia"></span>Ganancia</li>
                                    <li><span class="leyenda__muestra leyenda__muestra--perdida"></span>Pérdida</li>
                                </ul>
                            </div>
                            <p class="finanzas__nota">Eventos que ya ocurrieron (o se cancelaron) en el período.</p>
                            <div class="grafica" id="grafica-ganancias"></div>
                        </article>
                    </div>

                    <article class="finanzas__tarjeta" aria-labelledby="rentabilidad-titulo">
                        <div class="finanzas__titulo-leyenda">
                            <h3 id="rentabilidad-titulo">Rentabilidad por evento</h3>
                            <div class="finanzas__pestanas" role="tablist" aria-label="Eventos">
                                <button type="button" role="tab" id="pestana-historial" aria-selected="true" aria-controls="rentabilidad-tabla" data-vista="historial">Historial del período</button>
                                <button type="button" role="tab" id="pestana-proximos" aria-selected="false" aria-controls="rentabilidad-tabla" data-vista="proximos">Próximos eventos</button>
                            </div>
                        </div>
                        <p class="finanzas__nota" id="rentabilidad-nota"></p>
                        <div class="rentabilidad" id="rentabilidad-tabla" role="tabpanel"></div>
                        <button type="button" class="rentabilidad__mas" id="rentabilidad-mas" hidden>Ver más</button>
                    </article>
                </section>


            </div>
            <!-- FIN DE overview-content:
                 LAS ESTADÍSTICAS YA NO ENVUELVEN LA TABLA -->

            <!-- ===========================================
                 TABLA COMPARTIDA ENTRE SECCIONES
            ============================================ -->


<!-- ==========================================
     SOIRÉE - HERRAMIENTAS DE EVENTOS
========================================== -->

<div id="eventos-tools" class="eventos-tools" hidden>

    <div class="eventos-tools-copy">
        <span class="section-kicker">
            ADMINISTRACIÓN DE EVENTOS
        </span>

        <h2>Gestión de eventos</h2>

        <p>
            Crea, administra y publica eventos
            en el catálogo de Soirée.
        </p>
    </div>


</div>

{{-- Eventos sirve para crear y editar; la lista con sus acciones está en el catálogo.
     Se ve solo en Eventos y sin un formulario abierto (admin-tema.css) --}}
<div class="eventos-aviso">
    <div>
        <strong>¿Buscas un evento que ya existe?</strong>
        <span>Para verlo, editarlo, ponerle precios o publicarlo, búscalo en el catálogo.</span>
    </div>
    <button type="button" class="banner-boton" data-go="catalogo">Ir al catálogo de eventos</button>
</div>

<!-- ==========================================
     FORMULARIO DE EVENTOS
========================================== -->

<section
    id="evento-form-panel"
    class="panel evento-form-panel"
    hidden>

    <div class="evento-form-heading">

        <div>
            <span class="section-kicker">
                CENTRO DE EVENTOS
            </span>

            <h2 id="evento-form-title">
                Crear nuevo evento
            </h2>

            <p>
                Completa la información para guardar
                el evento como borrador.
            </p>
        </div>

        <button
            id="btn-cerrar-evento"
            type="button"
            class="evento-close"
            aria-label="Cerrar formulario">
            ✕
        </button>

    </div>

    <form id="evento-form">

        <div class="evento-form-grid">

            <label>
                Nombre del evento

                <input
                    name="nombre"
                    type="text"
                    maxlength="255"
                    placeholder="Ej. Festival de música"
                    required>
            </label>


            <label>
                Fecha y hora

                <input
                    name="fecha_evento"
                    type="datetime-local"
                    required>
            </label>

            {{-- Título de la primera escena de la experiencia (admin-evento-formulario.js lleva la cuenta) --}}
            <label class="evento-full evento-frase">
                <span>Frase destacada <span class="evento-frase__opcional">(opcional)</span></span>

                <input
                    type="text"
                    name="frase_destacada"
                    id="evento-frase"
                    maxlength="70"
                    autocomplete="off"
                    placeholder="Ej. Tres décadas de jazz en una sola noche"
                    aria-describedby="evento-frase-ayuda evento-frase-contador">

                <span class="evento-frase__pie">
                    <small id="evento-frase-ayuda">Encabeza la primera escena de la experiencia. Si la dejas vacía, se usa el nombre del evento.</small>
                    <small class="evento-frase__contador" id="evento-frase-contador" aria-live="polite">0 / 70</small>
                </span>
            </label>

            <label class="evento-full">
                Descripción

                <textarea
                    name="descripcion"
                    id="evento-descripcion"
                    rows="4"
                    maxlength="600"
                    placeholder="Describe el evento..."
                    aria-describedby="evento-descripcion-ayuda evento-descripcion-contador"></textarea>
                <span class="evento-frase__pie">
                    <small id="evento-descripcion-ayuda">Se ve completa en la página del evento; la primera escena de la experiencia muestra los primeros 170 caracteres.</small>
                    <small class="evento-frase__contador" id="evento-descripcion-contador" aria-live="polite">0 / 600</small>
                </span>
            </label>

            <label class="evento-recinto">
                Recinto

                <select
                    name="recinto_id"
                    id="evento-recinto"
                    required>

                    <option value="">
                        Seleccionar recinto
                    </option>

                </select>
                {{-- Plano del recinto elegido (admin-evento-formulario.js) --}}
                <button type="button" class="evento-ver-plano" id="evento-ver-plano" aria-expanded="false" aria-controls="evento-plano" disabled>
                    Ver plano del recinto
                </button>
            </label>


            {{-- Plano del recinto, desplegable (no ocupa espacio hasta que se pide) --}}
            <div class="evento-full evento-plano asientos-app" id="evento-plano" hidden>
                <div class="evento-plano__mapa mapa mapa--admin" id="evento-plano-mapa" aria-live="polite"></div>
            </div>

            {{-- FOTOS (admin-evento-formulario.js): se suben al elegirlas; las tres son obligatorias.
                 1 = portada del catálogo y primera escena de la experiencia; 2 y 3 = las otras escenas. --}}
            <fieldset class="evento-full evento-fotos" id="evento-fotos">
                <legend>Fotos del evento <span>Las tres son obligatorias · JPG, PNG o WebP de hasta {{ config('catalogo.imagen_evento_max_mb') }} MB</span></legend>
                <div class="evento-fotos__lista">
                    @foreach ([['imagen_url', 'Portada', 'Se ve en el catálogo y en la primera escena del evento'], ['imagen_url_2', 'Segunda escena', 'Aparece al bajar por la experiencia del evento'], ['imagen_url_3', 'Tercera escena', 'La última antes de elegir asientos']] as $i => [$campo, $titulo, $ayuda])
                        <div class="evento-foto" data-campo="{{ $campo }}">
                            <input type="hidden" name="{{ $campo }}">
                            <label class="evento-foto__zona">
                                <input type="file" accept="image/jpeg,image/png,image/webp" class="evento-foto__archivo">
                                <img class="evento-foto__vista" alt="" hidden>
                                <span class="evento-foto__vacia">
                                    <strong>{{ $i + 1 }} · {{ $titulo }}</strong>
                                    <span>Toca o arrastra una foto aquí</span>
                                </span>
                                <span class="evento-foto__estado" role="status"></span>
                            </label>
                            <small>{{ $ayuda }}</small>
                        </div>
                    @endforeach
                </div>
                <p class="evento-form-feedback" id="evento-fotos-mensaje" role="alert" hidden></p>
            </fieldset>

        </div>

        {{-- COSTOS DE PRODUCCIÓN (admin-costos.js): un campo por concepto. El recinto y los artistas
             son obligatorios; con el total se sugieren los precios de abajo. Los registran admin y
             operador; la ganancia solo la ve el administrador. --}}
        <section id="evento-costos-panel" class="evento-costos" aria-labelledby="evento-costos-titulo">
            <div class="evento-costos__cabecera">
                <div>
                    <span class="evento-localidades-eyebrow">SOIRÉE · COSTOS DE PRODUCCIÓN</span>
                    <h3 id="evento-costos-titulo">Costos del evento</h3>
                    <p>Llénalos primero: con ellos te sugerimos el precio de cada localidad.</p>
                </div>
                <strong class="evento-costos__total" id="evento-costos-total">Total Q0.00</strong>
            </div>
            <div class="evento-costos__campos" id="evento-costos-campos">
                @foreach ([['recinto', 'Alquiler del recinto', true], ['artistas', 'Artistas', true], ['produccion', 'Producción (sonido, luces)', false], ['publicidad', 'Publicidad', false], ['personal', 'Personal (seguridad, taquilla)', false], ['otros', 'Otros gastos', false]] as [$categoria, $concepto, $obligatorio])
                    <label class="evento-costos__campo">
                        <span>{{ $concepto }}@if ($obligatorio) <b aria-hidden="true">*</b>@endif</span>
                        <span class="evento-costos__monto">
                            <i aria-hidden="true">Q</i>
                            <input type="number" inputmode="decimal" step="0.01" placeholder="0.00"
                                   min="{{ $obligatorio ? '0.01' : '0' }}" max="9999999999.99"
                                   data-categoria="{{ $categoria }}" data-concepto="{{ $concepto }}"
                                   @if ($obligatorio) required @endif>
                        </span>
                    </label>
                @endforeach
            </div>
            <p class="evento-form-feedback" id="evento-costos-mensaje" role="status" hidden></p>
        </section>

        <!-- PRECIOS SEGÚN EL RECINTO -->
        <section id="evento-localidades-panel"
                 data-comision="{{ config('finanzas.comision_pasarela') }}"
                 class="evento-localidades-panel"
                 aria-labelledby="evento-localidades-title">

            <div class="evento-localidades-heading">
                <div>
                    <span class="evento-localidades-eyebrow">
                        SOIRÉE · CONFIGURACIÓN DE TARIFAS
                    </span>
                    <h3 id="evento-localidades-title">
                        Localidades y precios
                    </h3>
                    <p>
                        Configura el precio de cada localidad
                        disponible en el recinto seleccionado.
                    </p>
                </div>
                <span class="evento-localidades-currency">
                    GTQ · Quetzales
                </span>
            </div>

            <p class="evento-precios-aviso" id="evento-precios-aviso" hidden>
                <strong>Revisa bien los precios.</strong> Puedes cambiarlos mientras el evento sea borrador,
                pero una vez publicado ya no se podrán modificar.
            </p>

            <div id="evento-localidades-lista"
                 class="evento-localidades-lista"
                 aria-live="polite">
                <p class="muted">
                    Selecciona un recinto para ver sus localidades.
                </p>
            </div>

            <p id="evento-localidades-feedback"
               class="evento-form-feedback"
               role="status"
               hidden></p>
        </section>

        <div
            id="evento-form-feedback"
            class="evento-form-feedback"
            role="status"
            aria-live="polite"
            hidden>
        </div>

        <div class="evento-form-actions">

            <button
                id="btn-cancelar-evento"
                type="button"
                class="evento-secondary">
                Cancelar
            </button>

            <button
                id="btn-guardar-evento"
                type="submit"
                class="primary">
                Guardar borrador
            </button>

        </div>

    </form>

</section>


            <!-- ============================================
                 GESTIÓN DE PRECIOS POR LOCALIDAD
                 Se abre desde las acciones de cada evento.
                 Los datos serán cargados mediante la API admin.
            ============================================= -->
            <section id="evento-precios-panel"
                     class="panel evento-form-panel evento-precios-panel"
                     aria-labelledby="evento-precios-title"
                     hidden>

                <div class="evento-form-heading">
                    <div>
                        <span class="section-kicker">TARIFAS DEL EVENTO</span>
                        <h2 id="evento-precios-title">Precios por localidad</h2>
                        <p id="evento-precios-subtitle">
                            Consulta o configura los precios de las secciones del recinto.
                        </p>
                    </div>

                    <button type="button"
                            id="btn-cerrar-precios"
                            class="evento-close"
                            aria-label="Cerrar gestión de precios">✕</button>
                </div>

                <form id="evento-precios-form">
                    <div id="evento-precios-lista"
                         class="evento-precios-lista"
                         aria-live="polite">
                        <!-- Aquí JavaScript insertará las localidades reales. -->
                    </div>

                    <div id="evento-precios-feedback"
                         class="evento-form-feedback"
                         role="status"
                         aria-live="polite"
                         hidden></div>

                    <div class="evento-form-actions">
                        <button type="button"
                                id="btn-cancelar-precios"
                                class="evento-secondary">Cerrar</button>
                        <button type="submit"
                                id="btn-guardar-precios"
                                class="primary"
                                disabled>Guardar precios</button>
                    </div>
                </form>
            </section>

                    <!-- ESTADOS DE RESERVAS: solo en la sección Reservas (admin.js) -->
                    <article class="panel chart-card estados-tira" id="reservas-estados" hidden>

                        <div class="panel-heading">

                            <div>
                                <div class="heading-icon">
                                    <svg>
                                        <use href="#i-ticket"/>
                                    </svg>
                                </div>

                                <h2>Estados de las reservas</h2>

                                <p>
                                    Distribución actual de las reservas
                                </p>
                            </div>

                            <span class="mini-count"
                                  id="reservas-total">
                            </span>

                        </div>

                        <div id="reservation-bars" class="estados-tira__barras"></div>

                    </article>

            <article class="panel data-card" id="tabla-datos">

                <div class="table-head">

                    <div>

                        <div class="section-kicker">
                            ACTIVIDAD DE LA PLATAFORMA
                        </div>

                        <h2 id="table-title">
                            Reservas recientes
                        </h2>

                        <p id="table-subtitle">
                            Últimos movimientos registrados en MySQL.
                        </p>

                    </div>

                    <button type="button"
                            class="text-link"
                            id="view-all"
                            data-go="reservas">

                        Ver todas
                        <span>→</span>
                    </button>

                </div>

                <!-- FILTROS DINÁMICOS -->
                <div id="filters"></div>

                <!-- TABLA DINÁMICA -->
                <div class="table-wrap">

                    <table>
                        <thead id="thead"></thead>
                        <tbody id="tbody"></tbody>
                    </table>

                </div>

                <!-- PAGINACIÓN -->
                <div id="pagination"
                     class="pagination">
                </div>

            </article>

        </section>

        <!-- =================================================
             CATÁLOGO DE EVENTOS (admin-catalogo-eventos.js)
             Búsqueda con filtros sobre todos los eventos:
             GET /api/admin/eventos/catalogo
        ================================================= -->

        <section id="catalogo-panel" class="catalogo-panel" hidden>
            {{-- Estados de los eventos: cada estado filtra el catálogo al tocarlo --}}
            <article class="panel chart-card estados-tira" aria-labelledby="catalogo-estados-titulo">
                <div class="panel-heading">
                    <div>
                        <div class="heading-icon">
                            <svg><use href="#i-calendar"/></svg>
                        </div>
                        <h2 id="catalogo-estados-titulo">Estados de los eventos</h2>
                        <p>Estado de publicación de todos los eventos. Toca uno para filtrar.</p>
                    </div>
                    <span class="mini-count" id="catalogo-estados-total"></span>
                </div>
                <div id="catalogo-estados" class="estados-tira__barras"></div>
            </article>

            <form class="catalogo-filtros" id="catalogo-filtros" role="search" aria-label="Filtrar eventos">
                <label class="catalogo-filtros__nombre">
                    <span>Nombre</span>
                    <input type="search" name="nombre" placeholder="Buscar un evento…" autocomplete="off">
                </label>
                <label>
                    <span>Estado</span>
                    <select name="estado">
                        <option value="">Todos</option>
                        <option value="publicado">Publicado</option>
                        <option value="borrador">Borrador</option>
                        <option value="agotado">Agotado</option>
                        <option value="finalizado">Finalizado</option>
                        <option value="cancelado">Cancelado</option>
                    </select>
                </label>
                <label>
                    <span>Recinto</span>
                    <select name="recinto_id" id="catalogo-recinto">
                        <option value="">Todos</option>
                    </select>
                </label>
                <label>
                    <span>Desde</span>
                    <input type="date" name="desde">
                </label>
                <label>
                    <span>Hasta</span>
                    <input type="date" name="hasta">
                </label>
                <button type="reset" class="catalogo-limpiar">Limpiar</button>
            </form>

            <p class="catalogo-resumen" id="catalogo-resumen" role="status" aria-live="polite"></p>
            <div class="catalogo-grid" id="catalogo-grid"></div>
            <div class="catalogo-mas">
                <button type="button" class="banner-boton" id="catalogo-ver-mas" hidden>Ver más eventos</button>
            </div>
        </section>

        <!-- =================================================
             REEMBOLSOS (admin-reembolsos.js): solo admin
             ================================================= -->
        <section id="reembolsos-panel" class="reembolsos-panel" hidden>
            <div class="reembolsos-periodos" id="reembolsos-periodos" role="group" aria-label="Solicitudes hechas en">
                <button type="button" data-periodo="" aria-pressed="true">Todas</button>
                <button type="button" data-periodo="hoy" aria-pressed="false">Hoy</button>
                <button type="button" data-periodo="semana" aria-pressed="false">7 días</button>
                <button type="button" data-periodo="mes" aria-pressed="false">1 mes</button>
                <button type="button" data-periodo="anio" aria-pressed="false">1 año</button>
            </div>
            <div class="reembolsos-estados" id="reembolsos-estados" role="group" aria-label="Filtrar por estado"></div>
            <p class="catalogo-resumen" id="reembolsos-resumen" role="status" aria-live="polite"></p>
            <ol class="reembolsos-lista" id="reembolsos-lista"></ol>
            <div class="catalogo-mas">
                <button type="button" class="banner-boton" id="reembolsos-ver-mas" hidden>Ver más solicitudes</button>
            </div>
        </section>

        <!-- =================================================
             BITÁCORA (admin-bitacora.js): solo lectura, solo admin
        ================================================= -->

        <section id="bitacora-panel" class="bitacora-panel" hidden>
            <form class="bitacora-filtros" id="bitacora-filtros" role="search" aria-label="Filtrar la bitácora">
                <label class="bitacora-filtros__usuario">
                    <span>Usuario</span>
                    <input type="search" name="usuario" placeholder="Nombre o correo…" autocomplete="off">
                </label>
                <label>
                    <span>Categoría</span>
                    <select name="categoria" id="bitacora-categoria">
                        <option value="">Todas</option>
                    </select>
                </label>
                <label>
                    <span>Panel / rol</span>
                    <select name="rol">
                        <option value="">Todos</option>
                        <option value="admin">Administrador</option>
                        <option value="organizador">Operador</option>
                        <option value="cliente">Cliente</option>
                        <option value="sin_sesion">Sin sesión</option>
                    </select>
                </label>
                <label>
                    <span>Resultado</span>
                    <select name="resultado">
                        <option value="">Todos</option>
                        <option value="exito">Exitosos</option>
                        <option value="fallido">Fallidos</option>
                    </select>
                </label>
                <label>
                    <span>Desde</span>
                    <input type="date" name="desde">
                </label>
                <label>
                    <span>Hasta</span>
                    <input type="date" name="hasta">
                </label>
                <button type="reset" class="catalogo-limpiar">Limpiar</button>
            </form>

            <p class="catalogo-resumen" id="bitacora-resumen" role="status" aria-live="polite"></p>
            <ol class="bitacora-lista" id="bitacora-lista"></ol>
            <div class="catalogo-mas">
                <button type="button" class="banner-boton" id="bitacora-ver-mas" hidden>Ver más registros</button>
            </div>
        </section>

        <!-- =================================================
             BANNER DEL CATÁLOGO (admin-banner.js)
        ================================================= -->

        <section id="banner-panel"
                 class="panel banner-panel"
                 data-max-mb="{{ config('catalogo.banner.max_mb') }}"
                 data-max-segundos="{{ config('catalogo.banner.max_segundos_video') }}"
                 hidden>

            <div class="banner-encabezado">
                <div>
                    <div class="section-kicker">PORTADA DEL CATÁLOGO</div>
                    <h2>Fondo del catálogo</h2>
                    <p class="muted">
                        Elige una imagen o un video corto. Se muestra detrás del carrusel
                        con el tono oscuro del catálogo; así se verá:
                    </p>
                </div>
                <a class="text-link" href="{{ route('catalogo.index') }}" target="_blank" rel="noopener">Ver el catálogo ↗</a>
            </div>

            {{-- Vista previa con el mismo tratamiento oscuro que catalogo.css --}}
            <div class="banner-vista">
                <div class="banner-vista__fondo" id="banner-vista-fondo"></div>
                <div class="banner-vista__texto" aria-hidden="true">
                    <span>Temporada {{ now()->year }}</span>
                    <strong>Una noche que se <em>recuerda.</em></strong>
                </div>
                <span class="banner-vista__estado" id="banner-estado">Cargando…</span>
            </div>

            <div class="so-banner-info">
                <div class="so-banner-info__item">
                    <span>ESTADO DE PORTADA</span>
                    <strong id="banner-info-estado">Consultando...</strong>
                </div>
                <div class="so-banner-info__item">
                    <span>ARCHIVO</span>
                    <strong id="banner-info-archivo">Sin cambios pendientes</strong>
                </div>
            </div>

            <label class="banner-zona" id="banner-zona">
                <input type="file"
                       id="banner-archivo"
                       accept="image/jpeg,image/png,image/webp,video/mp4,video/webm">
                <strong>Arrastra aquí una imagen o un video</strong>
                <span>o haz clic para elegirlo · JPG, PNG, WEBP, MP4 o WEBM ·
                    máx. {{ config('catalogo.banner.max_mb') }} MB · videos de hasta {{ config('catalogo.banner.max_segundos_video') }} s</span>
            </label>

            <p id="banner-mensaje" class="banner-mensaje" role="status" aria-live="polite"></p>

            <div class="banner-acciones">
                <button type="button" class="primary" id="banner-publicar" hidden>Guardar y publicar portada</button>
                <button type="button" class="banner-boton" id="banner-cancelar" hidden>Descartar cambios</button>
                <button type="button" class="banner-boton banner-boton--quitar" id="banner-quitar" hidden>Restaurar portada original</button>
            </div>
        </section>

        <!-- =================================================
             SECCIONES PENDIENTES
        ================================================= -->

        <!-- CENTRO DE NOTIFICACIONES DEL ADMINISTRADOR -->
        <section id="notificaciones-panel"
                 class="panel so-notifications"
                 hidden>

            <div class="so-notifications-head">
                <div>
                    <span class="section-kicker">ACTIVIDAD DE SOIRÉE</span>
                    <h2>Centro de notificaciones</h2>
                    <p class="muted">
                        Reservas, pagos aprobados y boletos emitidos.
                    </p>
                </div>

                <button type="button"
                        class="banner-boton"
                        id="so-notificaciones-recargar">
                    Actualizar
                </button>
            </div>

            <div class="so-notifications-stats">
                <div><span>ACTIVIDAD RECIENTE</span>
                    <strong id="so-notif-total">—</strong></div>
                <div><span>RESERVAS</span>
                    <strong id="so-notif-reservas">—</strong></div>
                <div><span>PAGOS APROBADOS</span>
                    <strong id="so-notif-pagos">—</strong></div>
                <div><span>COMPRAS EMITIDAS</span>
                    <strong id="so-notif-compras">—</strong></div>
            </div>

            <div class="so-notifications-filters"
                 id="so-notificaciones-filtros"
                 aria-label="Filtrar actividad">
            </div>

            <p id="so-notificaciones-estado"
               class="muted"
               role="status"
               aria-live="polite"></p>

            <div id="so-notificaciones-lista"
                 class="so-notifications-list">
            </div>

            <p class="so-notifications-footnote">
                Actividad reciente basada en registros del sistema.
                Se actualiza automáticamente cada 30 segundos.
            </p>
        </section>


        <!-- CONFIGURACIÓN DE CUENTA SOIRÉE -->
        <section id="so-configuracion"
                 class="panel so-config"
                 hidden>

            <div class="so-config-header">
                <div class="so-config-avatar" id="so-config-iniciales">
                    US
                </div>

                <div>
                    <span class="so-config-eyebrow">
                        SOIRÉE · MI CUENTA
                    </span>
                    <h2>Configuración de mi perfil</h2>
                    <p>
                        Información personal y seguridad de la cuenta.
                    </p>
                </div>
            </div>

            <h3>Información personal</h3>

            <div class="so-config-grid">
                <div class="so-config-field">
                    <span>Nombre completo</span>
                    <strong id="so-config-nombre">—</strong>
                </div>

                <div class="so-config-field">
                    <span>Correo electrónico</span>
                    <strong id="so-config-correo">—</strong>
                </div>

                <div class="so-config-field">
                    <span>Rol</span>
                    <strong id="so-config-rol">—</strong>
                </div>

                <div class="so-config-field">
                    <span>Estado de cuenta</span>
                    <strong id="so-config-estado">—</strong>
                </div>

                <div class="so-config-field">
                    <span>ID de usuario</span>
                    <strong id="so-config-id">—</strong>
                </div>
            </div>


            <!-- SOIRÉE — CAMBIO DE CONTRASEÑA -->
            <div class="so-password-card">
                <div class="so-password-heading">
                    <span class="so-password-icon">✦</span>
                    <div>
                        <h3>Cambiar contraseña</h3>
                        <p>
                            Actualiza tu contraseña para mantener
                            protegida tu cuenta.
                        </p>
                    </div>
                </div>

                <form id="so-password-form" autocomplete="off">

                    <label for="so-password-actual">
                        Contraseña actual
                    </label>
                    <input
                        id="so-password-actual"
                        name="password_actual"
                        type="password"
                        autocomplete="current-password"
                        required>

                    <label for="so-password-nueva">
                        Nueva contraseña
                    </label>
                    <input
                        id="so-password-nueva"
                        name="password"
                        type="password"
                        autocomplete="new-password"
                        minlength="8"
                        required>

                    <label for="so-password-confirmar">
                        Confirmar nueva contraseña
                    </label>
                    <input
                        id="so-password-confirmar"
                        name="password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        minlength="8"
                        required>

                    <p class="so-password-help">
                        La contraseña debe cumplir las reglas
                        de seguridad establecidas por Soirée.
                    </p>

                    <p
                        id="so-password-mensaje"
                        class="so-password-message"
                        role="status"
                        aria-live="polite">
                    </p>

                    <button
                        type="submit"
                        id="so-password-guardar">
                        Actualizar contraseña
                    </button>
                </form>
            </div>


            <!-- SOIRÉE — EDITAR MI PERFIL -->
            <div class="so-edit-profile">
                <div class="so-edit-profile-heading">
                    <div>
                        <span class="so-config-eyebrow">
                            INFORMACIÓN PERSONAL
                        </span>
                        <h3>Editar mi perfil</h3>
                        <p>Actualiza los datos de tu cuenta.</p>
                    </div>
                </div>

                <form id="so-perfil-form">
                    <div class="so-edit-profile-grid">
                        <label>
                            Nombre
                            <input id="so-perfil-nombre"
                                   name="nombre"
                                   maxlength="100"
                                   autocomplete="given-name"
                                   required>
                        </label>

                        <label>
                            Apellido
                            <input id="so-perfil-apellido"
                                   name="apellido"
                                   maxlength="100"
                                   autocomplete="family-name"
                                   required>
                        </label>
                    </div>

                    <label>
                        Correo electrónico
                        <input id="so-perfil-email"
                               type="email"
                               readonly>
                    </label>

                    <p class="so-edit-profile-note">
                        Por seguridad, el correo se mantiene
                        protegido hasta habilitar su verificación.
                    </p>

                    <p id="so-perfil-mensaje"
                       role="status"
                       aria-live="polite"></p>

                    <button id="so-perfil-guardar"
                            type="submit">
                        Guardar cambios
                    </button>
                </form>
            </div>

            <div class="so-config-security">
                <h3>Seguridad y sesión</h3>
                <p>
                    Tu sesión está protegida mediante autenticación.
                    Al salir deberás iniciar sesión nuevamente.
                </p>

                <button type="button"
                        id="so-config-logout"
                        class="so-config-logout">
                    Cerrar sesión
                </button>

                <p id="so-config-mensaje"
                   class="so-config-message"
                   role="status"
                   aria-live="polite">
                    Puedes cerrar tu sesión de forma segura.
                </p>
            </div>

            <div class="so-config-system">
                <strong>SOIRÉE</strong>
                <span>
                    Plataforma de Eventos Masivos · Panel administrativo
                </span>
            </div>
        </section>

        <section id="coming-soon"
                 class="panel coming-soon"
                 hidden>

            <span class="coming-icon">✦</span>

            <h2 id="soon-title">
                Próximamente
            </h2>

            <p id="soon-description">
                Esta herramienta estará disponible cuando
                terminemos su implementación.
            </p>

            <button type="button"
                    class="primary"
                    data-go="resumen">

                Volver al resumen
            </button>

        </section>

        <!-- =================================================
             CENTRO DE REPORTES
        ================================================= -->

        <section id="reports"
                 class="panel report-card"
                 hidden>

            <div class="heading-icon">
                <svg>
                    <use href="#i-download"/>
                </svg>
            </div>

            <div class="section-kicker">
                CENTRO DE REPORTES
            </div>

            <h2>Reportes de Soirée</h2>

            <p class="muted">
                Exporta información real de MySQL en formato CSV,
                compatible con Microsoft Excel.
            </p>

            <form id="report-form">

                <label>
                    Datos a exportar

                    <select name="tipo">
                        <option value="reservas">Reservas</option>
                        <option value="eventos">Eventos</option>
                        <option value="pagos">Pagos</option>
                        <option value="usuarios">Usuarios</option>
                    </select>
                </label>

                <label>
                    Desde

                    <input name="desde"
                           type="date">
                </label>

                <label>
                    Hasta

                    <input name="hasta"
                           type="date">
                </label>


<div class="soiree-export-field">
    <label for="soiree-formato">
        Formato del reporte
    </label>

    <select id="soiree-formato" name="formato">
        <option value="excel">
            Excel profesional (.xlsx)
        </option>
        <option value="pdf">
            Documento PDF (.pdf)
        </option>
        <option value="csv">
            Datos CSV (.csv)
        </option>
    </select>
</div>

<button class="primary"
                        type="submit">

                    Generar informe
                </button>

            </form>

        </section>

        <!-- PIE DE PÁGINA -->
        <footer class="site-footer">

            <span>
                Soirée · Plataforma de Eventos Masivos
            </span>

            <span>
                Panel administrativo · Datos reales
            </span>

        </footer>

    </main>

</div>

</body>
</html>
