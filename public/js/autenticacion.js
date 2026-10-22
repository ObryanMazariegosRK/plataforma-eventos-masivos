
// Utilidades compartidas por las vistas de autenticación.
// En producción conviene utilizar cookies httpOnly para los tokens.

const Auth = {
    claveToken: 'auth_token',

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

    redirigirSegunRol(usuario) {
        const rol = String(usuario?.rol ?? '')
            .trim()
            .toLowerCase();

        // Administradores y Agentes Operativos usan el panel.
        if (rol === 'admin' || rol === 'organizador') {
            window.location.replace('/admin');
            return true;
        }

        if (rol === 'cliente') {
            // Respetar la ruta de retorno solo si es interna.
            const volver = new URLSearchParams(
                window.location.search
            ).get('volver') || '';

            const destino = /^\/(?!\/)/.test(volver)
                ? volver
                : '/catalogo';

            window.location.replace(destino);
            return true;
        }

        console.warn('Rol de usuario no reconocido:', rol);
        return false;
    },

    // CONSULTAR SESIÓN ACTUAL

    async entrarConSesion() {
        const respuesta = await this.api('/auth/perfil', {
            metodo: 'GET',
            conToken: true
        });

        return respuesta.ok &&
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
