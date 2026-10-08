// Utilidades compartidas por las vistas de autenticación.
// El token se guarda en localStorage (suficiente para pruebas; en producción
// conviene una cookie httpOnly para protegerlo de XSS).

const Auth = {
    claveToken: 'auth_token',

    obtenerToken() {
        return localStorage.getItem(this.claveToken);
    },

    guardarToken(token) {
        localStorage.setItem(this.claveToken, token);
    },

    borrarToken() {
        localStorage.removeItem(this.claveToken);
    },

    // Llama a la API y devuelve { ok, status, data } sin lanzar error por códigos 4xx/5xx.
    async api(ruta, { metodo = 'POST', cuerpo = null, conToken = false } = {}) {
        const headers = { 'Accept': 'application/json' };
        if (cuerpo) headers['Content-Type'] = 'application/json';
        if (conToken) headers['Authorization'] = `Bearer ${this.obtenerToken()}`;

        try {
            const respuesta = await fetch(`/api${ruta}`, {
                method: metodo,
                headers,
                body: cuerpo ? JSON.stringify(cuerpo) : null,
            });
            const data = await respuesta.json().catch(() => ({}));

            // Token rechazado (vencido/revocado) o cuenta bloqueada → la sesión local ya no sirve
            if (conToken && (respuesta.status === 401 || data.error === 'usuario_bloqueado')) {
                this.borrarToken();
            }

            return { ok: respuesta.ok, status: respuesta.status, data };
        } catch {
            return { ok: false, status: 0, data: { message: 'No se pudo conectar con el servidor.' } };
        }
    },

    // Convierte cualquier respuesta de error de la API en un texto para el usuario.
    mensajeDeError({ status, data }) {
        if (status === 429) return 'Demasiados intentos. Espera un minuto e inténtalo de nuevo.';
        if (data.errors) return Object.values(data.errors)[0][0];   // validación (422)
        return data.message || 'Ocurrió un error inesperado.';
    },

    mostrar(elemento, texto, tipo = 'error') {
        elemento.textContent = texto;
        elemento.className = `alerta alerta--${tipo} alerta--visible`;
    },

    ocultar(elemento) {
        elemento.className = 'alerta';
    },

    // Bloquea el botón mientras se espera la respuesta.
    cargando(boton, activo, texto) {
        if (activo) {
            boton.dataset.textoOriginal = boton.textContent;
            boton.textContent = texto;
        } else {
            boton.textContent = boton.dataset.textoOriginal;
        }
        boton.disabled = activo;
    },

    // Convierte las 6 casillas de un contenedor en un campo de código:
    // avanzan solas, Backspace regresa y pegar "123456" las llena todas.
    // Devuelve { valor(), limpiar(), enfocar() }.
    casillasCodigo(contenedor) {
        const casillas = [...contenedor.querySelectorAll('input')];

        casillas.forEach((casilla, i) => {
            casilla.addEventListener('input', () => {
                casilla.value = casilla.value.replace(/\D/g, '');   // solo números
                if (casilla.value && i < casillas.length - 1) casillas[i + 1].focus();
            });

            casilla.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !casilla.value && i > 0) casillas[i - 1].focus();
            });

            casilla.addEventListener('paste', (e) => {
                e.preventDefault();
                const digitos = e.clipboardData.getData('text').replace(/\D/g, '').slice(0, casillas.length);
                digitos.split('').forEach((d, j) => casillas[j].value = d);
                casillas[Math.min(digitos.length, casillas.length - 1)].focus();
            });
        });

        return {
            valor: () => casillas.map((c) => c.value).join(''),
            limpiar: () => casillas.forEach((c) => c.value = ''),
            enfocar: () => casillas[0].focus(),
        };
    },

    // Botón "Mostrar / Ocultar" de los campos de contraseña.
    activarVerPassword() {
        document.querySelectorAll('.password button').forEach((boton) => {
            boton.addEventListener('click', () => {
                const input = boton.previousElementSibling;
                const oculto = input.type === 'password';
                input.type = oculto ? 'text' : 'password';
                boton.textContent = oculto ? 'Ocultar' : 'Mostrar';
            });
        });
    },
};
