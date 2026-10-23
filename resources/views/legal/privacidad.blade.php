@php
    $marca = config('marca.nombre');
    $contacto = config('marca.contacto');
    $maximo = config('reservas.max_boletos_por_persona');
    $minutos = intdiv(config('reservas.ttl_segundos'), 60);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="dark">
    <meta name="theme-color" content="#0d0c0b">
    <meta name="description" content="Qué datos pide {{ $marca }}, para qué los usa y cómo los protege.">
    <title>Política de privacidad · {{ $marca }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;1,400&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">

    {{-- Cabecera, pie y paleta del catálogo; catalogo.js arma el menú de la cuenta --}}
    <link rel="stylesheet" href="{{ asset('css/catalogo.css') }}?v={{ filemtime(public_path('css/catalogo.css')) }}">
    <script src="{{ asset('js/catalogo.js') }}?v={{ filemtime(public_path('js/catalogo.js')) }}" defer></script>
    @include('partials.avisos')
</head>
<body class="pagina-legal">

    <header class="navbar">
        <div class="contenedor nav-contenido">
            <a class="logo" href="{{ route('catalogo.index') }}" aria-label="{{ $marca }}, ir a la cartelera">{{ $marca }}</a>

            <nav class="nav-enlaces" aria-label="Navegación principal">
                <a href="{{ route('catalogo.index') }}">Cartelera</a>
                <a href="/login">Iniciar sesión</a>
                <a href="/registro" class="btn-registro">Registrarse</a>
            </nav>
        </div>
    </header>

    <main class="contenedor legal">
        <a class="legal__volver" href="{{ route('catalogo.index') }}">← Cartelera</a>

        <header class="legal__encabezado">
            <span class="label-seccion">Tus datos</span>
            <h1>Política de <em>privacidad</em></h1>
            <p class="legal__intro">
                En {{ $marca }} pedimos solo los datos que necesitamos para venderte tus boletos y dejarte entrar
                a la función. Aquí te contamos cuáles son, para qué los usamos y cómo los cuidamos.
            </p>
            <p class="legal__fecha">Última actualización: 21 de octubre de 2026</p>
        </header>

        <nav class="legal__indice" aria-label="Contenido">
            <a href="#datos">Qué datos pedimos</a>
            <a href="#usos">Para qué los usamos</a>
            <a href="#pagos">Pagos</a>
            <a href="#compartir">Con quién los compartimos</a>
            <a href="#seguridad">Cómo los protegemos</a>
            <a href="#tiempo">Cuánto tiempo los guardamos</a>
            <a href="#derechos">Tus derechos</a>
        </nav>

        <section id="datos" class="legal__seccion">
            <h2>Qué datos pedimos</h2>
            <ul>
                <li><strong>Para tu cuenta:</strong> nombre, apellido, correo electrónico y una contraseña.
                    La contraseña se guarda cifrada: nadie, ni siquiera nosotros, puede leerla.</li>
                <li><strong>Si entras con Google:</strong> solo tu nombre, tu correo y el identificador de tu cuenta
                    de Google. No recibimos tu contraseña de Google ni tus contactos, fotos o archivos.</li>
                <li><strong>Cuando compras:</strong> el evento, los asientos que elegiste, el monto pagado, la fecha
                    del pago y los boletos emitidos con su código QR.</li>
                <li><strong>Al usar el sitio:</strong> la sesión se guarda en tu navegador para no pedirte la
                    contraseña en cada página, y usamos cookies técnicas indispensables para que el sitio funcione.
                    No usamos cookies de publicidad ni de rastreo.</li>
                <li><strong>Si activas los avisos en tu dispositivo:</strong> la dirección que tu navegador crea para
                    recibirlos (no incluye tu nombre ni tu ubicación). Puedes desactivarlos cuando quieras tocando la
                    campana; también se borra si retiras el permiso en tu navegador.</li>
            </ul>
            <p class="legal__nota">
                <strong>No pedimos</strong> tu número de teléfono, tu dirección, tu documento de identificación ni tu
                fecha de nacimiento: no los necesitamos para venderte un boleto.
            </p>
        </section>

        <section id="usos" class="legal__seccion">
            <h2>Para qué los usamos</h2>
            <ul>
                <li><strong>Crear y proteger tu cuenta:</strong> te enviamos un código a tu correo para verificarla
                    y, si olvidas tu contraseña, otro para recuperarla.</li>
                <li><strong>Venderte tus boletos:</strong> apartamos tus asientos por {{ $minutos }} minutos mientras
                    pagas, registramos la compra y emitimos un boleto con código QR por cada asiento.</li>
                <li><strong>Mantenerte informado:</strong> te avisamos por correo cuando tus boletos están listos,
                    24 horas antes de la función, si la función cambia de fecha o se cancela y cuando se resuelve un
                    reembolso; si activaste la campana, también como notificación en tu dispositivo.</li>
                <li><strong>Dejarte entrar:</strong> en la entrada se escanea el código QR de tu boleto para
                    comprobar que es válido y que no se ha usado.</li>
                <li><strong>Que la venta sea justa:</strong> limitamos las compras a {{ $maximo }} boletos por persona
                    en cada función, para que nadie acapare los asientos.</li>
                <li><strong>Atender reembolsos y dudas</strong> sobre tus compras.</li>
            </ul>
            <p class="legal__nota">No vendemos tus datos ni los usamos para enviarte publicidad.</p>
        </section>

        <section id="pagos" class="legal__seccion">
            <h2>Pagos</h2>
            <p>
                Los datos de tu tarjeta se escriben en la página de la pasarela de pago, no en {{ $marca }}, y
                <strong>nunca los guardamos</strong>. De cada pago solo conservamos el monto, la fecha, su estado
                (aprobado o rechazado) y la referencia que nos da la pasarela.
            </p>
            <p class="legal__nota">
                Este sitio es un proyecto académico: la pasarela es una simulación y no se cobra dinero real.
            </p>
        </section>

        <section id="compartir" class="legal__seccion">
            <h2>Con quién los compartimos</h2>
            <ul>
                <li><strong>La pasarela de pago</strong>, solo el monto y la referencia de cada compra.</li>
                <li><strong>Google</strong>, solo si eliges entrar con tu cuenta de Google.</li>
                <li><strong>El servicio de correo</strong> que entrega nuestros mensajes, que recibe tu correo
                    electrónico y el contenido del mensaje.</li>
                <li><strong>El servicio de notificaciones de tu navegador</strong> (Google, Mozilla, Apple o Microsoft,
                    según el que uses), solo si activas los avisos en tu dispositivo: recibe el texto del aviso para
                    entregártelo.</li>
                <li><strong>Autoridades</strong>, únicamente si una ley nos obliga a hacerlo.</li>
            </ul>
        </section>

        <section id="seguridad" class="legal__seccion">
            <h2>Cómo los protegemos</h2>
            <ul>
                <li>Las contraseñas se guardan cifradas con un algoritmo de un solo sentido.</li>
                <li>Los códigos de verificación vencen y se anulan tras varios intentos fallidos.</li>
                <li>Cada código QR es un valor aleatorio imposible de adivinar, y solo tú puedes ver los tuyos.</li>
                <li>Puedes cerrar la sesión en todos tus dispositivos desde «Mi perfil».</li>
            </ul>
        </section>

        <section id="tiempo" class="legal__seccion">
            <h2>Cuánto tiempo los guardamos</h2>
            <p>
                Los datos de tu cuenta se guardan mientras la tengas activa. Las compras y los pagos se conservan
                el tiempo necesario para atender reembolsos, aclaraciones y registros contables. Los códigos de
                verificación y de recuperación dejan de servir en cuanto se usan o vencen.
            </p>
        </section>

        <section id="derechos" class="legal__seccion">
            <h2>Tus derechos</h2>
            <p>
                Puedes pedirnos en cualquier momento <strong>ver</strong> los datos que tenemos de ti,
                <strong>corregirlos</strong> o <strong>eliminar tu cuenta</strong>. Escríbenos a
                <a href="mailto:{{ $contacto }}">{{ $contacto }}</a> y te responderemos lo antes posible.
                Tu nombre y tu correo los puedes consultar también en «Mi perfil».
            </p>
        </section>
    </main>

    @include('partials.pie-pagina')

</body>
</html>
