@php
    $paradas = config('landing.paradas');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="dark">
    <title>{{ $evento['nombre'] }} · {{ config('marca.nombre') }}</title>
    <meta name="description" content="{{ $evento['nombre'] }} en {{ $evento['recinto'] }}: elige tus asientos y compra tus boletos.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;1,400&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/landing.css') }}?v={{ filemtime(public_path('css/landing.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/asientos.css') }}?v={{ filemtime(public_path('css/asientos.css')) }}">
    @foreach (array_unique(array_column($paradas, 'imagen')) as $imagen)
        <link rel="preload" as="image" href="{{ asset($imagen) }}">
    @endforeach
</head>
<body>
    <header class="cabecera" data-cabecera>
        <a href="{{ route('catalogo.index') }}" class="marca">{{ config('marca.nombre') }}</a>
        {{-- data-corte: fundido a negro y salto directo al plano (landing.js) --}}
        <a href="#asientos" class="cabecera__boton" data-corte>Elegir asientos</a>
    </header>

    {{-- 1. El recorrido por el teatro, con el nombre del evento en la entrada --}}
    @include('landing._recorrido', ['paradas' => $paradas, 'titulo' => $evento['nombre'], 'detalle' => $detalle])

    {{-- 2. Al final del recorrido: el plano para elegir asientos (sube encima de la última foto) --}}
    <section class="seccion-asientos">
        <div id="asientos" class="seccion-asientos__ancla"></div>
        @include('asientos._selector', ['eventoId' => $eventoId, 'volver' => "/eventos/{$eventoId}/experiencia#asientos"])
    </section>

    <script src="{{ asset('js/vendor/lenis.min.js') }}"></script>
    <script src="{{ asset('js/landing.js') }}?v={{ filemtime(public_path('js/landing.js')) }}"></script>
    <script src="{{ asset('js/autenticacion.js') }}?v={{ filemtime(public_path('js/autenticacion.js')) }}"></script>
    <script src="{{ asset('js/asientos.js') }}?v={{ filemtime(public_path('js/asientos.js')) }}"></script>
    <script src="{{ asset('js/seleccion-asientos.js') }}?v={{ filemtime(public_path('js/seleccion-asientos.js')) }}"></script>
</body>
</html>
