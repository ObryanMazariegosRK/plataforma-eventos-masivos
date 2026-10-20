<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <meta name="robots" content="noindex,nofollow">
    <meta name="theme-color" content="#191126">

    <title>Soirée | Administración</title>

    <!-- Tipografías -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Outfit:wght@400;500;600;700;800;900&display=swap"
          rel="stylesheet">

    <!-- Estilos y JavaScript -->
    <!-- Base: estructura, formularios, tablas y diseño responsivo -->
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}?v=12">
    <!-- Soirée: primera identidad visual -->
    <link rel="stylesheet" href="{{ asset('css/admin-soiree.css') }}?v=3">
    <!-- Soirée: refinamiento de interfaz -->
    <link rel="stylesheet" href="{{ asset('css/admin-soiree-v2.css') }}?v=1">
    <!-- Funcionalidad administrativa existente -->
    <link rel="stylesheet" href="{{ asset('css/admin-soiree-v4.css') }}?v=1">
    <script defer src="{{ asset('js/admin.js') }}?v=15"></script>
    <link rel="stylesheet" href="{{ asset('css/admin-soiree-final.css') }}?v=1">
    <script defer src="{{ asset('js/admin-event-filters.js') }}?v=1"></script>
    <!-- Banner del catálogo (imagen o video de fondo) -->
    <link rel="stylesheet" href="{{ asset('css/admin-banner.css') }}?v={{ filemtime(public_path('css/admin-banner.css')) }}">
    <script defer src="{{ asset('js/admin-banner.js') }}?v={{ filemtime(public_path('js/admin-banner.js')) }}"></script>

    <!-- Estilos exclusivos de la vista previa de imagen -->
    <style>
        .evento-image-help {
            color: #8192a8;
            font-size: 11px;
            font-weight: 500;
        }
        .evento-image-preview {
            max-width: 420px;
            padding: 8px;
            border: 1px solid #dbe8ed;
            border-radius: 13px;
            background: #f4f8fa;
        }
        .evento-image-preview img {
            display: block;
            width: 100%;
            max-height: 240px;
            object-fit: cover;
            border-radius: 9px;
        }

        /* Gestión de precios por localidad */
        .evento-precios-panel { margin-bottom: 14px; }
        .evento-precios-lista { display: grid; gap: 10px; }
        .evento-precio-fila {
            display: grid;
            grid-template-columns: minmax(0, 1fr) minmax(140px, 185px);
            gap: 14px;
            align-items: center;
            padding: 13px 15px;
            border: 1px solid #e3ebf2;
            border-radius: 11px;
            background: #f8fbfd;
        }
        .evento-precio-fila strong { display: block; font-size: 13px; color: #18324a; }
        .evento-precio-fila small { color: #8496aa; font-size: 11px; }
        .evento-precio-fila label { display: grid; gap: 5px; color: #536b82; font-size: 11px; font-weight: 800; }
        .evento-precio-fila input { width: 100%; min-width: 0; }
        #btn-guardar-precios:disabled { opacity: .45; cursor: not-allowed; }
        @media (max-width: 600px) {
            .evento-precio-fila { grid-template-columns: 1fr; }
        }
    </style>
</head>

<body>

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

    <!-- BARRA LATERAL -->
    <aside class="sidebar"
           aria-label="Navegación administrativa">

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

                <span>Resumen</span>
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
                <span class="nav-soon">Pronto</span>
            </button>

            <button type="button"
                    class="nav-item"
                    data-section="historial">

                <svg class="nav-icon">
                    <use href="#i-clock"/>
                </svg>

                <span>Historial</span>
                <span class="nav-soon">Pronto</span>
            </button>

            <button type="button"
                    class="nav-item"
                    data-section="reportes">

                <svg class="nav-icon">
                    <use href="#i-chart"/>
                </svg>

                <span>Reportes CSV</span>
            </button>

            <button type="button"
                    class="nav-item"
                    data-section="configuracion">

                <svg class="nav-icon">
                    <use href="#i-settings"/>
                </svg>

                <span>Configuración</span>
                <span class="nav-soon">Pronto</span>
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

            <div class="top-search">

                <svg class="utility-icon">
                    <use href="#i-search"/>
                </svg>

                <input id="global-search"
                       type="search"
                       placeholder="Buscar usuarios..."
                       aria-label="Buscar usuarios">

            </div>

            <div class="utility-person">

                <span class="top-online">
                    <span class="online-indicator"></span>
                    SESIÓN ADMIN
                </span>

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

                <!-- GRÁFICAS Y ACCIONES RÁPIDAS -->
                <div class="insights-grid">

                    <!-- ESTADOS DE RESERVAS -->
                    <article class="panel chart-card">

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

                        <div id="reservation-bars"></div>

                    </article>

                    <!-- ESTADOS DE EVENTOS -->
                    <article class="panel chart-card">

                        <div class="panel-heading">

                            <div>
                                <div class="heading-icon">
                                    <svg>
                                        <use href="#i-calendar"/>
                                    </svg>
                                </div>

                                <h2>Estados de los eventos</h2>

                                <p>
                                    Estado de publicación de los eventos
                                </p>
                            </div>

                            <span class="mini-count"
                                  id="eventos-total">
                            </span>

                        </div>

                        <div id="event-bars"></div>

                    </article>

                    <!-- ACCIONES RÁPIDAS -->
                    <article class="panel quick-card">

                        <div class="panel-heading">

                            <div>
                                <div class="heading-icon">
                                    <svg>
                                        <use href="#i-zap"/>
                                    </svg>
                                </div>

                                <h2>Acciones rápidas</h2>

                                <p>
                                    Accede a tus herramientas administrativas
                                </p>
                            </div>

                        </div>

                        <div class="quick-links">

                            <button type="button"
                                    class="quick-link featured"
                                    data-go="reportes">

                                <svg>
                                    <use href="#i-download"/>
                                </svg>

                                <span>Descargar reporte</span>
                                <b>→</b>
                            </button>

                            <button type="button"
                                    class="quick-link"
                                    data-go="eventos">

                                <svg>
                                    <use href="#i-calendar"/>
                                </svg>

                                <span>Ver eventos</span>
                                <b>›</b>
                            </button>

                            <button type="button"
                                    class="quick-link"
                                    data-go="usuarios">

                                <svg>
                                    <use href="#i-users"/>
                                </svg>

                                <span>Consultar usuarios</span>
                                <b>›</b>
                            </button>

                        </div>

                    </article>

                </div>
                <!-- FIN DE GRÁFICAS -->

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

    <button
        type="button"
        class="primary"
        id="btn-nuevo-evento">
        + Nuevo evento
    </button>

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
                Recinto

                <select
                    name="recinto_id"
                    id="evento-recinto"
                    required>

                    <option value="">
                        Seleccionar recinto
                    </option>

                </select>
            </label>

            <label>
                Fecha y hora

                <input
                    name="fecha_evento"
                    type="datetime-local"
                    required>
            </label>

            <label class="evento-full">
                Descripción

                <textarea
                    name="descripcion"
                    rows="4"
                    maxlength="10000"
                    placeholder="Describe el evento..."></textarea>
            </label>

            <!-- URL pública de fotografía del evento -->
            <label class="evento-full" for="evento-imagen-url">
                URL de imagen del evento

                <input
                    type="url"
                    name="imagen_url"
                    id="evento-imagen-url"
                    maxlength="2048"
                    placeholder="https://ejemplo.com/concierto.jpg"
                    inputmode="url"
                    autocomplete="off">

                <small class="evento-image-help">
                    Enlace HTTPS de una imagen pública. Es opcional.
                </small>
            </label>

            <!-- Vista previa: visible cuando la imagen carga -->
            <div id="evento-image-preview"
                 class="evento-full evento-image-preview"
                 hidden>
                <img id="evento-image-preview-img"
                     alt="Vista previa de la imagen del evento"
                     referrerpolicy="no-referrer">
            </div>

        </div>

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

            <article class="panel data-card">

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
                <button type="button" class="primary" id="banner-publicar" hidden>Publicar en el catálogo</button>
                <button type="button" class="banner-boton" id="banner-cancelar" hidden>Cancelar</button>
                <button type="button" class="banner-boton banner-boton--quitar" id="banner-quitar" hidden>Quitar y usar la foto por defecto</button>
            </div>
        </section>

        <!-- =================================================
             SECCIONES PENDIENTES
        ================================================= -->

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

                <button class="primary"
                        type="submit">

                    Descargar CSV
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

<!-- Vista previa independiente del envío del formulario -->
<script>
(() => {
    'use strict';

    const input = document.getElementById('evento-imagen-url');
    const preview = document.getElementById('evento-image-preview');
    const image = document.getElementById('evento-image-preview-img');
    const form = document.getElementById('evento-form');
    if (!input || !preview || !image) return;

    function updatePreview() {
        const value = input.value.trim();
        preview.hidden = true;
        image.removeAttribute('src');
        if (!value) return;
        try {
            const url = new URL(value);
            if (url.protocol !== 'https:') return;
            image.src = url.href;
        } catch (_) {
            // Mostrar únicamente URLs HTTPS válidas.
        }
    }

    input.addEventListener('input', updatePreview);
    input.addEventListener('change', updatePreview);
    image.addEventListener('load', () => {
        preview.hidden = false;
    });
    image.addEventListener('error', () => {
        preview.hidden = true;
    });
    if (form) {
        form.addEventListener('reset', () => {
            preview.hidden = true;
            image.removeAttribute('src');
        });
    }
})();
</script>
</body>
</html>
