'use strict';

// Sesión de Soirée (todos los roles): termina al cerrar sesión, al cerrar el navegador o, como
// máximo, 12 h después de iniciarla (eso último lo vence el servidor).
//
// El token sigue en localStorage (lo leen todas las páginas), pero solo vale mientras quede abierta
// alguna pestaña de Soirée:
//   - cada pestaña tiene un id en sessionStorage (sobrevive a recargas y a navegar dentro de la
//     pestaña; muere al cerrarla) y se anota en una lista compartida con un latido; al cerrarse
//     se borra de la lista (pagehide).
//   - una pestaña NUEVA (sin id) adopta la sesión si hay otra pestaña viva en la lista: es el
//     intercambio entre pestañas (abrir un enlace en otra pestaña no pide iniciar sesión).
//   - si no queda ninguna (se cerró el navegador), el token se descarta y se revoca en el servidor.
// Se carga SIN defer al principio del <head> (partials/sesion), antes que cualquier script que lea
// el token, así ninguna página llega a usar una sesión que ya terminó.
//
// En el panel (/admin…): sin sesión, o si la API responde 401 (la sesión venció), lleva al acceso
// del personal y, al entrar, regresa a la misma página.

(() => {
    const TOKEN = 'auth_token';
    const LISTA = 'soiree_pestanas';
    const ID = 'soiree_pestana';
    const LATIDO_MS = 20000;
    // Chrome despierta las pestañas en segundo plano una vez por minuto: con menos margen, una
    // pestaña nueva creería que la otra (escondida) ya se cerró
    const VIVA_MS = 90000;

    const leer = () => {
        try { return JSON.parse(localStorage.getItem(LISTA)) || {}; } catch { return {}; }
    };
    const escribir = (lista) => {
        try { localStorage.setItem(LISTA, JSON.stringify(lista)); } catch { /* almacenamiento lleno o bloqueado */ }
    };

    let id;
    try {
        const ahora = Date.now();
        const lista = leer();
        Object.keys(lista).forEach((otra) => { if (ahora - lista[otra] > VIVA_MS) delete lista[otra]; });

        id = sessionStorage.getItem(ID);
        if (!id) {
            const token = localStorage.getItem(TOKEN);
            if (token && Object.keys(lista).length === 0) {
                // Navegador cerrado y vuelto a abrir: la sesión anterior ya no vale
                localStorage.removeItem(TOKEN);
                fetch('/api/auth/logout', {
                    method: 'POST',
                    keepalive: true,
                    headers: { Accept: 'application/json', Authorization: `Bearer ${token}` },
                }).catch(() => {});
            }
            id = `${ahora.toString(36)}-${Math.random().toString(36).slice(2, 8)}`;
            sessionStorage.setItem(ID, id);
        }
        lista[id] = ahora;
        escribir(lista);
    } catch {
        return;   // sin almacenamiento (modo muy restringido): no hay sesión que cuidar
    }

    const latir = () => {
        const lista = leer();
        lista[id] = Date.now();
        escribir(lista);
    };
    setInterval(latir, LATIDO_MS);
    document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') latir(); });
    window.addEventListener('pageshow', latir);
    window.addEventListener('pagehide', () => {
        const lista = leer();
        delete lista[id];
        escribir(lista);
    });

    // ---------------- Panel: siempre con sesión del personal ----------------
    const ruta = window.location.pathname;
    const esPanel = /^\/admin(\/|$)/.test(ruta) && !/^\/admin\/(login|olvide-password|restablecer-password)\b/.test(ruta);
    if (!esPanel) return;

    let saliendo = false;
    const irAlAcceso = (mensaje) => {
        if (saliendo) return;
        saliendo = true;
        try { localStorage.removeItem(TOKEN); } catch { /* nada */ }
        const volver = encodeURIComponent(ruta + window.location.search);
        window.location.replace(`/admin/login?volver=${volver}` + (mensaje ? `&tipo=aviso&mensaje=${encodeURIComponent(mensaje)}` : ''));
    };
    window.SesionPanel = { irAlAcceso };

    let token = null;
    try { token = localStorage.getItem(TOKEN); } catch { /* nada */ }
    if (!token) {
        irAlAcceso();
        return;
    }

    // Sesión vencida a mitad del trabajo (12 h): cualquier 401 de la API con token lleva al acceso
    const conToken = (init) => {
        const cabeceras = init?.headers;
        if (!cabeceras) return false;
        const valor = cabeceras instanceof Headers ? cabeceras.get('Authorization') : (cabeceras.Authorization || cabeceras.authorization);
        return typeof valor === 'string' && valor.startsWith('Bearer ');
    };
    const fetchOriginal = window.fetch.bind(window);
    window.fetch = async (recurso, init) => {
        const respuesta = await fetchOriginal(recurso, init);
        if (respuesta.status === 401 && conToken(init)) {
            irAlAcceso('Tu sesión terminó. Inicia sesión de nuevo para continuar.');
        }
        return respuesta;
    };
})();
