{{--
Selector de asientos (plano + panel). Se usa en /eventos/{id}/asientos y al final de la experiencia.
Variables: $eventoId; opcional $volver (a dónde regresar tras iniciar sesión; por defecto, esta página).
Requiere: css/asientos.css, js/autenticacion.js, js/asientos.js y js/seleccion-asientos.js.
--}}
<div class="asientos-app">
{{-- en celular el nombre va arriba del plano --}}
<div class="evento evento--movil">
    <h1 class="evento__nombre" data-nombre-evento></h1>
    <p class="evento__datos" data-datos-evento></p>
</div>

<main class="contenido"
      id="seleccionAsientos"
      data-evento="{{ $eventoId }}"
      data-volver="{{ $volver ?? '' }}"
      data-maximo="{{ config('reservas.max_boletos_por_persona') }}"
      data-minutos="{{ intdiv(config('reservas.ttl_segundos'), 60) }}">

    <section class="tarjeta mapa" id="mapa" aria-label="Mapa de asientos">
        <p class="cargando">Cargando el mapa del recinto…</p>
    </section>

    <aside class="tarjeta resumen resumen--cliente" id="resumen" aria-live="polite">
        {{-- datos del evento en el panel: así el plano usa toda la altura --}}
        <div class="evento">
            <span class="evento__etiqueta mayus">Elige tus asientos</span>
            <h2 class="evento__nombre" data-nombre-evento>Cargando…</h2>
            <p class="evento__datos" data-datos-evento></p>
        </div>

        <h2 class="resumen__titulo">Tu selección</h2>
        <p class="resumen__ayuda" id="ayuda"></p>

        <div class="aviso" id="aviso" role="alert"></div>

        {{-- Lugares apartados sin pagar (cuentan para el máximo mientras no vencen):
             continuar el pago o liberarlos (seleccion-asientos.js) --}}
        <div class="pendientes" id="pendientes" hidden></div>

        {{-- Ya tiene el máximo de boletos por persona para este evento --}}
        <p class="limite" id="limite" hidden>
            <span id="limiteTexto"></span>
            <a href="{{ route('reservas.mis-compras') }}" id="limiteCompras">Ver mis compras</a>
        </p>

        <ul class="lista" id="lista" data-lenis-prevent></ul>

        <div class="total">
            <span class="mayus">Total</span>
            <span class="total__monto" id="total">Q0.00</span>
        </div>

        <button type="button" class="boton-reservar" id="btnReservar" disabled>Reservar y pagar</button>
    </aside>
</main>
</div>
