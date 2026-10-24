{{-- Sala de espera de la fila virtual: toma el turno, muestra cuántos van delante y, cuando le
     toca, manda al mapa de asientos con su tiempo para reservar (public/js/fila-virtual.js) --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    @include('partials.sesion')
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="dark">
    <title>Fila virtual · {{ config('marca.nombre') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;1,400&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/asientos.css') }}?v={{ filemtime(public_path('css/asientos.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/fila-virtual.css') }}?v={{ filemtime(public_path('css/fila-virtual.css')) }}">
    @include('partials.iconos')
</head>
<body class="pagina-asientos pagina-fila">
    <header class="cabecera">
        <a href="{{ route('catalogo.index') }}" class="marca">{{ config('marca.nombre') }}</a>
        <nav class="cabecera__enlaces">
            <a href="{{ route('catalogo.index') }}">Eventos</a>
            <a href="{{ route('reservas.mis-compras') }}">Mis compras</a>
        </nav>
    </header>

    <main class="fila" id="filaVirtual" data-evento="{{ $eventoId }}">
        <div class="fila__fondo" id="filaFondo" aria-hidden="true"></div>

        <section class="fila__tarjeta" aria-live="polite">
            <p class="fila__etiqueta mayus">Fila virtual</p>
            <h1 class="fila__evento" id="filaEvento">Cargando…</h1>
            <p class="fila__datos" id="filaDatos"></p>

            {{-- Lo llena fila-virtual.js según el estado del turno --}}
            <div class="fila__estado" id="filaEstado">
                <p class="fila__texto">Tomando tu lugar en la fila…</p>
            </div>

            <p class="fila__nota" id="filaNota">
                La fila avanza sola: puedes dejar esta página abierta. Cuando sea tu turno te avisaremos
                aquí, por correo y con una notificación si las activaste.
            </p>
        </section>
    </main>

    <script src="{{ asset('js/autenticacion.js') }}?v={{ filemtime(public_path('js/autenticacion.js')) }}"></script>
    <script src="{{ asset('js/fila-virtual.js') }}?v={{ filemtime(public_path('js/fila-virtual.js')) }}"></script>
</body>
</html>
