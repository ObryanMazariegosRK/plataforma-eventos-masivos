// Recorrido por scroll de la landing.
//
// Idea: la sección .recorrido mide (paradas + 1) pantallas de alto y su .escena se queda fija
// (sticky). Mientras bajas, convertimos "cuánto has bajado" en un número `w`:
//   w = 0   → inicio (título sobre negro, primera foto detrás)
//   w = 1   → segunda parada
//   w = 1.5 → a medio camino entre la segunda y la tercera
//   w = N   → fin (la sección final sube encima)
// y con ese número decidimos el zoom, la opacidad de cada foto y qué texto se ve.
//
// Suavizado:
//   - Con mouse/trackpad, Lenis (public/js/vendor/lenis.min.js) desplaza TODA la página con inercia
//     en lugar de saltar ~100 px por cada clic de la rueda; la animación lo sigue directo.
//   - En celular el dedo ya tiene inercia nativa; Lenis no la toca y la animación solo se suaviza un poco.

(() => {
    const recorrido = document.querySelector('[data-recorrido]');
    if (!recorrido) return;

    const escena = recorrido.querySelector('[data-escena]');
    const imagenes = [...recorrido.querySelectorAll('[data-imagen]')];
    // acercamiento con el que empieza cada parada (1 = foto completa)
    const zoomInicial = imagenes.map((imagen) => Number(imagen.dataset.zoomInicial || 1));
    const paradas = [...recorrido.querySelectorAll('[data-parada]')];
    const riel = recorrido.querySelector('[data-riel]');
    const entrada = recorrido.querySelector('[data-entrada]');
    const tituloEntrada = entrada.querySelector('.entrada__titulo');
    const salida = recorrido.querySelector('[data-salida]');
    const cabecera = document.querySelector('[data-cabecera]');

    const total = imagenes.length;
    const sinMovimiento = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    const ZOOM = 0.22;             // cuánto se acerca cada foto a lo largo de su tramo (22 %)
    const FUNDIDO = 0.35;          // la siguiente foto aparece durante el último 35 % del tramo anterior
    const ENTRADA = 0.4;           // el título sobre negro desaparece durante el primer 40 % del recorrido
    const SALIDA = 0.3;            // el oscurecimiento final ocupa el último 30 %
    const OSCURIDAD_FINAL = 0.6;   // no llega a negro total: la sección final entra encima con su degradado
    const SUAVIDAD_TACTIL = 0.2;   // celular: fracción del camino que recorre la animación por cuadro (más bajo = más inercia)
    const LENIS_LERP = 0.08;       // mouse/trackpad: suavidad del scroll de la página (más bajo = más inercia)

    const limitar = (valor) => Math.min(1, Math.max(0, valor));

    // ---- Distancia del recorrido: se calcula con la altura de la ESCENA, no de la ventana ----
    // En el celular la barra de direcciones aparece y desaparece al hacer scroll y cambia
    // window.innerHeight; si la usáramos, la animación saltaría. La escena mide 100svh (fija).
    let distancia = 1;
    let anchoAnterior = 0;
    const medir = () => {
        distancia = Math.max(1, recorrido.offsetHeight - escena.offsetHeight);
        anchoAnterior = window.innerWidth;
    };

    const progresoReal = () => limitar(-recorrido.getBoundingClientRect().top / distancia);

    function dibujar(progreso) {
        const w = progreso * total;

        // ---- Entrada: el negro se desvanece y revela la primera foto ----
        const revelado = limitar(w / ENTRADA);
        entrada.style.opacity = String(1 - revelado);
        entrada.style.visibility = revelado >= 1 ? 'hidden' : 'visible';
        if (!sinMovimiento) {
            tituloEntrada.style.transform = `translate3d(0, ${-40 * revelado}px, 0)`;
        }

        // ---- Fotos: fundido de entrada + acercamiento ("caminar hacia adentro") ----
        imagenes.forEach((imagen, i) => {
            const inicio = i === 0 ? 0 : i - FUNDIDO;               // cuándo empieza a verse
            const fin = i === total - 1 ? Infinity : i + 1;         // cuándo la siguiente ya la tapó por completo
            const opacidad = i === 0 ? 1 : limitar((w - inicio) / FUNDIDO);
            const avance = limitar((w - inicio) / (i + 1 - inicio)); // 0 al aparecer, 1 al terminar su tramo

            // fuera de su tramo no se dibuja: menos capas que mover para el celular
            imagen.style.visibility = w >= inicio && w <= fin ? 'visible' : 'hidden';
            imagen.style.opacity = String(opacidad);
            // con "reducir movimiento" la foto se queda quieta, pero respeta su encuadre inicial
            const zoom = sinMovimiento ? zoomInicial[i] : zoomInicial[i] * (1 + ZOOM * avance);
            imagen.style.transform = `scale3d(${zoom}, ${zoom}, 1)`;
        });

        // ---- Textos: cada uno se ve alrededor del centro de su tramo ----
        paradas.forEach((parada, i) => {
            const centro = i === 0 ? 0.7 : i + 0.45;   // el primero espera a que termine la entrada
            const visible = limitar(1 - Math.abs(w - centro) / 0.3);

            parada.style.opacity = String(visible);
            parada.style.transform = `translate3d(0, ${(1 - visible) * (w < centro ? 20 : -20)}px, 0)`;
        });

        // ---- Salida: la última foto se oscurece mientras la sección final sube encima ----
        salida.style.opacity = String(OSCURIDAD_FINAL * limitar((w - (total - SALIDA)) / SALIDA));

        if (riel) {
            riel.style.transform = `scaleY(${progreso})`;
        }
    }

    // ---- Scroll suave con Lenis (si cargó y la persona no pidió "reducir movimiento") ----
    const lenis = window.Lenis && !sinMovimiento
        ? new window.Lenis({ lerp: LENIS_LERP, smoothWheel: true, anchors: true })   // táctil nativo; enlaces #ancla suaves
        : null;

    // ---- Bucle de animación ----
    let actual = 0;          // lo que se está dibujando
    let animando = false;
    let ultimoCuadro = 0;
    let usandoTactil = false;   // el último desplazamiento vino de un dedo

    function cuadro(ahora) {
        const objetivo = progresoReal();

        // Con la rueda, Lenis ya suavizó la página: se dibuja directo (sin doble suavizado).
        // Con el dedo (o sin Lenis), la animación persigue al scroll; el paso no depende de los
        // cuadros por segundo, así se siente igual en pantallas de 60 Hz y de 120 Hz.
        const suavizar = !sinMovimiento && (usandoTactil || !lenis);
        const pasos = ultimoCuadro ? (ahora - ultimoCuadro) / (1000 / 60) : 1;
        ultimoCuadro = ahora;
        const factor = suavizar ? 1 - Math.pow(1 - SUAVIDAD_TACTIL, pasos) : 1;

        actual += (objetivo - actual) * factor;
        if (Math.abs(objetivo - actual) < 0.0002) {
            actual = objetivo;
        }

        dibujar(actual);
        cabecera?.classList.toggle('cabecera--solida', objetivo >= 1);

        // sigue animando solo mientras falte camino; luego se detiene (no gasta batería)
        if (actual !== objetivo) {
            requestAnimationFrame(cuadro);
        } else {
            animando = false;
            ultimoCuadro = 0;
        }
    }

    const despertar = () => {
        if (animando) return;
        animando = true;
        requestAnimationFrame(cuadro);
    };

    window.addEventListener('touchstart', () => { usandoTactil = true; }, { passive: true });
    window.addEventListener('wheel', () => { usandoTactil = false; }, { passive: true });
    window.addEventListener('scroll', despertar, { passive: true });
    window.addEventListener('resize', () => {
        // solo se vuelve a medir si cambió el ANCHO (girar el celular, cambiar el tamaño de la ventana);
        // los cambios de alto por la barra de direcciones se ignoran
        if (window.innerWidth !== anchoAnterior) {
            medir();
            despertar();
        }
    });

    if (lenis) {
        // Lenis necesita que le den cada cuadro para mover la página
        const avanzarLenis = (tiempo) => {
            lenis.raf(tiempo);
            requestAnimationFrame(avanzarLenis);
        };
        requestAnimationFrame(avanzarLenis);
    }

    // ---- Corte con fundido para los enlaces data-corte (p. ej. "Elegir asientos") ----
    // En lugar de recorrer todo el teatro a toda velocidad, la pantalla se funde a negro,
    // salta al destino y se aclara mostrándolo (como un corte de cine).
    const velo = document.createElement('div');
    velo.className = 'corte';
    velo.setAttribute('aria-hidden', 'true');
    document.body.appendChild(velo);

    function irConCorte(destino, hash) {
        const seccion = destino.closest('.seccion-asientos') || destino;
        const app = seccion.querySelector('.asientos-app') || destino;

        const saltar = () => {
            // En computadora el plano ya deja espacio para la cabecera fija; en celular se descuenta
            const margen = window.innerWidth > 900 ? 0 : (cabecera?.offsetHeight || 0);
            const y = Math.max(0, app.getBoundingClientRect().top + window.scrollY - margen);
            if (lenis) {
                lenis.scrollTo(y, { immediate: true, force: true });
            } else {
                window.scrollTo(0, y);
            }
            history.replaceState(null, '', hash);
            // el recorrido queda en su posición final de una vez (sin animarse detrás del negro)
            actual = progresoReal();
            dibujar(actual);
            cabecera?.classList.toggle('cabecera--solida', actual >= 1);
        };

        if (sinMovimiento) {
            saltar();
            return;
        }

        velo.classList.add('corte--cerrado');
        setTimeout(() => {
            saltar();

            let aclarado = false;
            const aclarar = () => {
                if (aclarado) return;
                aclarado = true;
                velo.classList.remove('corte--cerrado');
                seccion.classList.remove('seccion--entrando');
                void seccion.offsetWidth;   // reinicia la animación si se usa dos veces
                seccion.classList.add('seccion--entrando');
            };
            // dos cuadros: que el navegador pinte la nueva posición antes de aclarar;
            // con respaldo de tiempo por si no hay cuadros (pestaña en segundo plano)
            requestAnimationFrame(() => requestAnimationFrame(aclarar));
            setTimeout(aclarar, 150);
        }, 380);
    }

    // En captura (window): se atiende antes que Lenis, que también escucha los clics en #anclas
    window.addEventListener('click', (evento) => {
        const enlace = evento.target.closest?.('a[data-corte]');
        const hash = enlace?.getAttribute('href') || '';
        const destino = hash.startsWith('#') ? document.querySelector(hash) : null;
        if (!destino || evento.button !== 0 || evento.metaKey || evento.ctrlKey) return;

        evento.preventDefault();
        evento.stopImmediatePropagation();
        irConCorte(destino, hash);
    }, true);

    medir();
    actual = progresoReal();   // si la página carga a mitad del recorrido, empieza ahí sin animar
    dibujar(actual);
    cabecera?.classList.toggle('cabecera--solida', actual >= 1);
})();
