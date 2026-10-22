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
