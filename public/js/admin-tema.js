'use strict';

// Botón claro/oscuro del panel. El tema se guarda en el navegador (localStorage "tema_panel")
// y un script en el <head> lo aplica antes de dibujar la página (sin destello al recargar).
document.addEventListener('DOMContentLoaded', () => {
    const boton = document.getElementById('btnTema');
    const raiz = document.documentElement;

    if (!boton) {
        return;
    }

    function pintarBoton() {
        const oscuro = raiz.dataset.tema === 'oscuro';
        const texto = oscuro ? 'Cambiar a tema claro' : 'Cambiar a tema oscuro';
        boton.setAttribute('aria-label', texto);
        boton.title = texto;
        boton.setAttribute('aria-pressed', String(oscuro));
    }

    boton.addEventListener('click', () => {
        const nuevo = raiz.dataset.tema === 'oscuro' ? 'claro' : 'oscuro';

        // Transición suave de colores solo al cambiar (no en la carga inicial)
        raiz.classList.add('tema-transicion');
        raiz.dataset.tema = nuevo;
        setTimeout(() => raiz.classList.remove('tema-transicion'), 450);

        try {
            localStorage.setItem('tema_panel', nuevo);
        } catch {
            // sin almacenamiento: el tema dura hasta recargar
        }

        pintarBoton();
    });

    pintarBoton();

    // La pastilla del encabezado dice "Acceso administrador" para todos: al agente operativo
    // (rol organizador) se le muestra "Acceso operador"
    (async () => {
        const pastilla = document.querySelector('.hero .user-pill');
        const token = localStorage.getItem('auth_token');
        if (!pastilla || !token) return;

        try {
            const respuesta = await fetch('/api/auth/perfil', {
                headers: { Accept: 'application/json', Authorization: `Bearer ${token}` }
            });
            const perfil = respuesta.ok ? await respuesta.json() : null;
            if (String(perfil?.rol).toLowerCase() !== 'organizador') return;

            const texto = [...pastilla.childNodes].reverse().find(n => n.nodeType === Node.TEXT_NODE && n.textContent.trim());
            if (texto) texto.textContent = ' Acceso operador ';
        } catch {
            // sin conexión: queda el texto original
        }
    })();
});

// Celular y tableta (≤ 850 px): la barra lateral se vuelve un menú que se despliega desde la
// izquierda con ícono y nombre de cada sección. La barra de arriba dice en qué sección estás.
document.addEventListener('DOMContentLoaded', () => {
    const menu = document.getElementById('menu-lateral');
    const abrir = document.getElementById('btnMenu');
    const cerrar = document.getElementById('btnCerrarMenu');
    const velo = document.getElementById('menuVelo');
    const seccion = document.getElementById('barraSeccion');
    const movil = window.matchMedia('(max-width: 850px)');

    if (!menu || !abrir) {
        return;
    }

    menu.tabIndex = -1;

    function alternar(abierto) {
        document.body.classList.toggle('menu-abierto', abierto);
        abrir.setAttribute('aria-expanded', String(abierto));
        velo.hidden = !abierto;

        if (abierto) {
            // El foco entra al menú (Tab recorre las secciones) sin marcar ninguna
            menu.focus({ preventScroll: true });
        } else if (menu.contains(document.activeElement)) {
            abrir.focus({ preventScroll: true });
        }
    }

    abrir.addEventListener('click', () => alternar(true));
    cerrar.addEventListener('click', () => alternar(false));
    velo.addEventListener('click', () => alternar(false));
    document.addEventListener('keydown', (evento) => {
        if (evento.key === 'Escape' && document.body.classList.contains('menu-abierto')) alternar(false);
    });
    // Elegir una sección cierra el menú (admin.js ya se encarga de mostrarla)
    menu.addEventListener('click', (evento) => {
        if (movil.matches && evento.target.closest('.nav-item')) alternar(false);
    });
    // Al pasar a pantalla ancha (girar la tableta) el menú vuelve a ser la barra fija
    movil.addEventListener('change', () => alternar(false));

    // Nombre de la sección actual en la barra de arriba
    document.addEventListener('admin:recargar', (evento) => {
        const boton = menu.querySelector(`.nav-item[data-section="${evento.detail?.section}"]`);
        seccion.textContent = boton?.querySelector('span')?.textContent.trim() || '';
    });
});
