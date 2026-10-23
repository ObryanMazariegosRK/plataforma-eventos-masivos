<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    @include('partials.sesion')
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="dark">
    <meta name="theme-color" content="#0d0c0b">
    <meta name="robots" content="noindex,nofollow">
    <title>Mis compras · {{ config('marca.nombre') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;1,400&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">

    {{-- Cabecera y paleta del catálogo; catalogo.js arma el menú de la cuenta --}}
    <link rel="stylesheet" href="{{ asset('css/catalogo.css') }}?v={{ filemtime(public_path('css/catalogo.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/mis-compras.css') }}?v={{ filemtime(public_path('css/mis-compras.css')) }}">
    <script src="{{ asset('js/autenticacion.js') }}?v={{ filemtime(public_path('js/autenticacion.js')) }}"></script>
    <script src="{{ asset('js/catalogo.js') }}?v={{ filemtime(public_path('js/catalogo.js')) }}" defer></script>
    @include('partials.avisos')
    <script src="{{ asset('js/mis-compras.js') }}?v={{ filemtime(public_path('js/mis-compras.js')) }}" defer></script>
    @include('partials.iconos')
</head>
<body class="pagina-compras">

    <header class="navbar">
        <div class="contenedor nav-contenido">
            <a class="logo" href="{{ route('catalogo.index') }}" aria-label="{{ config('marca.nombre') }}, ir a la cartelera">
                {{ config('marca.nombre') }}
            </a>

            <nav class="nav-enlaces" aria-label="Navegación principal">
                <a href="{{ route('catalogo.index') }}">Cartelera</a>
                <a href="/login">Iniciar sesión</a>
                <a href="/registro" class="btn-registro">Registrarse</a>
            </nav>
        </div>
    </header>

    <main class="contenedor compras">
        <a class="compras__volver" href="{{ route('catalogo.index') }}#eventos">
            <span class="compras__flecha" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M11 6l-6 6 6 6"/></svg>
            </span>
            Volver a la cartelera
        </a>

        <header class="compras__encabezado">
            <span class="label-seccion">Tu historial</span>
            <h1>Mis <em>compras</em></h1>
        </header>

        <p class="compras__aviso" id="comprasAviso" role="status" aria-live="polite">Cargando tus compras…</p>

        {{-- Pestañas: funciones que vienen y funciones que ya pasaron --}}
        <div class="compras__pestanas" role="tablist" aria-label="Compras" id="pestanas" hidden>
            <button type="button" role="tab" id="pestanaProximas" aria-controls="panelCompras" data-grupo="proximas">
                Próximas <span class="compras__cuenta" id="cuentaProximas">0</span>
            </button>
            <button type="button" role="tab" id="pestanaPasadas" aria-controls="panelCompras" data-grupo="pasadas">
                Pasadas <span class="compras__cuenta" id="cuentaPasadas">0</span>
            </button>
        </div>

        <section class="compras__panel" id="panelCompras" role="tabpanel" hidden>
            <div class="compras__lista" id="listaCompras"></div>
            <p class="compras__sin" id="comprasSin" hidden></p>
            <div class="compras__mas">
                <button type="button" class="compras__ver-mas" id="verMas" hidden>Ver más</button>
            </div>
        </section>

        <div class="compras__vacio" id="comprasVacio" hidden>
            <p>Todavía no tienes compras.</p>
            <a class="btn-explorar" href="{{ route('catalogo.index') }}">Ver la cartelera</a>
        </div>
    </main>

    {{-- Solicitar reembolso (mis-compras.js): política, monto de hoy y motivo opcional --}}
    <dialog class="reembolso" id="dialogoReembolso" aria-labelledby="reembolsoTitulo">
        <form method="dialog" class="reembolso__caja" id="formReembolso">
            <button type="button" class="reembolso__cerrar" data-cerrar aria-label="Cerrar">✕</button>
            <span class="label-seccion">Tu compra</span>
            <h2 id="reembolsoTitulo" tabindex="-1">Solicitar reembolso</h2>
            <p class="reembolso__evento" id="reembolsoEvento"></p>

            <div class="reembolso__cuerpo" id="reembolsoCuerpo" aria-live="polite">
                <p class="reembolso__cargando">Calculando tu reembolso…</p>
            </div>

            <p class="reembolso__error" id="reembolsoError" role="alert" hidden></p>

            <div class="reembolso__acciones">
                <button type="button" class="reembolso__secundario" data-cerrar>Volver</button>
                <button type="submit" class="reembolso__principal" id="reembolsoEnviar" hidden>Solicitar reembolso</button>
            </div>
        </form>
    </dialog>

    @include('partials.pie-pagina')

</body>
</html>
