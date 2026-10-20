<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="dark">
    <title>Ocupación en vivo · {{ config('marca.nombre') }}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;1,400&family=Inter:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/asientos.css') }}?v={{ filemtime(public_path('css/asientos.css')) }}">
</head>
<body class="pagina-asientos">
    <header class="cabecera">
        <a href="{{ route('landing.experiencia') }}" class="marca">{{ config('marca.nombre') }}</a>
        <nav class="cabecera__enlaces">
            <a href="/admin">Panel de administración</a>
        </nav>
    </header>

    <main class="contenido contenido--admin asientos-app" id="pagina" data-evento="{{ $eventoId }}">
        <section class="tarjeta mapa mapa--admin" id="mapa" aria-label="Mapa de ocupación">
            <p class="cargando">Cargando el mapa del recinto…</p>
        </section>

        <aside class="tarjeta estadisticas" aria-live="polite">
            <div class="evento">
                <span class="evento__etiqueta mayus">Ocupación en vivo</span>
                <h1 class="evento__nombre" id="nombreEvento">Cargando…</h1>
                <p class="evento__datos" id="datosEvento"></p>
            </div>

            <span class="vivo mayus">En vivo · cada 4 s</span>

            <div class="cifra">
                <span class="mayus">Vendidos</span>
                <div class="cifra__valor" id="vendidos">—</div>
                <div class="cifra__detalle" id="ocupacion"></div>
            </div>
            <div class="cifra">
                <span class="mayus">Apartados (en pago)</span>
                <div class="cifra__valor" id="apartados">—</div>
                <div class="cifra__detalle">Se liberan solos si no pagan a tiempo</div>
            </div>
            <div class="cifra">
                <span class="mayus">Disponibles</span>
                <div class="cifra__valor" id="disponibles">—</div>
            </div>
            <div class="cifra">
                <span class="mayus">Recaudado</span>
                <div class="cifra__valor" id="recaudado">—</div>
                <div class="cifra__detalle">Lugares vendidos × precio de su zona</div>
            </div>

            <label class="selector">
                <span class="mayus">Ver otro evento</span>
                <select id="selectorEvento"><option value="">Cargando eventos…</option></select>
            </label>
        </aside>
    </main>

    <script src="{{ asset('js/autenticacion.js') }}?v={{ filemtime(public_path('js/autenticacion.js')) }}"></script>
    <script src="{{ asset('js/asientos.js') }}?v={{ filemtime(public_path('js/asientos.js')) }}"></script>
    <script>
    (() => {
        const eventoId = Number(document.getElementById('pagina').dataset.evento);
        const mapa = document.getElementById('mapa');
        const texto = (id, valor) => { document.getElementById(id).textContent = valor; };

        // Solo administradores. Los datos del mapa son públicos, pero esta vista es del panel.
        async function verificarAdmin() {
            if (!Auth.obtenerToken()) return false;
            const perfil = await Auth.api('/auth/perfil', { metodo: 'GET', conToken: true });
            return perfil.ok && String(perfil.data.rol).toLowerCase() === 'admin';
        }

        async function refrescar() {
            const respuesta = await Auth.api(`/eventos/${eventoId}/disponibilidad`, { metodo: 'GET' });
            if (!respuesta.ok) {
                mapa.innerHTML = '';
                const p = document.createElement('p');
                p.className = 'cargando';
                p.textContent = respuesta.status === 404
                    ? 'Este evento no está publicado: todavía no tiene lugares a la venta.'
                    : 'No se pudo cargar el mapa.';
                mapa.appendChild(p);
                return;
            }

            const datos = respuesta.data;
            texto('nombreEvento', datos.evento.nombre);
            texto('datosEvento', datos.evento.recinto);
            MapaAsientos.dibujar(mapa, datos, { modo: 'admin' });

            const r = datos.resumen;
            const recaudado = datos.localidades.reduce((suma, l) => suma + l.vendidos * (l.precio || 0), 0);
            texto('vendidos', r.vendidos);
            texto('ocupacion', `${r.total ? Math.round((r.vendidos / r.total) * 100) : 0}% del aforo (${r.total} lugares)`);
            texto('apartados', r.bloqueados);
            texto('disponibles', r.disponibles);
            texto('recaudado', MapaAsientos.moneda(recaudado));
        }

        async function cargarSelector() {
            const respuesta = await Auth.api('/eventos', { metodo: 'GET' });
            const selector = document.getElementById('selectorEvento');
            const eventos = Array.isArray(respuesta.data?.data) ? respuesta.data.data : [];
            selector.replaceChildren();
            eventos.forEach((evento) => {
                const opcion = new Option(evento.nombre, evento.id, false, Number(evento.id) === eventoId);
                selector.add(opcion);
            });
            if (!eventos.some((e) => Number(e.id) === eventoId)) {
                selector.add(new Option('— Evento actual —', '', true, true), 0);
            }
            selector.addEventListener('change', () => {
                if (selector.value) location.href = `/admin/eventos/${selector.value}/asientos`;
            });
        }

        (async () => {
            if (!(await verificarAdmin())) {
                location.href = `/login?tipo=aviso&mensaje=${encodeURIComponent('Inicia sesión como administrador para ver la ocupación.')}`;
                return;
            }
            await refrescar();
            cargarSelector();
            setInterval(() => { if (!document.hidden) refrescar(); }, 4000);
        })();
    })();
    </script>
</body>
</html>
