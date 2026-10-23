'use strict';

// Panel de admin → Banner: una imagen o un video corto de fondo en la portada del catálogo.
// Misma lógica que la tienda (banners-controller.js): validar tipo y peso en el navegador,
// vista previa local con URL.createObjectURL y subida con FormData. Aquí es un solo archivo.

document.addEventListener('DOMContentLoaded', () => {
    const panel = document.getElementById('banner-panel');

    if (!panel) {
        return;
    }

    const TIPOS_VALIDOS = ['image/jpeg', 'image/png', 'image/webp', 'video/mp4', 'video/webm'];
    const MAX_MB = Number(panel.dataset.maxMb) || 15;
    const MAX_SEGUNDOS = Number(panel.dataset.maxSegundos) || 30;
    const FOTO_POR_DEFECTO = '/img/landing/auditorio-general.jpg';

    const fondo = document.getElementById('banner-vista-fondo');
    const estado = document.getElementById('banner-estado');
    const zona = document.getElementById('banner-zona');
    const input = document.getElementById('banner-archivo');
    const mensaje = document.getElementById('banner-mensaje');
    const btnPublicar = document.getElementById('banner-publicar');
    const btnCancelar = document.getElementById('banner-cancelar');
    const btnQuitar = document.getElementById('banner-quitar');
    const infoEstado = document.getElementById('banner-info-estado');
    const infoArchivo = document.getElementById('banner-info-archivo');

    let actual = null;          // banner publicado: {tipo, url} o null
    let pendiente = null;       // archivo elegido, aún sin publicar
    let urlLocal = null;        // URL.createObjectURL del archivo pendiente
    let cargado = false;

    const token = () => localStorage.getItem('auth_token');

    function avisar(texto, tipo = '') {
        mensaje.textContent = texto;
        mensaje.className = `banner-mensaje${tipo ? ` ${tipo}` : ''}`;
    }

    // ---- Vista previa ----

    function pintar(url, esVideo) {
        if (esVideo) {
            const video = document.createElement('video');
            video.muted = true;
            video.loop = true;
            video.autoplay = true;
            video.playsInline = true;
            video.src = url;
            fondo.replaceChildren(video);
        } else {
            const imagen = new Image();
            imagen.alt = '';
            imagen.src = url;
            fondo.replaceChildren(imagen);
        }
    }

    function mostrarActual() {
        pintar(actual?.url || FOTO_POR_DEFECTO, actual?.tipo === 'video');
        estado.textContent = actual
            ? (actual.tipo === 'video' ? 'Publicado · video' : 'Publicado · imagen')
            : 'Foto por defecto';
        if (infoEstado) {
            infoEstado.textContent = actual
                ? (actual.tipo === 'video' ? 'Video publicado' : 'Imagen publicada')
                : 'Portada predeterminada';
        }

        if (infoArchivo) {
            infoArchivo.textContent = 'Sin cambios pendientes';
        }

        estado.classList.remove('es-nuevo');
        btnQuitar.hidden = !actual;
    }

    function descartarPendiente() {
        if (urlLocal) {
            URL.revokeObjectURL(urlLocal);
        }

        pendiente = null;
        urlLocal = null;
        input.value = '';
        btnPublicar.hidden = true;
        btnCancelar.hidden = true;
    }

    // ---- Validación en el navegador (el servidor vuelve a validar) ----

    function duracionDelVideo(url) {
        return new Promise((resolver) => {
            const video = document.createElement('video');
            video.preload = 'metadata';
            video.onloadedmetadata = () => resolver(video.duration);
            video.onerror = () => resolver(NaN);
            video.src = url;
        });
    }

    async function elegir(archivo) {
        if (!archivo) {
            return;
        }

        if (!TIPOS_VALIDOS.includes(archivo.type)) {
            avisar(`"${archivo.name}" no es válido: usa JPG, PNG, WEBP, MP4 o WEBM.`, 'error');
            input.value = '';
            return;
        }

        if (archivo.size > MAX_MB * 1024 * 1024) {
            avisar(`"${archivo.name}" pesa ${(archivo.size / 1024 / 1024).toFixed(1)} MB — máximo ${MAX_MB} MB.`, 'error');
            input.value = '';
            return;
        }

        descartarPendiente();
        const url = URL.createObjectURL(archivo);
        const esVideo = archivo.type.startsWith('video/');

        if (esVideo) {
            const segundos = await duracionDelVideo(url);

            if (Number.isFinite(segundos) && segundos > MAX_SEGUNDOS) {
                URL.revokeObjectURL(url);
                avisar(`El video dura ${Math.round(segundos)} s — usa uno de ${MAX_SEGUNDOS} s o menos.`, 'error');
                return;
            }
        }

        pendiente = archivo;
        urlLocal = url;
        pintar(url, esVideo);
        estado.textContent = 'Vista previa · sin publicar';
        if (infoEstado) {
            infoEstado.textContent = 'Pendiente de publicación';
        }

        if (infoArchivo) {
            const tamano = (archivo.size / 1024 / 1024).toFixed(2);
            infoArchivo.textContent =
                archivo.name + ' · ' + tamano + ' MB';
        }

        estado.classList.add('es-nuevo');
        btnPublicar.hidden = false;
        btnCancelar.hidden = false;
        avisar(`${archivo.name} · ${(archivo.size / 1024 / 1024).toFixed(1)} MB. Revisa la vista previa y publícalo.`);
    }

    // ---- Servidor ----

    async function cargar() {
        cargado = true;

        try {
            const respuesta = await fetch('/api/admin/banner', {
                headers: { Accept: 'application/json', Authorization: `Bearer ${token()}` }
            });

            actual = respuesta.ok ? (await respuesta.json()).data : null;
        } catch {
            avisar('No se pudo consultar el banner actual.', 'error');
        }

        if (!pendiente) {
            mostrarActual();
        }
    }

    function ocupado(activo) {
        zona.classList.toggle('ocupada', activo);
        btnPublicar.disabled = activo;
        btnCancelar.disabled = activo;
        btnQuitar.disabled = activo;
    }

    // XMLHttpRequest en lugar de fetch para mostrar el avance de la subida (los videos pesan)
    function publicar() {
        if (!pendiente) {
            return;
        }

        const datos = new FormData();
        datos.append('archivo', pendiente);

        const peticion = new XMLHttpRequest();
        peticion.open('POST', '/api/admin/banner');
        peticion.setRequestHeader('Accept', 'application/json');
        peticion.setRequestHeader('Authorization', `Bearer ${token()}`);
        peticion.responseType = 'json';

        peticion.upload.addEventListener('progress', (evento) => {
            if (evento.lengthComputable) {
                avisar(`Subiendo… ${Math.round((evento.loaded / evento.total) * 100)}%`);
            }
        });

        peticion.addEventListener('load', () => {
            ocupado(false);
            const cuerpo = peticion.response || {};

            if (peticion.status >= 200 && peticion.status < 300) {
                actual = cuerpo.data;
                descartarPendiente();
                mostrarActual();
                avisar('Listo: el catálogo ya muestra el nuevo fondo.', 'exito');
                return;
            }

            const errores = cuerpo.errors ? Object.values(cuerpo.errors)[0] : null;
            avisar(
                peticion.status === 413
                    ? `El archivo pesa demasiado para el servidor (máximo ${MAX_MB} MB).`
                    : (Array.isArray(errores) ? errores[0] : cuerpo.message) || 'No se pudo publicar el banner.',
                'error'
            );
        });

        peticion.addEventListener('error', () => {
            ocupado(false);
            avisar('No se pudo conectar con el servidor.', 'error');
        });

        ocupado(true);
        avisar('Subiendo… 0%');
        peticion.send(datos);
    }

    async function quitar() {
        if (!confirm('¿Quitar el banner? El catálogo volverá a la foto del teatro.')) {
            return;
        }

        ocupado(true);

        try {
            const respuesta = await fetch('/api/admin/banner', {
                method: 'DELETE',
                headers: { Accept: 'application/json', Authorization: `Bearer ${token()}` }
            });

            if (!respuesta.ok) {
                throw new Error();
            }

            actual = null;
            descartarPendiente();
            mostrarActual();
            avisar('Banner quitado: el catálogo usa la foto por defecto.', 'exito');
        } catch {
            avisar('No se pudo quitar el banner.', 'error');
        } finally {
            ocupado(false);
        }
    }

    // ---- Eventos ----

    input.addEventListener('change', () => elegir(input.files[0]));

    ['dragenter', 'dragover'].forEach((tipo) => zona.addEventListener(tipo, (evento) => {
        evento.preventDefault();
        zona.classList.add('arrastrando');
    }));

    ['dragleave', 'drop'].forEach((tipo) => zona.addEventListener(tipo, () => {
        zona.classList.remove('arrastrando');
    }));

    zona.addEventListener('drop', (evento) => {
        evento.preventDefault();
        elegir(evento.dataTransfer.files[0]);
    });

    btnPublicar.addEventListener('click', publicar);

    btnCancelar.addEventListener('click', () => {
        descartarPendiente();
        mostrarActual();
        avisar('');
    });

    btnQuitar.addEventListener('click', quitar);

    // Se consulta la primera vez que se abre la sección
    document.querySelectorAll('.nav-item[data-section="banner"]').forEach((boton) => {
        boton.addEventListener('click', () => {
            if (!cargado) {
                cargar();
            }
        });
    });
});
