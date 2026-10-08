<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('titulo') · Eventos Masivos</title>
    <link rel="stylesheet" href="{{ asset('css/autenticacion.css') }}">
</head>
<body>
    <main class="auth">
        {{-- Columna izquierda: marca (igual en todas las páginas) --}}
        <section class="auth__marca">
            <div class="auth__logo">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M3 8a2 2 0 0 0 2-2h14a2 2 0 0 0 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 0-2 2H5a2 2 0 0 0-2-2v-2a2 2 0 0 0 0-4z"/>
                    <path d="M13 6v2M13 11v2M13 16v2"/>
                </svg>
                Eventos Masivos
            </div>

            <div class="auth__eslogan">
                <h2>@yield('eslogan', 'Tu lugar en el próximo gran evento.')</h2>
                <p>Compra boletos de forma segura, sin sobreventa y con tu asiento reservado mientras pagas.</p>
            </div>

            <div class="boleto" aria-hidden="true">
                <div><small>EVENTO</small><strong>Concierto 2026</strong></div>
                <div><small>LOCALIDAD</small><strong>VIP · A-12</strong></div>
            </div>
        </section>

        {{-- Columna derecha: el formulario de cada página --}}
        <section class="auth__form">
            @yield('contenido')
        </section>
    </main>

    <script src="{{ asset('js/autenticacion.js') }}"></script>
    @stack('scripts')
</body>
</html>
