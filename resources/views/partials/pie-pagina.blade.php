{{-- Pie de página de la tienda (catálogo, detalle, Mis compras, boletos, privacidad).
     Estilos en catalogo.css (.pie-pagina). --}}
@php
    $marca = config('marca.nombre');
    $maximo = config('reservas.max_boletos_por_persona');
    $minutos = intdiv(config('reservas.ttl_segundos'), 60);
@endphp
<footer class="pie-pagina">
    <div class="contenedor pie-pagina__columnas">
        <div class="pie-pagina__marca">
            <a class="logo" href="{{ route('catalogo.index') }}">{{ $marca }}</a>
            <p>Teatro, conciertos y funciones en vivo. Elige tu evento y tu butaca, frente al escenario.</p>
        </div>

        <nav class="pie-pagina__grupo" aria-label="Explora">
            <h2>Explora</h2>
            <a href="{{ route('catalogo.index') }}">Cartelera</a>
            <a href="{{ route('reservas.mis-compras') }}">Mis compras</a>
        </nav>

        <div class="pie-pagina__grupo">
            <h2>Antes de comprar</h2>
            <p>Hasta {{ $maximo }} boletos por persona en cada función.</p>
            <p>Apartamos tus asientos {{ $minutos }} minutos mientras pagas.</p>
            <p>Tus boletos con código QR quedan en «Mis compras».</p>
        </div>

        <nav class="pie-pagina__grupo" aria-label="Legal y contacto">
            <h2>Legal</h2>
            <a href="{{ route('legal.privacidad') }}">Política de privacidad</a>
            <a href="mailto:{{ config('marca.contacto') }}">{{ config('marca.contacto') }}</a>
        </nav>
    </div>

    <div class="contenedor pie-pagina__final">
        <span>© {{ now()->year }} {{ $marca }}</span>
        <span>Proyecto académico de Análisis de Sistemas II · Universidad Mariano Gálvez</span>
        <span>Pagos simulados: no se cobra dinero real</span>
    </div>
</footer>
