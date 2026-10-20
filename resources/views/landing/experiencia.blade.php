@php
    $paradas = config('landing.paradas');
    [$titulo, $tituloCursiva] = config('landing.lugar');
    $nombreLugar = "{$titulo} {$tituloCursiva}";
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="dark">
    <title>{{ $nombreLugar }} · {{ config('marca.nombre') }}</title>
    <meta name="description" content="Recorre el {{ $nombreLugar }} y compra tus boletos sin filas ni sobreventa.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;1,400&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}?v={{ filemtime(public_path('css/landing.css')) }}">
    {{-- Precargar las imágenes del recorrido para que el scroll no muestre huecos --}}
    @foreach (array_unique(array_column($paradas, 'imagen')) as $imagen)
        <link rel="preload" as="image" href="{{ asset($imagen) }}">
    @endforeach
</head>
<body>
    <header class="cabecera" data-cabecera>
        <a href="{{ url('/') }}" class="marca">{{ config('marca.nombre') }}</a>
        <a href="{{ route('catalogo.index') }}" class="cabecera__boton">Ver eventos</a>
    </header>

    @include('landing._recorrido', ['paradas' => $paradas, 'titulo' => $titulo, 'tituloCursiva' => $tituloCursiva])

    <section class="final">
        <p class="final__etiqueta">Venta abierta</p>
        <h2 class="final__titulo">Tu lugar te está <em>esperando.</em></h2>
        <span class="final__linea" aria-hidden="true"></span>
        <p class="final__texto">Cola virtual, asiento apartado mientras pagas y boleto digital al instante.</p>
        <div class="final__acciones">
            <a href="{{ route('catalogo.index') }}" class="boton boton--primario">Ver eventos</a>
            <a href="{{ route('login') }}" class="boton boton--secundario">Iniciar sesión</a>
        </div>
    </section>

    {{-- Lenis: scroll suave (MIT). Va antes de landing.js, que lo usa si está disponible --}}
    <script src="{{ asset('js/vendor/lenis.min.js') }}"></script>
    <script src="{{ asset('js/landing.js') }}?v={{ filemtime(public_path('js/landing.js')) }}"></script>
</body>
</html>
