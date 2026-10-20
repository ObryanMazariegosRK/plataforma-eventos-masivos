{{--
    Recorrido por scroll (fotos del teatro + textos por parada).
    Variables: $paradas (config('landing.paradas')), $titulo, opcional $tituloCursiva y $detalle.
    Requiere css/landing.css y, al final de la página, js/vendor/lenis.min.js + js/landing.js.
--}}
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
            <h1 class="entrada__titulo">{{ $titulo }} @if (!empty($tituloCursiva))<em>{{ $tituloCursiva }}</em>@endif</h1>
            @if (!empty($detalle))
                <p class="entrada__detalle">{{ $detalle }}</p>
            @endif
            <span class="entrada__linea" aria-hidden="true"></span>
            <p class="entrada__pista">Desliza para entrar</p>
        </div>

        {{-- Salida: la última imagen se oscurece mientras la sección siguiente sube encima --}}
        <div class="salida" data-salida aria-hidden="true"></div>
    </div>
</section>
