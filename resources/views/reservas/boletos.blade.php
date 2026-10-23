<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="dark">
    <meta name="theme-color" content="#0d0c0b">
    <meta name="robots" content="noindex,nofollow">
    <title>Tus boletos · {{ config('marca.nombre') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;1,400&family=Inter:wght@300;400;500&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">

    {{-- Cabecera y paleta del catálogo; catalogo.js arma el menú de la cuenta --}}
    <link rel="stylesheet" href="{{ asset('css/catalogo.css') }}?v={{ filemtime(public_path('css/catalogo.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/boletos.css') }}?v={{ filemtime(public_path('css/boletos.css')) }}">
    <script src="{{ asset('js/autenticacion.js') }}?v={{ filemtime(public_path('js/autenticacion.js')) }}"></script>
    <script src="{{ asset('js/catalogo.js') }}?v={{ filemtime(public_path('js/catalogo.js')) }}" defer></script>
    @include('partials.avisos')
    <script src="{{ asset('js/boletos.js') }}?v={{ filemtime(public_path('js/boletos.js')) }}" defer></script>
    @include('partials.iconos')
</head>
<body class="pagina-boletos" data-marca="{{ config('marca.nombre') }}">

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

    <main class="contenedor boletos" id="paginaBoletos" data-reserva="{{ request()->route('reservaId') }}">
        <a class="boletos__volver" href="{{ route('reservas.mis-compras') }}">← Mis compras</a>

        <header class="boletos__encabezado">
            <div>
                <span class="label-seccion" id="boletosCantidad">Tus boletos</span>
                <h1 id="boletosEvento">Cargando…</h1>
                <p class="boletos__datos" id="boletosDatos"></p>
            </div>
            <button type="button" class="boletos__imprimir" id="btnImprimir" hidden>Imprimir o guardar PDF</button>
        </header>

        <p class="boletos__aviso" id="boletosAviso" role="status" aria-live="polite">Cargando tus boletos…</p>

        <div class="boletos__lista" id="boletosLista"></div>

        <p class="boletos__consejo" id="boletosConsejo" hidden>
            En la entrada, sube el brillo de tu pantalla y muestra el código de cada boleto.
            Cada QR sirve una sola vez.
        </p>
    </main>

    @include('partials.pie-pagina')

</body>
</html>
