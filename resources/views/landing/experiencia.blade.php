@php
    // Nombre del lugar: la segunda parte se muestra en cursiva en el título de entrada.
    $lugar = ['Teatro de', 'Don Juan'];
    $nombreLugar = implode(' ', $lugar);

    // Cada parada del recorrido: su imagen, su texto y hacia dónde "camina" la cámara.
    //   foco        → punto de la foto hacia el que se acerca (x% y%), en pantallas horizontales
    //   focoMovil   → lo mismo en pantallas verticales (celular), donde la foto se recorta por los lados
    //   zoomInicial → acercamiento con el que empieza la parada (1 = foto completa)
    $paradas = [
        [
            'imagen'      => 'img/landing/auditorio-general.jpg',
            'alt'         => 'Vista general del teatro desde el balcón, con arcos iluminados y el escenario al fondo',
            'foco'        => '50% 60%',   // hacia el escenario
            'focoMovil'   => '50% 60%',
            'zoomInicial' => 1,
            'etiqueta'    => 'La llegada',
            'titulo'      => 'Esta noche, la función es tuya.',
            'texto'       => 'Las puertas están por abrirse. Miles de personas buscan su lugar al mismo tiempo.',
        ],
        [
            'imagen'      => 'img/landing/auditorio-butacas.jpg',
            'alt'         => 'Butacas del teatro con el escenario iluminado en azul a la izquierda',
            'foco'        => '35% 72%',   // hacia las butacas y el escenario
            'focoMovil'   => '22% 70%',   // en vertical, el escenario azul queda a la vista
            'zoomInicial' => 1,
            'etiqueta'    => 'Tu butaca',
            'titulo'      => 'Elige tu asiento.',
            'texto'       => 'Lo apartamos solo para ti durante 5 minutos mientras completas tu compra. Nadie más puede tomarlo.',
        ],
        [
            // la misma vista general, pero ya cerca del escenario: la foto es grande y aguanta el acercamiento
            'imagen'      => 'img/landing/auditorio-general.jpg',
            'alt'         => 'El escenario del teatro visto de cerca',
            'foco'        => '50% 64%',
            'focoMovil'   => '50% 64%',
            'zoomInicial' => 1.7,
            'etiqueta'    => 'Se abre el telón',
            'titulo'      => 'Tu boleto, en tu correo.',
            'texto'       => 'Pago seguro, boleto digital con código QR y cero sobreventa.',
        ],
    ];
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

    {{--
        El recorrido: una sección muy alta (una pantalla por parada + una extra).
        Dentro, el "escenario" se queda fijo (position: sticky) mientras haces scroll,
        y JavaScript convierte la posición del scroll en zoom, fundidos y textos.
    --}}
    <section class="recorrido" data-recorrido style="--paradas: {{ count($paradas) }}">
        <div class="escena" data-escena>
            @foreach ($paradas as $i => $parada)
                <img
                    class="escena__imagen"
                    data-imagen
                    src="{{ asset($parada['imagen']) }}"
                    alt="{{ $parada['alt'] }}"
                    data-zoom-inicial="{{ $parada['zoomInicial'] }}"
                    style="--foco: {{ $parada['foco'] }}; --foco-movil: {{ $parada['focoMovil'] }}"
                >
            @endforeach

            <div class="escena__sombra"></div>
            <div class="escena__velo"></div>

            @foreach ($paradas as $i => $parada)
                <article class="parada" data-parada>
                    <span class="parada__etiqueta">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }} <span class="parada__guion"></span> {{ $parada['etiqueta'] }}</span>
                    <h2 class="parada__titulo">{{ $parada['titulo'] }}</h2>
                    <p class="parada__texto">{{ $parada['texto'] }}</p>
                </article>
            @endforeach

            {{-- Raíl de progreso a la derecha --}}
            <div class="riel" aria-hidden="true"><div class="riel__relleno" data-riel></div></div>

            {{-- Entrada: título sobre negro que se desvanece con el primer scroll --}}
            <div class="entrada" data-entrada>
                <p class="entrada__antetitulo">{{ config('marca.nombre') }} presenta</p>
                <h1 class="entrada__titulo">{{ $lugar[0] }} <em>{{ $lugar[1] }}</em></h1>
                <span class="entrada__linea" aria-hidden="true"></span>
                <p class="entrada__pista">Desliza para entrar</p>
            </div>

            {{-- Salida: la última imagen se funde a negro antes de la sección final --}}
            <div class="salida" data-salida aria-hidden="true"></div>
        </div>
    </section>

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
