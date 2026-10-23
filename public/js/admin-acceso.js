'use strict';

// Panel → "Control de acceso" (admin y operador): el "portero" escanea el QR de cada boleto con la
// cámara del dispositivo (o pega el código) y ve la tarjeta de verificacion-boleto.js para registrar
// la entrada con un toque. Mientras hay un resultado en pantalla, el escaneo se pausa: así otro QR
// que pase frente a la cámara no reemplaza la tarjeta antes de decidir.
// Lectura del QR: BarcodeDetector del navegador (Chrome/Android) o, si no existe, jsQR (se descarga
// solo en ese caso). La cámara exige https o localhost; si no hay, queda el campo para pegar el código.

document.addEventListener('DOMContentLoaded', () => {
    const panel = document.getElementById('acceso-panel');
    if (!panel || !window.VerificacionBoleto) return;

    const video = document.getElementById('acceso-video');
    const velo = document.getElementById('acceso-velo');
    const estadoCamara = document.getElementById('acceso-camara-estado');
    const botonCamara = document.getElementById('acceso-camara-boton');
    const resultado = document.getElementById('acceso-resultado');
    const siguiente = document.getElementById('acceso-siguiente');
    const manual = document.getElementById('acceso-manual');
    const historial = document.getElementById('acceso-historial');
    const historialVacio = document.getElementById('acceso-historial-vacio');

    const JSQR = 'https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js';
    const JSQR_SRI = 'sha384-b5Ya4Bq3qCyz39m2ISh+4DxjAIljdeFwK/BsXLuj9gugaNwAcj/ia15fxNZL9Nlx';
    const hora = new Intl.DateTimeFormat('es-GT', { timeZone: 'America/Guatemala', hour: 'numeric', minute: '2-digit', second: '2-digit' });

    let flujo = null;          // MediaStream de la cámara
    let lector = null;         // (imagen) => Promise<string|null>
    let pausado = false;       // hay un resultado en pantalla
    let consultando = false;
    let cuadro = null;

    const crear = (etiqueta, clase, texto) => {
        const nodo = document.createElement(etiqueta);
        if (clase) nodo.className = clase;
        if (texto !== undefined) nodo.textContent = texto;
        return nodo;
    };

    function mostrarVelo(texto, conBoton = true) {
        estadoCamara.textContent = texto;
        botonCamara.hidden = !conBoton;
        velo.hidden = false;
    }

    function cargarJsQr() {
        if (window.jsQR) return Promise.resolve();
        return new Promise((listo, fallo) => {
            const script = document.createElement('script');
            script.src = JSQR;
            script.integrity = JSQR_SRI;
            script.crossOrigin = 'anonymous';
            script.onload = listo;
            script.onerror = () => fallo(new Error('No se pudo cargar el lector de QR.'));
            document.head.append(script);
        });
    }

    async function prepararLector() {
        if ('BarcodeDetector' in window) {
            const formatos = await window.BarcodeDetector.getSupportedFormats?.().catch(() => []) ?? [];
            if (formatos.includes('qr_code')) {
                const detector = new window.BarcodeDetector({ formats: ['qr_code'] });
                return async (fuente) => (await detector.detect(fuente))[0]?.rawValue ?? null;
            }
        }
        await cargarJsQr();
        const lienzo = document.createElement('canvas');
        const contexto = lienzo.getContext('2d', { willReadFrequently: true });
        return async (fuente) => {
            const ancho = fuente.videoWidth;
            const alto = fuente.videoHeight;
            if (!ancho || !alto) return null;
            lienzo.width = ancho;
            lienzo.height = alto;
            contexto.drawImage(fuente, 0, 0, ancho, alto);
            return window.jsQR(contexto.getImageData(0, 0, ancho, alto).data, ancho, alto, { inversionAttempts: 'dontInvert' })?.data ?? null;
        };
    }

    async function encenderCamara() {
        if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
            mostrarVelo('La cámara del navegador solo funciona con https o en localhost. Puedes pegar el código abajo, o escanear con la cámara del teléfono: el QR abre la verificación directamente.', false);
            return;
        }
        mostrarVelo('Encendiendo la cámara…', false);
        try {
            lector ??= await prepararLector();
            flujo = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' } }, audio: false });
            video.srcObject = flujo;
            await video.play();
            velo.hidden = true;
            buscar();
        } catch (error) {
            apagarCamara();
            mostrarVelo(error.name === 'NotAllowedError'
                ? 'No diste permiso para usar la cámara. Actívalo en el navegador e inténtalo de nuevo.'
                : (error.message || 'No se pudo encender la cámara.'));
        }
    }

    function apagarCamara() {
        cancelAnimationFrame(cuadro);
        flujo?.getTracks().forEach((pista) => pista.stop());
        flujo = null;
        video.srcObject = null;
    }

    // Unas 6 lecturas por segundo bastan y no calientan el teléfono
    let ultimaLectura = 0;
    function buscar(instante = 0) {
        if (!flujo) return;
        cuadro = requestAnimationFrame(buscar);
        if (pausado || consultando || instante - ultimaLectura < 160 || video.readyState < 2) return;
        ultimaLectura = instante;
        consultando = true;
        lector(video)
            .then((texto) => (texto ? verificar(texto) : null))
            .catch(() => null)
            .finally(() => { consultando = false; });
    }

    function anotar(datos, resultadoFinal) {
        const item = crear('li', `acceso-registro es-${resultadoFinal}`);
        const nombre = datos.encontrado
            ? `${datos.boleto.titular} · ${datos.lugar.localidad}${datos.lugar.numero === null ? '' : ` ${datos.lugar.fila}${datos.lugar.numero}`}`
            : 'Código no reconocido';
        const etiqueta = { entro: 'Entró', rechazado: 'No pasó', pendiente: 'Sin registrar' }[resultadoFinal];
        item.append(crear('span', 'acceso-registro__hora', hora.format(new Date())), crear('span', 'acceso-registro__nombre', nombre), crear('span', 'acceso-registro__etiqueta', etiqueta));
        historial.prepend(item);
        while (historial.children.length > 8) historial.lastChild.remove();
        historialVacio.hidden = true;
        return item;
    }

    async function verificar(texto) {
        pausado = true;
        resultado.replaceChildren(crear('p', 'acceso-espera', 'Verificando…'));
        try {
            const datos = await VerificacionBoleto.consultar(texto);
            if (datos.vista !== 'personal') throw new Error('Tu sesión ya no tiene permiso para registrar entradas.');
            const registro = anotar(datos, datos.puede_entrar ? 'pendiente' : 'rechazado');
            resultado.replaceChildren(VerificacionBoleto.tarjeta(datos, {
                alTerminar: () => {
                    registro.className = 'acceso-registro es-entro';
                    registro.querySelector('.acceso-registro__etiqueta').textContent = 'Entró';
                    // Tras registrar, listo para el siguiente en un momento
                    setTimeout(() => { if (pausado) reanudar(); }, 2500);
                },
            }));
            navigator.vibrate?.(datos.puede_entrar ? 60 : [80, 60, 80]);
        } catch (error) {
            resultado.replaceChildren(crear('p', 'acceso-espera es-error', error.message));
        }
        siguiente.hidden = false;
        siguiente.focus({ preventScroll: true });
    }

    function reanudar() {
        pausado = false;
        siguiente.hidden = true;
        resultado.replaceChildren(crear('p', 'acceso-espera', flujo ? 'Apunta la cámara al siguiente boleto.' : 'El resultado del escaneo aparecerá aquí.'));
    }

    botonCamara.addEventListener('click', encenderCamara);
    siguiente.addEventListener('click', reanudar);
    manual.addEventListener('submit', (evento) => {
        evento.preventDefault();
        const campo = manual.elements.codigo;
        const texto = campo.value.trim();
        if (!texto) return;
        campo.value = '';
        verificar(texto);
    });

    // La cámara se apaga al salir de la sección o al ocultar la pestaña
    document.addEventListener('admin:recargar', (evento) => {
        if (evento.detail?.section === 'acceso') {
            if (!flujo) mostrarVelo('Apunta la cámara al QR del boleto.');
        } else {
            apagarCamara();
        }
    });
    document.addEventListener('visibilitychange', () => {
        if (document.hidden && flujo) {
            apagarCamara();
            mostrarVelo('La cámara se apagó al salir de la página.');
        }
    });
});
