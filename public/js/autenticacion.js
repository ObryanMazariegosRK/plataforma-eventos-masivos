
// Utilidades compartidas por las vistas de autenticación (tienda: /login…, personal: /admin/login…).
// El token vive en localStorage, pero sesion.js lo descarta al cerrar el navegador; además
// el servidor lo vence a las 12 h (config/autenticacion.php).

const Auth = {
    claveToken: 'auth_token',
    claveVolver: 'volver_tras_login',

    // GESTIÓN DEL TOKEN

    obtenerToken() {
        return localStorage.getItem(this.claveToken);
    },

    guardarToken(token) {
        localStorage.setItem(this.claveToken, token);
    },

    borrarToken() {
        localStorage.removeItem(this.claveToken);
    },

    // REDIRECCIÓN SEGÚN EL ROL

    // ?volver=/eventos/5/asientos → al iniciar sesión se regresa ahí (p. ej. eligiendo asientos).
    // Solo rutas internas ("/algo", nunca "//otro-sitio.com": evita redirecciones a sitios
    // externos) y nunca a las propias páginas de acceso (evita ciclos).
    volverValido(ruta) {
        return typeof ruta === 'string'
            && /^\/(?!\/)/.test(ruta)
            && !/^\/(admin\/)?(login|registro|verificar|olvide-password|restablecer-password|oauth)\b/.test(ruta);
    },

    // En /login y /registro se guarda el ?volver para que sobreviva a los pasos intermedios
    // (ir a Google y volver, o registrarse → verificar el correo). Si se entra al login sin
    // ?volver, se borra lo que hubiera quedado de un intento anterior (el registro no lo borra:
    // se llega a él desde el login con "Regístrate").
    recordarVolver() {
        const pagina = window.location.pathname;
        if (!/^\/(admin\/)?(login|registro)\b/.test(pagina)) return;
        try {
            const volver = new URLSearchParams(window.location.search).get('volver');
            if (this.volverValido(volver)) sessionStorage.setItem(this.claveVolver, volver);
            else if (/^\/(admin\/)?login\b/.test(pagina)) sessionStorage.removeItem(this.claveVolver);
        } catch { /* sin almacenamiento: solo funciona el ?volver directo */ }
    },

    destinoVolver() {
        let volver = new URLSearchParams(window.location.search).get('volver');
        try {
            volver = this.volverValido(volver) ? volver : sessionStorage.getItem(this.claveVolver);
            sessionStorage.removeItem(this.claveVolver);   // se usa una sola vez
        } catch { /* nada */ }
        return this.volverValido(volver) ? volver : null;
    },

    // Inicio de cada rol cuando no hay a dónde volver: el admin, al Dashboard; el operador, al
    // Catálogo de eventos (su vista principal); el cliente, a la cartelera
    inicioDe(rol) {
        return { admin: '/admin', organizador: '/admin?seccion=catalogo' }[rol] || '/catalogo';
    },

    // Tras iniciar sesión: a la página de donde venía (p. ej. sus boletos al tocar una notificación
    // o la sección del panel donde le venció la sesión). Un cliente nunca vuelve al panel.
    redirigirSegunRol(usuario) {
        const rol = String(usuario?.rol ?? '')
            .trim()
            .toLowerCase();

        if (!['admin', 'organizador', 'cliente'].includes(rol)) {
            console.warn('Rol de usuario no reconocido:', rol);
            return false;
        }

        const volver = this.destinoVolver();
        const destino = volver && !(rol === 'cliente' && /^\/admin\b/.test(volver)) ? volver : this.inicioDe(rol);
        window.location.replace(destino);
        return true;
    },

    // CONSULTAR SESIÓN ACTUAL

    // Al abrir un login con una sesión ya abierta: si es de esa misma puerta (personal en
    // /admin/login, cliente en /login) entra directo; si no, se queda en el formulario
    async entrarConSesion(personal = false) {
        const respuesta = await this.api('/auth/perfil', {
            metodo: 'GET',
            conToken: true
        });

        const esPersonal = ['admin', 'organizador'].includes(respuesta.data?.rol);
        return respuesta.ok && esPersonal === personal &&
            this.redirigirSegunRol(respuesta.data);
    },

    // PROCESAR LOGIN

    procesarInicioSesion(data) {
        const token = data?.token;
        const usuario = data?.usuario;

        if (!token || !usuario?.rol) {
            console.error(
                'La respuesta del login no contiene token o rol.'
            );
            return false;
        }

        const rol = String(usuario.rol)
            .trim()
            .toLowerCase();

        if (!['admin', 'organizador', 'cliente'].includes(rol)) {
            console.warn('Rol no reconocido:', rol);
            return false;
        }

        this.guardarToken(token);
        return this.redirigirSegunRol(usuario);
    },

    // PETICIONES A LA API

    async api(
        ruta,
        {
            metodo = 'POST',
            cuerpo = null,
            conToken = false
        } = {}
    ) {
        const headers = {
            Accept: 'application/json'
        };

        if (cuerpo) {
            headers['Content-Type'] = 'application/json';
        }

        if (conToken) {
            const token = this.obtenerToken();

            if (token) {
                headers['Authorization'] = `Bearer ${token}`;
            }
        }

        try {
            const respuesta = await fetch(`/api${ruta}`, {
                method: metodo,
                headers,
                body: cuerpo ? JSON.stringify(cuerpo) : null
            });

            const data = await respuesta
                .json()
                .catch(() => ({}));

            // Borrar token cuando la sesión deja de ser válida.
            if (
                conToken &&
                (
                    respuesta.status === 401 ||
                    data.error === 'usuario_bloqueado'
                )
            ) {
                this.borrarToken();
            }

            return {
                ok: respuesta.ok,
                status: respuesta.status,
                data
            };

        } catch {
            return {
                ok: false,
                status: 0,
                data: {
                    message: 'No se pudo conectar con el servidor.'
                }
            };
        }
    },

    // PAGO: IR DIRECTAMENTE A LA PASARELA

    async irAPagar(reservaId) {
        const pasarelas = await this.api('/pasarelas', {
            metodo: 'GET'
        });

        if (
            !pasarelas.ok ||
            !Array.isArray(pasarelas.data) ||
            pasarelas.data.length !== 1
        ) {
            window.location.href = `/pagar?reserva=${reservaId}`;
            return null;
        }

        const respuesta = await this.api('/pagos', {
            conToken: true,
            cuerpo: {
                reserva_id: Number(reservaId),
                pasarela: pasarelas.data[0].codigo
            }
        });

        if (respuesta.ok) {
            window.location.href = respuesta.data.url_aprobacion;
            return null;
        }

        return respuesta;
    },

    // MENSAJES DE ERROR

    mensajeDeError({ status, data }) {
        if (status === 429) {
            return 'Demasiados intentos. Espera un minuto e inténtalo de nuevo.';
        }

        if (data?.errors) {
            const primerError = Object.values(data.errors)[0];

            return Array.isArray(primerError)
                ? primerError[0]
                : String(primerError);
        }

        return data?.message || 'Ocurrió un error inesperado.';
    },

    // ALERTAS

    mostrar(elemento, texto, tipo = 'error') {
        elemento.textContent = texto;
        elemento.className =
            `alerta alerta--${tipo} alerta--visible`;
    },

    ocultar(elemento) {
        elemento.className = 'alerta';
    },

    // ESTADO DE CARGA DE BOTONES

    cargando(boton, activo, texto) {
        if (activo) {
            boton.dataset.textoOriginal = boton.textContent;
            boton.textContent = texto;
        } else {
            boton.textContent =
                boton.dataset.textoOriginal ?? boton.textContent;
        }

        boton.disabled = activo;
    },

    // CASILLAS DEL CÓDIGO DE VERIFICACIÓN

    casillasCodigo(contenedor) {
        const casillas = [
            ...contenedor.querySelectorAll('input')
        ];

        casillas.forEach((casilla, i) => {
            casilla.addEventListener('input', () => {
                casilla.value = casilla.value.replace(/\D/g, '');

                if (
                    casilla.value &&
                    i < casillas.length - 1
                ) {
                    casillas[i + 1].focus();
                }
            });

            casilla.addEventListener('keydown', (e) => {
                if (
                    e.key === 'Backspace' &&
                    !casilla.value &&
                    i > 0
                ) {
                    casillas[i - 1].focus();
                }
            });

            casilla.addEventListener('paste', (e) => {
                e.preventDefault();

                const digitos = e.clipboardData
                    .getData('text')
                    .replace(/\D/g, '')
                    .slice(0, casillas.length);

                digitos.split('').forEach((digito, j) => {
                    casillas[j].value = digito;
                });

                if (casillas.length > 0) {
                    casillas[
                        Math.min(
                            digitos.length,
                            casillas.length - 1
                        )
                    ].focus();
                }
            });
        });

        return {
            valor: () => casillas
                .map(casilla => casilla.value)
                .join(''),

            limpiar: () => casillas.forEach(
                casilla => casilla.value = ''
            ),

            enfocar: () => casillas[0]?.focus()
        };
    },

    // MOSTRAR / OCULTAR CONTRASEÑAS

    activarVerPassword() {
        document
            .querySelectorAll('.password button')
            .forEach((boton) => {
                boton.addEventListener('click', () => {
                    const input = boton.previousElementSibling;

                    if (!input) return;

                    const oculto = input.type === 'password';

                    input.type = oculto ? 'text' : 'password';

                    boton.textContent = oculto
                        ? 'Ocultar'
                        : 'Mostrar';
                });
            });
    }
};

Auth.recordarVolver();
