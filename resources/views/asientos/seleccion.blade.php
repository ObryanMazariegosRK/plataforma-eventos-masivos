<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="dark">
    <title>Elige tus asientos · {{ config('marca.nombre') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;1,400&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/asientos.css') }}?v={{ filemtime(public_path('css/asientos.css')) }}">
    @include('partials.iconos')
</head>
<body class="pagina-asientos">
    <header class="cabecera">
        <a href="{{ route('catalogo.index') }}" class="marca">{{ config('marca.nombre') }}</a>
        <nav class="cabecera__enlaces">
            <a href="{{ route('catalogo.index') }}">Eventos</a>
            <a href="{{ route('login') }}" id="enlaceCuenta">Iniciar sesión</a>
        </nav>
    </header>

    @include('asientos._selector', ['eventoId' => $eventoId])

    <script src="{{ asset('js/autenticacion.js') }}?v={{ filemtime(public_path('js/autenticacion.js')) }}"></script>
    <script src="{{ asset('js/asientos.js') }}?v={{ filemtime(public_path('js/asientos.js')) }}"></script>
    <script src="{{ asset('js/seleccion-asientos.js') }}?v={{ filemtime(public_path('js/seleccion-asientos.js')) }}"></script>
</body>
</html>
