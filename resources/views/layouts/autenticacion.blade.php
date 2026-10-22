<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    {{-- La página ya es oscura: evita que el "modo oscuro automático" del navegador la recoloree --}}
    <meta name="color-scheme" content="dark">
    <title>@yield('titulo') · {{ config('marca.nombre') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;1,400&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
    {{-- ?v=: cambia cuando cambia el archivo, así el navegador no usa una versión vieja --}}
    <link rel="stylesheet" href="{{ asset('css/autenticacion.css') }}?v={{ filemtime(public_path('css/autenticacion.css')) }}">
</head>
<body>
    <main class="auth">
        {{-- Columna izquierda: solo la foto del teatro y la marca (igual en todas las páginas) --}}
        <section class="auth__marca">
            {{-- <img> y no background-image: los modos oscuros de algunos navegadores quitan los fondos --}}
            <img class="auth__foto" src="{{ asset('img/landing/auditorio-general.jpg') }}" alt="" aria-hidden="true">
            <div class="auth__sombra" aria-hidden="true"></div>
            <a href="{{ route('catalogo.index') }}" class="auth__logo">{{ config('marca.nombre') }}</a>
        </section>

        {{-- Columna derecha: el formulario de cada página --}}
        <section class="auth__form">
            @yield('contenido')
        </section>
    </main>

    <script src="{{ asset('js/autenticacion.js') }}?v={{ filemtime(public_path('js/autenticacion.js')) }}"></script>
    @stack('scripts')
</body>
</html>
