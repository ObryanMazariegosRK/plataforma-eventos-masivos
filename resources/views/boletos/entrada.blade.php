<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="dark">
    <meta name="theme-color" content="#0d0c0b">
    <meta name="robots" content="noindex,nofollow">
    {{-- El código del boleto viaja en el fragmento (#): nunca se envía a otros sitios --}}
    <meta name="referrer" content="no-referrer">
    <title>Boleto · {{ config('marca.nombre') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;1,400&family=Inter:wght@300;400;500;600&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="{{ asset('css/catalogo.css') }}?v={{ filemtime(public_path('css/catalogo.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/verificacion-boleto.css') }}?v={{ filemtime(public_path('css/verificacion-boleto.css')) }}">
    <style>
        .entrada { min-height: 100dvh; display: grid; align-content: start; justify-items: center; gap: 22px; padding: 28px 16px 48px; }
        .entrada__marca { color: var(--marfil); font-family: var(--titulo); font-size: 26px; text-decoration: none; }
        .entrada__aviso { color: var(--piedra); font-family: var(--titulo); font-size: 22px; font-style: italic; text-align: center; }
        .entrada__aviso[hidden], .entrada__pie[hidden] { display: none; }
        .entrada__pie { max-width: 520px; color: var(--tenue); font-size: 13px; line-height: 1.6; text-align: center; }
        .entrada__pie a { color: var(--champan); }
    </style>
    <script src="{{ asset('js/verificacion-boleto.js') }}?v={{ filemtime(public_path('js/verificacion-boleto.js')) }}" defer></script>
    <script src="{{ asset('js/entrada-boleto.js') }}?v={{ filemtime(public_path('js/entrada-boleto.js')) }}" defer></script>
</head>
<body>
    <main class="entrada">
        <a class="entrada__marca" href="{{ route('catalogo.index') }}">{{ config('marca.nombre') }}</a>
        <p class="entrada__aviso" id="entradaAviso" role="status">Leyendo el boleto…</p>
        <div id="entradaResultado"></div>
        <p class="entrada__pie" id="entradaPie" hidden>
            Escanea el siguiente boleto con la cámara, o usa el
            <a href="/admin?seccion=acceso">control de acceso</a> del panel para escanear sin salir de la página.
        </p>
    </main>
</body>
</html>
