(() => {
    'use strict';

    // =====================================================
    // SOIRÉE - PANEL ADMINISTRATIVO
    // =====================================================

    const $ = id => document.getElementById(id);

    const titles = {
        resumen: [
            'Vista general',
            'Resumen de tu plataforma y sus operaciones.'
        ],
        usuarios: [
            'Gestión de usuarios',
            'Consulta los usuarios registrados y sus roles.'
        ],
        eventos: [
            'Gestión de eventos',
            'Crea, edita y administra eventos de Soirée.'
        ],
        reservas: [
            'Gestión de reservas',
            'Consulta las reservas registradas.'
        ],
        pagos: [
            'Registro de pagos',
            'Consulta los pagos y sus estados.'
        ],
        catalogo: [
            'Catálogo de eventos',
            'Busca entre todos los eventos por nombre, estado, recinto o fecha.'
        ],
        banner: [
            'Banner del catálogo',
            'Imagen o video de fondo de la portada del catálogo.'
        ],
        reportes: [
            'Centro de reportes',
            'Exporta información de la plataforma.'
        ],
        notificaciones: [
            'Notificaciones',
            'Reservas, pagos y boletos registrados en Soirée.'
        ],
        bitacora: [
            'Bitácora',
            'Quién hizo qué y cuándo: sesiones, compras y cambios en el panel.'
        ],
        configuracion: [
            'Configuración',
            'Próximamente: herramientas administrativas.'
        ]
    };

    const columns = {
        usuarios: [
            ['id', 'ID'],
            ['nombre', 'Nombre'],
            ['apellido', 'Apellido'],
            ['email', 'Correo'],
            ['rol', 'Rol'],
            ['estado', 'Estado']
        ],
        eventos: [
            ['id', 'ID'],
            ['nombre', 'Evento'],
            ['fecha_evento', 'Fecha'],
            ['estado', 'Estado']
        ],
        reservas: [
            ['id', 'ID'],
            ['evento', 'Evento'],
            ['estado', 'Estado'],
            ['total', 'Total (Q)'],
            ['created_at', 'Registro']
        ],
        pagos: [
            ['id', 'ID'],
            ['reserva_id', 'Reserva'],
            ['monto', 'Monto (Q)'],
            ['estado', 'Estado'],
            ['created_at', 'Registro']
        ]
    };

    const pendingSections = new Set([
        'configuracion'
    ]);

    // El rol se verifica con /api/auth/perfil antes de habilitar la navegación.
    let currentRole = null;
    let currentProfile = null;
    const operativeSections = new Set([
        'eventos', 'catalogo', 'banner', 'configuracion'
    ]);

    function canAccessSection(name) {
        if (currentRole === 'admin') return true;
        return currentRole === 'organizador' && operativeSections.has(name);
    }

    let section = 'resumen';
    let page = 1;
    let query = '';
    let requestVersion = 0;
    let editingEventId = null;
    let editingPublished = false;
    let savingEvent = false;

    // =====================================================
    // UTILIDADES
    // =====================================================

    function token() {
        return localStorage.getItem('auth_token');
    }

    async function loadCurrentUser() {
        const accessToken = token();
        if (!accessToken) {
            throw new Error('Debes iniciar sesión para acceder al panel.');
        }

        const response = await fetch('/api/auth/perfil', {
            headers: {
                Accept: 'application/json',
                Authorization: 'Bearer ' + accessToken
            }
        });

        if (response.status === 401) {
            throw new Error('Tu sesión ha caducado. Inicia sesión nuevamente.');
        }
        if (!response.ok) {
            throw new Error('No fue posible verificar el perfil de tu cuenta.');
        }

        // El endpoint devuelve directamente el usuario, incluido su campo rol.
        const profile = await response.json();
        if (!['admin', 'organizador'].includes(profile?.rol)) {
            throw new Error('Tu cuenta no tiene acceso al panel operativo.');
        }

        currentRole = profile.rol;
        currentProfile = profile;
        renderConfiguracion();
        document.querySelectorAll('#nav [data-section]').forEach(item => {
            item.hidden = !canAccessSection(item.dataset.section);
        });

        const search = $('global-search');
        if (search) {
            const isAdmin = currentRole === 'admin';
            search.disabled = !isAdmin;
            const searchArea = search.closest('.top-search');
            if (searchArea) searchArea.hidden = !isAdmin;
        }

        const userLabel = document.querySelector('.utility-person > span:last-child');
        if (userLabel) {
            userLabel.textContent = currentRole === 'organizador'
                ? 'Agente Operativo'
                : 'Administrador';
        }
        const sessionLabel = document.querySelector('.top-online');
        if (sessionLabel) {
            const indicator = sessionLabel.querySelector('.online-indicator');
            sessionLabel.textContent = currentRole === 'organizador'
                ? 'SESIÓN OPERATIVA'
                : 'SESIÓN ADMIN';
            if (indicator) sessionLabel.prepend(indicator);
        }

        llenarFormularioPerfil(profile);
        return profile;
    }


    function renderConfiguracion() {
        if (!currentProfile) return;

        const p = currentProfile;

        const nombre = [p.nombre, p.apellido]
            .filter(Boolean)
            .join(' ') || 'Sin nombre registrado';

        const valores = {
            'so-config-nombre': nombre,
            'so-config-correo': p.email || p.correo || 'No disponible',
            'so-config-rol': p.rol || 'No disponible',
            'so-config-estado': p.estado || 'No disponible',
            'so-config-id': p.id ?? 'No disponible'
        };

        Object.entries(valores).forEach(([id, valor]) => {
            const nodo = $(id);
            if (nodo) nodo.textContent = String(valor);
        });

        const iniciales = nombre
            .split(/\\s+/)
            .slice(0, 2)
            .map(parte => parte.charAt(0).toUpperCase())
            .join('');

        const avatar = $('so-config-iniciales');
        if (avatar) avatar.textContent = iniciales || 'US';
    }

    function el(tag, value, className) {
        const node = document.createElement(tag);

        if (value !== undefined) {
            node.textContent = String(value);
        }

        if (className) {
            node.className = className;
        }

        return node;
    }

    function show(id, visible) {
        const node = $(id);

        if (node) {
            node.hidden = !visible;
        }
    }

    function feedback(message, error = false) {
        const node = $('feedback');
        if (!node) return;

        node.textContent = message;
        node.className = 'feedback' + (error ? ' error' : '');
        node.hidden = false;
    }

    function clearFeedback() {
        show('feedback', false);
    }

    function formFeedback(message, error = false) {
        const node = $('evento-form-feedback');
        if (!node) return;

        node.textContent = message;
        node.className =
            'evento-form-feedback' + (error ? ' error' : '');
        node.hidden = false;
    }

    function clearFormFeedback() {
        show('evento-form-feedback', false);
    }

    function safeValue(value) {
        return value === null || value === undefined
            ? '—'
            : String(value);
    }

    // =====================================================
    // PETICIONES AUTENTICADAS
    // =====================================================

    async function api(path, options = {}) {
        const accessToken = token();

        if (!accessToken) {
            throw new Error(
                'Inicia sesión para continuar.'
            );
        }

        const headers = {
            Accept: 'application/json',
            Authorization: 'Bearer ' + accessToken,
            ...(options.headers || {})
        };

        if (options.body && !(options.body instanceof FormData)) {
            headers['Content-Type'] = 'application/json';
        }

        const response = await fetch('/api/admin/' + path, {
            ...options,
            headers
        });

        if (response.status === 401) {
            throw new Error(
                'Tu sesión ha caducado. Inicia sesión nuevamente.'
            );
        }

        if (response.status === 403) {
            throw new Error(
                'No tienes permisos para realizar esta acción.'
            );
        }

        if (!response.ok) {
            let message =
                'Error del servidor (' + response.status + ').';

            try {
                const data = await response.json();

                if (data.errors) {
                    const first = Object.values(data.errors)
                        .flat()[0];

                    if (first) {
                        message = String(first);
                    }
                } else if (data.message) {
                    message = String(data.message);
                }
            } catch (_) {
                // Conservar el mensaje genérico.
            }

            throw new Error(message);
        }

        return response;
    }

    // =====================================================
    // ESTADÍSTICAS DEL RESUMEN
    // =====================================================

    function metric(label, value, description, icon, color = '') {
        const card = el(
            'div',
            undefined,
            'metric' + (color ? ' ' + color : '')
        );

        const iconElement = el('span', icon, 'metric-icon');
        iconElement.setAttribute('aria-hidden', 'true');

        const copy = el('div', undefined, 'metric-copy');

        copy.append(
            el('small', label),
            el('strong', value),
            el('span', description, 'unit')
        );

        card.append(iconElement, copy);
        return card;
    }

    function bars(target, rows) {
        const root = $(target);
        if (!root) return;

        root.replaceChildren();

        if (!Array.isArray(rows) || !rows.length) {
            root.append(
                el(
                    'p',
                    'Todavía no hay datos registrados.',
                    'muted'
                )
            );
            return;
        }

        const total = rows.reduce(
            (sum, item) => sum + Number(item.total || 0),
            0
        );

        rows.forEach(item => {
            const wrapper = el('div', undefined, 'bar-row');
            const label = el('div', undefined, 'bar-label');

            label.append(
                el('span', item.etiqueta || item.estado),
                el('b', item.total)
            );

            const track = el('div', undefined, 'track');
            const fill = el('div', undefined, 'fill');

            const percentage = total > 0
                ? Number(item.total || 0) / total * 100
                : 0;

            fill.style.width =
                Math.max(0, Math.min(100, percentage)) + '%';

            track.append(fill);
            wrapper.append(label, track);
            root.append(wrapper);
        });
    }

    async function loadSummary() {
        const response = await api('resumen');
        const data = await response.json();
        const totals = data.totales;

        const currency = new Intl.NumberFormat('es-GT', {
            style: 'currency',
            currency: 'GTQ'
        });

        $('summary').replaceChildren(
            metric(
                'Usuarios registrados',
                totals.usuarios,
                'Registros totales',
                '♙'
            ),
            metric(
                'Operadores',
                totals.operadores,
                'Agentes que gestionan eventos',
                '♧',
                'blue'
            ),
            metric(
                'Eventos',
                totals.eventos,
                'Eventos registrados',
                '▦',
                'coral'
            ),
            metric(
                'Reservas',
                totals.reservas,
                'Reservas totales',
                '▤'
            ),
            metric(
                'Pagos',
                totals.pagos,
                (totals.pagos_aprobados || 0) + ' aprobados',
                '▣',
                'blue'
            ),
            metric(
                'Monto de pagos',
                currency.format(
                    Number(totals.monto_pagos_aprobados || 0)
                ),
                'Solo pagos aprobados',
                '◈',
                'orange'
            )
        );

        // Los estados de reservas y de eventos ahora viven en Reservas y en el Catálogo
        return data;
    }

    // Sección Reservas: tarjeta "Estados de las reservas" encima de la tabla
    async function loadReservationStates() {
        try {
            const response = await api('resumen');
            const data = await response.json();

            if (section !== 'reservas') return;

            bars('reservation-bars', data.reservas_por_estado);
            $('reservas-total').textContent = 'Total: ' + data.totales.reservas;
        } catch {
            show('reservas-estados', false);
        }
    }

    // =====================================================
    // TABLAS
    // =====================================================

    function formatCell(field, value) {
        if (!value) {
            return safeValue(value);
        }

        if (
            field === 'fecha_evento' ||
            field === 'created_at'
        ) {
            const date = new Date(value);

            if (!Number.isNaN(date.getTime())) {
                return date.toLocaleString('es-GT', {
                    dateStyle: 'medium',
                    timeStyle: 'short'
                });
            }
        }

        return safeValue(value);
    }

    function actionButton(label, className, callback) {
        const button = el('button', label, className);
        button.type = 'button';
        button.addEventListener('click', callback);
        return button;
    }


    // =====================================================
    // ADMINISTRACION DE USUARIOS
    // =====================================================


    function renderUserActions(item) {
        const cell = el('td');
        const group = el('div', undefined, 'eventos-row-actions');

        group.append(
            actionButton('Ver', 'evento-action', () => viewUser(item.id)),
            actionButton('Editar', 'evento-action', () => openUserForm(item.id)),
            actionButton('Cambiar rol', 'evento-action', () => changeUserRole(item)),
            actionButton(
                item.estado === 'activo' ? 'Bloquear' : 'Activar',
                'evento-action',
                () => changeUserStatus(item)
            ),
            actionButton('Eliminar', 'evento-action', () => deleteUser(item))
        );

        cell.append(group);
        return cell;
    }


    async function changeUserRole(item) {
        if (!canAccessSection('usuarios')) return;

        const roles = [
            {
                value: 'cliente',
                title: 'Cliente',
                detail: 'Acceso al catálogo, compras y reservas.'
            },
            {
                value: 'organizador',
                title: 'Agente Operativo',
                detail: 'Gestión de eventos, banner y funciones operativas.'
            },
            {
                value: 'admin',
                title: 'Administrador',
                detail: 'Acceso completo a la administración de Soirée.'
            }
        ];

        const anterior = $('so-role-dialog');
        if (anterior) anterior.remove();

        const dialog = document.createElement('dialog');
        dialog.id = 'so-role-dialog';
        dialog.className = 'so-role-dialog';

        const header = el('div', undefined, 'so-role-header');
        header.append(
            el('span', 'SOIRÉE · ADMINISTRACIÓN', 'so-role-eyebrow'),
            el('h2', 'Cambiar rol de usuario'),
            el('p', 'Selecciona los permisos que tendrá esta cuenta.')
        );

        const identity = el('div', undefined, 'so-role-identity');
        identity.append(
            el('strong', `${item.nombre} ${item.apellido}`),
            el('span', item.email)
        );

        const form = document.createElement('form');
        form.className = 'so-role-form';

        const options = el('div', undefined, 'so-role-options');

        for (const role of roles) {
            const label = document.createElement('label');
            label.className = 'so-role-option';

            const radio = document.createElement('input');
            radio.type = 'radio';
            radio.name = 'nuevo_rol';
            radio.value = role.value;
            radio.checked = item.rol === role.value;

            const content = el('span', undefined, 'so-role-option-copy');
            content.append(
                el('strong', role.title),
                el('small', role.detail)
            );

            label.append(radio, content);
            options.append(label);
        }

        const message = el('p', '', 'so-role-message');
        message.setAttribute('role', 'alert');
        message.hidden = true;

        const footer = el('div', undefined, 'so-role-footer');

        const cancel = actionButton(
            'Cancelar',
            'so-role-cancel',
            () => dialog.close()
        );

        const save = document.createElement('button');
        save.type = 'submit';
        save.className = 'so-role-save';
        save.textContent = 'Guardar cambio';

        footer.append(cancel, save);
        form.append(options, message, footer);
        dialog.append(header, identity, form);
        document.body.append(dialog);

        dialog.addEventListener('close', () => dialog.remove(), {
            once: true
        });

        dialog.addEventListener('click', event => {
            if (event.target === dialog) dialog.close();
        });

        form.addEventListener('submit', async event => {
            event.preventDefault();

            const seleccionado = form.querySelector(
                'input[name="nuevo_rol"]:checked'
            );

            if (!seleccionado) return;

            const rol = seleccionado.value;

            if (rol === item.rol) {
                dialog.close();
                return;
            }

            const titulo = roles.find(r => r.value === rol)?.title;

            if (!window.confirm(
                `¿Confirmas asignar el rol ${titulo} a ${item.email}?\n\n` +
                'Las sesiones actuales de esta cuenta serán cerradas.'
            )) {
                return;
            }

            save.disabled = true;
            cancel.disabled = true;
            save.textContent = 'Guardando...';
            message.hidden = true;

            try {
                const response = await api(
                    'usuarios/' + item.id + '/rol',
                    {
                        method: 'PATCH',
                        body: JSON.stringify({ rol })
                    }
                );

                const result = await response.json();

                dialog.close();
                await load();

                feedback(
                    result.message || 'Rol actualizado correctamente.'
                );

            } catch (error) {
                message.textContent = error.message;
                message.hidden = false;

            } finally {
                save.disabled = false;
                cancel.disabled = false;
                save.textContent = 'Guardar cambio';
            }
        });

        dialog.showModal();
    }

    async function changeUserStatus(item) {
        if (!canAccessSection('usuarios')) return;

        const estado = item.estado === 'activo'
            ? 'bloqueado'
            : 'activo';

        if (!window.confirm(
            '¿Deseas cambiar la cuenta ' + item.email +
            ' al estado ' + estado + '?'
        )) return;

        try {
            const response = await api('usuarios/' + item.id + '/estado', {
                method: 'PATCH',
                body: JSON.stringify({ estado })
            });

            const result = await response.json();
            await load();

            feedback(result.message || 'Estado actualizado correctamente.');
        } catch (error) {
            feedback(error.message, true);
        }
    }

    async function deleteUser(item) {
        if (!canAccessSection('usuarios')) return;

        if (!window.confirm(
            '¿Eliminar la cuenta ' + item.email + '?' +
            '\\n\\nSe conservará su historial mediante eliminación lógica.'
        )) return;

        try {
            const response = await api('usuarios/' + item.id, {
                method: 'DELETE'
            });

            const result = await response.json();
            await load();

            feedback(result.message || 'Usuario eliminado correctamente.');
        } catch (error) {
            feedback(error.message, true);
        }
    }


    async function viewUser(id) {
        if (!canAccessSection('usuarios')) return;

        try {
            const response = await api('usuarios/' + id);
            const result = await response.json();
            const user = result.data;

            if (!user) {
                throw new Error('No se encontró el usuario.');
            }

            const anterior = $('so-user-detail-dialog');
            if (anterior) anterior.remove();

            const dialog = document.createElement('dialog');
            dialog.id = 'so-user-detail-dialog';
            dialog.className = 'so-role-dialog';

            const header = el('div', undefined, 'so-role-header');

            header.append(
                el('span', 'SOIRÉE · ADMINISTRACIÓN', 'so-role-eyebrow'),
                el('h2', 'Información del usuario'),
                el('p', 'Consulta los datos y permisos de esta cuenta.')
            );

            const identity = el('div', undefined, 'so-role-identity');

            const nombre = [user.nombre, user.apellido]
                .filter(Boolean)
                .join(' ');

            identity.append(
                el('strong', nombre || 'Usuario sin nombre'),
                el('span', user.email || 'Correo no disponible')
            );

            const roles = {
                admin: 'Administrador',
                organizador: 'Agente Operativo',
                cliente: 'Cliente'
            };

            const estados = {
                activo: 'Activo',
                bloqueado: 'Bloqueado'
            };

            const fecha = value => {
                if (!value) return 'No disponible';

                const date = new Date(value);

                return Number.isNaN(date.getTime())
                    ? 'No disponible'
                    : date.toLocaleString('es-GT', {
                        dateStyle: 'medium',
                        timeStyle: 'short'
                    });
            };

            const details = el('div', undefined, 'so-user-detail-list');

            const addDetail = (label, value) => {
                const row = el('div', undefined, 'so-user-detail-row');

                row.append(
                    el('span', label),
                    el('strong', String(value ?? 'No disponible'))
                );

                details.append(row);
            };

            addDetail('Identificador', '#' + user.id);
            addDetail('Nombre completo', nombre);
            addDetail('Correo electrónico', user.email);
            addDetail('Teléfono', user.telefono || 'No registrado');
            addDetail('Rol asignado', roles[user.rol] || user.rol);
            addDetail('Estado', estados[user.estado] || user.estado);
            addDetail('Fecha de registro', fecha(user.created_at));
            addDetail('Última actualización', fecha(user.updated_at));

            const footer = el('div', undefined, 'so-role-footer');

            const close = actionButton(
                'Cerrar',
                'so-role-cancel',
                () => dialog.close()
            );

            const edit = actionButton(
                'Editar usuario',
                'so-role-save',
                () => {
                    dialog.close();
                    openUserForm(user.id);
                }
            );

            footer.append(close, edit);

            dialog.append(header, identity, details, footer);
            document.body.append(dialog);

            dialog.addEventListener(
                'close',
                () => dialog.remove(),
                { once: true }
            );

            dialog.addEventListener('click', event => {
                if (event.target === dialog) {
                    dialog.close();
                }
            });

            dialog.showModal();

        } catch (error) {
            feedback(error.message, true);
        }
    }

    function ensureUserDialog() {
        let dialog = $('so-user-dialog');
        if (dialog) return dialog;

        dialog = document.createElement('dialog');
        dialog.id = 'so-user-dialog';
        dialog.className = 'so-event-dialog';
        dialog.style.maxWidth = '540px';
        dialog.style.width = 'calc(100% - 32px)';
        dialog.style.padding = '24px';

        document.body.append(dialog);
        return dialog;
    }

    async function openUserForm(id = null) {
        if (!canAccessSection('usuarios')) return;

        try {
            let user = null;

            if (id !== null) {
                const result = await (await api('usuarios/' + id)).json();
                user = result.data;
            }

            const dialog = ensureUserDialog();
            dialog.replaceChildren();

            const title = el('h2', user ? 'Editar usuario' : 'Nuevo usuario');
            const form = document.createElement('form');
            form.style.display = 'grid';
            form.style.gap = '12px';

            const field = (name, label, type = 'text', value = '') => {
                const wrapper = document.createElement('label');
                wrapper.style.display = 'grid';
                wrapper.style.gap = '5px';

                wrapper.append(el('span', label));

                const input = document.createElement('input');
                input.name = name;
                input.type = type;
                input.value = value ?? '';
                input.required = !['telefono'].includes(name);
                input.style.width = '100%';
                input.style.padding = '10px';

                if (name === 'password' || name === 'password_confirmation') {
                    input.minLength = 8;
                }

                wrapper.append(input);
                form.append(wrapper);
                return input;
            };

            field('nombre', 'Nombre', 'text', user?.nombre);
            field('apellido', 'Apellido', 'text', user?.apellido);
            field('email', 'Correo electrónico', 'email', user?.email);
            field('telefono', 'Teléfono', 'tel', user?.telefono);

            if (!user) {
                field('password', 'Contraseña', 'password');
                field('password_confirmation', 'Confirmar contraseña', 'password');

                const wrapper = document.createElement('label');
                wrapper.append(el('span', 'Rol'));

                const select = document.createElement('select');
                select.name = 'rol';
                select.required = true;
                select.style.width = '100%';
                select.style.padding = '10px';

                [
                    ['cliente', 'Cliente'],
                    ['organizador', 'Agente Operativo'],
                    ['admin', 'Administrador']
                ].forEach(([value, label]) => {
                    const option = document.createElement('option');
                    option.value = value;
                    option.textContent = label;
                    select.append(option);
                });

                wrapper.append(select);
                form.append(wrapper);
            }

            const message = el('p', '');
            message.setAttribute('role', 'alert');
            form.append(message);

            const buttons = el('div');
            buttons.style.display = 'flex';
            buttons.style.gap = '10px';

            const cancel = actionButton('Cancelar', 'evento-action', () => {
                dialog.close();
            });

            const save = actionButton('Guardar', 'primary', () => {
                form.requestSubmit();
            });

            buttons.append(cancel, save);
            form.append(buttons);
            dialog.append(title, form);

            form.addEventListener('submit', async event => {
                event.preventDefault();
                if (!form.reportValidity()) return;

                const payload = Object.fromEntries(new FormData(form));

                save.disabled = true;
                message.textContent = 'Guardando...';

                try {
                    const response = await api(
                        user ? 'usuarios/' + user.id : 'usuarios',
                        {
                            method: user ? 'PUT' : 'POST',
                            body: JSON.stringify(payload)
                        }
                    );

                    const result = await response.json();
                    dialog.close();
                    await load();
                    feedback(result.message || 'Usuario guardado correctamente.');
                } catch (error) {
                    message.textContent = error.message;
                } finally {
                    save.disabled = false;
                }
            });

            dialog.showModal();
        } catch (error) {
            feedback(error.message, true);
        }
    }

    async function deleteEvent(id) {
        try {
            const evento = await fetchEvent(id);

            if (evento.estado !== 'borrador') {
                feedback(
                    'Solo se pueden eliminar eventos en borrador.',
                    true
                );
                return;
            }

            const confirmado = window.confirm(
                '¿Eliminar el evento "' + evento.nombre + '"?\\n\\n' +
                'Se realizará una eliminación lógica.'
            );

            if (!confirmado) return;

            const response = await api(
                'eventos/' + encodeURIComponent(id),
                { method: 'DELETE' }
            );

            const result = await response.json();

            await load();

            feedback(
                result.message ||
                'Evento eliminado correctamente.'
            );
        } catch (error) {
            feedback(error.message, true);
        }
    }

    // =====================================================
    // CANCELACIÓN DE EVENTOS Y REEMBOLSOS
    // Utiliza la API existente: POST /api/eventos/{id}/cancelar
    // =====================================================

    async function cancelEvent(id) {
        if (currentRole !== 'admin') {
            feedback('Solo el Administrador puede cancelar eventos.', true);
            return;
        }

        try {
            const evento = await fetchEvent(id);

            if (!['publicado', 'agotado'].includes(evento.estado)) {
                feedback('Este evento no admite cancelación.', true);
                return;
            }

            const dialog = document.createElement('dialog');
            dialog.className = 'so-cancel-dialog';

            const title = el('h2', 'Cancelar evento');
            const identity = el(
                'p',
                (evento.nombre || 'Evento') + ' · #' + evento.id,
                'so-cancel-event-name'
            );

            const warning = el(
                'p',
                'Esta operación cancelará el evento, anulará los boletos ' +
                'emitidos correspondientes y preparará solicitudes de ' +
                'reembolso cuando existan pagos aprobados. ' +
                'Los reembolsos no se pagan automáticamente.',
                'so-cancel-warning'
            );

            const label = document.createElement('label');
            label.className = 'so-cancel-label';
            label.textContent = 'Escribe CANCELAR para confirmar';

            const input = document.createElement('input');
            input.type = 'text';
            input.placeholder = 'CANCELAR';
            input.autocomplete = 'off';
            input.setAttribute('aria-label', 'Confirmar cancelación');
            label.append(input);

            const message = el('p', '', 'so-cancel-message');
            message.setAttribute('role', 'alert');
            message.hidden = true;

            const buttons = el('div', undefined, 'so-cancel-actions');

            const volver = actionButton(
                'Volver',
                'evento-action',
                () => dialog.close()
            );

            const confirmar = document.createElement('button');
            confirmar.type = 'button';
            confirmar.className = 'so-cancel-confirm';
            confirmar.textContent = 'Confirmar cancelación';
            confirmar.disabled = true;

            input.addEventListener('input', () => {
                confirmar.disabled = input.value.trim() !== 'CANCELAR';
            });

            confirmar.addEventListener('click', async () => {
                if (input.value.trim() !== 'CANCELAR') return;

                const accessToken = token();

                if (!accessToken) {
                    message.textContent = 'Tu sesión ha caducado.';
                    message.hidden = false;
                    return;
                }

                confirmar.disabled = true;
                volver.disabled = true;
                input.disabled = true;
                confirmar.textContent = 'Cancelando...';
                message.hidden = true;

                try {
                    const response = await fetch(
                        '/api/eventos/' +
                            encodeURIComponent(id) +
                            '/cancelar',
                        {
                            method: 'POST',
                            headers: {
                                Accept: 'application/json',
                                Authorization: 'Bearer ' + accessToken
                            }
                        }
                    );

                    const data = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        throw new Error(
                            data.mensaje ||
                            data.message ||
                            'No fue posible cancelar el evento (' +
                            response.status + ').'
                        );
                    }

                    const resultado = data.resultado || {};

                    dialog.close();
                    await load();

                    feedback(
                        (data.mensaje || 'Evento cancelado.') +
                        ' Boletos anulados: ' +
                        (resultado.boletos_anulados ?? 0) +
                        '. Solicitudes de reembolso creadas: ' +
                        (resultado.reembolsos_creados ?? 0) +
                        '. Monto preparado: Q' +
                        Number(resultado.monto_preparado ?? 0).toFixed(2)
                    );
                } catch (error) {
                    message.textContent = error.message;
                    message.hidden = false;
                    input.disabled = false;
                    volver.disabled = false;
                    confirmar.textContent = 'Confirmar cancelación';
                    confirmar.disabled =
                        input.value.trim() !== 'CANCELAR';
                }
            });

            buttons.append(volver, confirmar);
            dialog.append(title, identity, warning, label, message, buttons);
            document.body.append(dialog);

            dialog.addEventListener('close', () => {
                dialog.remove();
            }, { once: true });

            dialog.showModal();
            input.focus();

        } catch (error) {
            feedback(error.message, true);
        }
    }

    function renderEventActions(item) {
        const cell = el('td');
        cell.append(eventActionGroup(item));
        return cell;
    }

    // Catálogo de eventos: las opciones que abren un formulario (modificar, precios) lo
    // muestran en la sección Eventos y, al cerrarlo (guardar o cancelar), se regresa al catálogo
    let regresarA = null;

    function desdeCatalogo(abrir) {
        return () => {
            navigate('eventos');
            regresarA = 'catalogo';   // después de navigate(): navigate() cierra el formulario
            abrir();
        };
    }

    function volverSiHaceFalta() {
        if (!regresarA) return;
        const destino = regresarA;
        regresarA = null;
        navigate(destino);
    }

    // Botones de acción de un evento (los usan la tabla y las tarjetas del catálogo)
    function eventActionGroup(item, { catalogo = false } = {}) {
        const conRegreso = abrir => (catalogo ? desdeCatalogo(abrir) : abrir);
        const group = el('div', undefined, 'eventos-row-actions');

        group.append(
            actionButton(
                'Ver detalles',
                'evento-action',
                () => viewEvent(item.id)
            )
        );

        const menu = document.createElement('details');
        menu.className = 'so-event-menu';

        const summary = document.createElement('summary');
        summary.className = 'so-event-menu-trigger';
        summary.textContent = 'Gestionar';

        const options = el(
            'div',
            undefined,
            'so-event-menu-options'
        );

        const addOption = (label, callback, danger = false) => {
            const button = actionButton(
                label,
                danger
                    ? 'so-event-menu-option is-danger'
                    : 'so-event-menu-option',
                () => {
                    menu.open = false;
                    callback();
                }
            );

            options.append(button);
        };

        if (item.estado === 'borrador') {
            addOption(
                'Modificar evento',
                conRegreso(() => editEvent(item.id))
            );

            addOption(
                'Configurar precios',
                conRegreso(() => openPrices(item.id))
            );

            addOption(
                'Publicar evento',
                () => publishEvent(item.id)
            );

            if (currentRole === 'admin') {
                addOption(
                    'Eliminar borrador',
                    () => deleteEvent(item.id),
                    true
                );
            }

        } else {
            if (item.estado === 'publicado' &&
                currentRole === 'admin') {
                addOption(
                    'Modificar evento',
                    conRegreso(() => modifyPublishedEvent(item.id))
                );
            }

            addOption(
                'Ver asientos',
                () => {
                    window.location.href =
                        '/admin/eventos/' +
                        encodeURIComponent(item.id) +
                        '/asientos';
                }
            );

            if (
                currentRole === 'admin' &&
                ['publicado', 'agotado'].includes(item.estado)
            ) {
                addOption(
                    'Cancelar evento',
                    () => cancelEvent(item.id),
                    true
                );
            }
        }

        menu.append(summary, options);
        group.append(menu);

        return group;
    }

    // =====================================================
    // SOIRÉE — DETALLES ADMINISTRATIVOS DE RESERVAS
    // =====================================================

    function viewReservation(item) {
        const anterior = $('so-reservation-dialog');
        if (anterior) anterior.remove();

        const dialog = document.createElement('dialog');
        dialog.id = 'so-reservation-dialog';
        dialog.className = 'so-reservation-dialog';

        const header = el('div', undefined, 'so-reservation-header');
        header.append(
            el('span', 'SOIRÉE · CONTROL DE RESERVAS', 'so-reservation-caption'),
            el('h2', 'Reserva #' + item.id),
            el('p', 'Información registrada de esta reserva.')
        );

        const grid = el('div', undefined, 'so-reservation-grid');

        const addDetail = (label, value) => {
            const row = el('div', undefined, 'so-reservation-detail');

            row.append(
                el('span', label),
                el('strong', value === null || value === undefined || value === ''
                    ? 'No disponible'
                    : value)
            );

            grid.append(row);
        };

        const cliente = [
            item.cliente_nombre,
            item.cliente_apellido
        ].filter(Boolean).join(' ');

        const total = Number(item.total || 0);

        const moneda = new Intl.NumberFormat('es-GT', {
            style: 'currency',
            currency: 'GTQ'
        });

        addDetail('Evento', item.evento || 'Evento no disponible');
        addDetail('Cliente', cliente || 'Usuario #' + item.usuario_id);
        addDetail('Correo electrónico', item.cliente_email);
        addDetail('Estado', item.estado);
        addDetail('Importe registrado', moneda.format(total));
        addDetail('Fecha de registro', formatCell('created_at', item.created_at));
        addDetail('Fecha de vencimiento',
            item.expira_en
                ? formatCell('created_at', item.expira_en)
                : 'No especificada'
        );
        addDetail('Última actualización',
            item.updated_at
                ? formatCell('created_at', item.updated_at)
                : 'No disponible'
        );

        const nota = el(
            'p',
            'El importe mostrado pertenece a la reserva. ' +
            'No confirma por sí solo que exista un pago aprobado.',
            'so-reservation-note'
        );

        const footer = el('div', undefined, 'so-reservation-footer');

        footer.append(
            actionButton(
                'Cerrar',
                'evento-action',
                () => dialog.close()
            )
        );

        dialog.append(header, grid, nota, footer);
        document.body.append(dialog);

        dialog.addEventListener('close', () => dialog.remove(), {
            once: true
        });

        dialog.addEventListener('click', event => {
            if (event.target === dialog) dialog.close();
        });

        dialog.showModal();
    }

    function renderReservationActions(item) {
        const cell = el('td');

        cell.append(
            actionButton(
                'Ver detalles',
                'evento-action',
                () => viewReservation(item)
            )
        );

        return cell;
    }

    // SOIRÉE — DETALLES DE PAGOS

    function viewPayment(item) {
        const anterior = $('so-payment-dialog');
        if (anterior) anterior.remove();

        const dialog = document.createElement('dialog');
        dialog.id = 'so-payment-dialog';
        dialog.className = 'so-reservation-dialog so-payment-dialog';

        const formatoMoneda = new Intl.NumberFormat('es-GT', {
            style: 'currency',
            currency: 'GTQ'
        });

        const moneda = formatoMoneda.format(Number(item.monto || 0));

        const fecha = valor => valor
            ? formatCell('created_at', valor)
            : 'No disponible';

        const nombreCliente = [
            item.cliente_nombre,
            item.cliente_apellido
        ].filter(Boolean).join(' ') || 'No disponible';

        const estado = String(item.estado || 'sin estado');
        const estadoReserva = String(
            item.reserva_estado || 'No disponible'
        );

        const header = el('header', undefined, 'so-payment-header');

        const headerText = el('div');
        headerText.append(
            el('span', 'SOIRÉE · CONTROL FINANCIERO', 'so-payment-eyebrow'),
            el('h2', 'Detalle del pago #' + item.id),
            el('p', (item.evento || 'Evento no disponible') +
                ' · Reserva #' + (item.reserva_id ?? '—'))
        );

        const closeTop = actionButton(
            '×',
            'so-payment-close',
            () => dialog.close()
        );
        closeTop.setAttribute('aria-label', 'Cerrar detalle de pago');

        header.append(headerText, closeTop);

        const summary = el('section', undefined, 'so-payment-summary');

        const amount = el('div');
        amount.append(
            el('span', 'IMPORTE REGISTRADO', 'so-payment-eyebrow'),
            el('strong', moneda, 'so-payment-amount'),
            el('small', fecha(item.created_at))
        );

        const badge = el(
            'span',
            estado,
            'so-payment-status'
        );

        badge.dataset.status = estado.toLowerCase();

        summary.append(amount, badge);

        const sectionTitle = el(
            'h3',
            'Información de la transacción',
            'so-payment-section-title'
        );

        const grid = el('div', undefined, 'so-payment-grid');

        const addDetail = (etiqueta, valor) => {
            const fila = el('div', undefined, 'so-payment-detail');

            fila.append(
                el('span', etiqueta),
                el(
                    'strong',
                    valor === null ||
                    valor === undefined ||
                    valor === ''
                        ? 'No disponible'
                        : String(valor)
                )
            );

            grid.append(fila);
        };

        addDetail('Cliente', nombreCliente);
        addDetail('Correo electrónico', item.cliente_email);
        addDetail('Evento', item.evento);
        addDetail('Número de reserva', '#' + item.reserva_id);
        addDetail('Estado de reserva', estadoReserva);
        addDetail('Última actualización', fecha(item.updated_at));

        const notice = el(
            'p',
            'Este apartado es de consulta. Un pago aprobado ' +
            'no debe modificarse manualmente desde esta ventana. ' +
            'Los reembolsos se gestionan mediante su proceso correspondiente.',
            'so-payment-notice'
        );

        const footer = el('footer', undefined, 'so-payment-footer');

        footer.append(
            actionButton(
                'Cerrar detalles',
                'so-payment-done',
                () => dialog.close()
            )
        );

        dialog.append(
            header,
            summary,
            sectionTitle,
            grid,
            notice,
            footer
        );

        document.body.append(dialog);

        dialog.addEventListener('close', () => {
            dialog.remove();
        }, { once: true });

        dialog.addEventListener('click', event => {
            if (event.target === dialog) dialog.close();
        });

        dialog.showModal();
    }

    function renderPaymentActions(item) {
        const cell = el('td');

        cell.append(
            actionButton(
                'Ver detalles',
                'evento-action',
                () => viewPayment(item)
            )
        );

        return cell;
    }

    function renderTable(items, kind, pagination = null) {
        const cols = columns[kind];

        const thead = $('thead');
        const tbody = $('tbody');
        const paginationElement = $('pagination');

        thead.replaceChildren();
        tbody.replaceChildren();
        paginationElement.replaceChildren();

        const headerRow = el('tr');

        cols.forEach(([, label]) => {
            headerRow.append(el('th', label));
        });

        if (['eventos', 'usuarios', 'reservas', 'pagos'].includes(kind)) {
            headerRow.append(el('th', 'Acciones'));
        }

        thead.append(headerRow);

        for (const item of items) {
            const row = el('tr');

            cols.forEach(([field]) => {
                const cell = el('td');
                const value = item[field] ?? '—';

                if (field === 'estado' || field === 'rol') {
                    const positive =
                        /^(activo|activa|admin|publicado|aprobado)$/i
                            .test(String(value));

                    cell.append(
                        el(
                            'span',
                            value,
                            'status' + (positive ? '' : ' neutral')
                        )
                    );
                } else if (kind === 'eventos' && field === 'nombre') {
                    const eventIdentity = el('div', undefined, 'so-event-name');
                    const thumb = el('div', undefined, 'so-event-thumb');
                    const imageUrl = safeEventImage(item.imagen_url);
                    if (imageUrl) {
                        const img = document.createElement('img');
                        img.src = imageUrl;
                        img.alt = '';
                        img.loading = 'lazy';
                        img.addEventListener('error', () => {
                            thumb.replaceChildren(el('span', '♫'));
                        }, { once: true });
                        thumb.append(img);
                    } else {
                        thumb.append(el('span', '♫'));
                    }
                    const textGroup = el('div', undefined, 'so-event-label');
                    textGroup.append(el('strong', value), el('small', 'ID #' + item.id));
                    eventIdentity.append(thumb, textGroup);
                    cell.append(eventIdentity);
                } else {
                    cell.textContent = formatCell(field, value);
                }

                row.append(cell);
            });

            if (kind === 'eventos') {
                row.append(renderEventActions(item));
            }

            if (kind === 'usuarios') {
                row.append(renderUserActions(item));
            }

            if (kind === 'reservas') {
                row.append(renderReservationActions(item));
            }

            if (kind === 'pagos') {
                row.append(renderPaymentActions(item));
            }

            tbody.append(row);
        }

        if (!items.length) {
            const row = el('tr');

            const cell = el(
                'td',
                'No hay registros para mostrar.',
                'empty'
            );

            cell.colSpan = cols.length +
                (['eventos', 'usuarios', 'reservas', 'pagos'].includes(kind) ? 1 : 0);

            row.append(cell);
            tbody.append(row);
        }

        if (pagination && pagination.last_page > 1) {
            const previous = el('button', '← Anterior');
            const next = el('button', 'Siguiente →');

            previous.disabled =
                pagination.current_page <= 1;

            next.disabled =
                pagination.current_page >= pagination.last_page;

            previous.addEventListener('click', () => {
                page--;
                load();
            });

            next.addEventListener('click', () => {
                page++;
                load();
            });

            paginationElement.append(
                previous,
                el(
                    'span',
                    `Página ${pagination.current_page} de ${pagination.last_page}`
                ),
                next
            );
        }
    }

    // =====================================================
    // NAVEGACIÓN
    // =====================================================

    function updateHeader() {
        const title = $('page-title');
        const subtitle = $('page-subtitle');
        const pair = titles[section];

        const text = section === 'resumen'
            ? 'Panel de Administración'
            : pair[0];

        title.replaceChildren();

        const words = text.split(' ');

        if (words.length > 1) {
            title.append(
                document.createTextNode(
                    words.slice(0, -1).join(' ') + ' '
                ),
                el('em', words.at(-1))
            );
        } else {
            title.textContent = text;
        }

        subtitle.textContent = section === 'resumen'
            ? 'Gestiona eventos, reservas y grandes experiencias.'
            : pair[1];
    }

    function closeEventForm() {
        editingEventId = null;
        editingPublished = false;

        const recinto = $('evento-recinto');
        if (recinto) recinto.disabled = false;

        const fecha = $('evento-form')?.elements.namedItem('fecha_evento');
        if (fecha) fecha.readOnly = false;

        show('evento-form-panel', false);

        const form = $('evento-form');

        if (form) {
            form.reset();
        }

        clearFormFeedback();
        volverSiHaceFalta();
    }

    // =====================================================
    // NOTIFICACIONES ADMINISTRATIVAS
    // Actividad reciente; consulta de solo lectura.
    // =====================================================

    let actividadNotificaciones = [];
    let filtroNotificaciones = 'todas';
    let cargandoNotificaciones = false;

    function nodoNotificacion(tag, texto = '', clase = '') {
        const nodo = document.createElement(tag);

        if (clase) nodo.className = clase;
        if (texto !== '') nodo.textContent = String(texto);

        return nodo;
    }

    // Lecturas locales del Administrador.
    // Se conserva el estado en este navegador.
    const CLAVE_SOR_NOTIF_V2 = 'soiree-notificaciones-leidas-v2';

    function sorLeidas() {
        try {
            const valor = JSON.parse(
                localStorage.getItem(CLAVE_SOR_NOTIF_V2) || '[]'
            );
            return new Set(Array.isArray(valor) ? valor : []);
        } catch {
            return new Set();
        }
    }

    function sorContador() {
        const badge = $('so-notif-badge');
        if (!badge) return;

        const leidas = sorLeidas();

        const nuevas = actividadNotificaciones.filter(
            aviso => !leidas.has(String(aviso.clave))
        ).length;

        badge.textContent = nuevas > 99 ? '99+' : String(nuevas);
        badge.hidden = nuevas === 0;
        badge.setAttribute(
            'aria-label', nuevas + ' notificaciones pendientes'
        );
    }

    function sorMarcarLeida(clave) {
        const leidas = sorLeidas();
        leidas.add(String(clave));

        localStorage.setItem(
            CLAVE_SOR_NOTIF_V2,
            JSON.stringify([...leidas].slice(-1000))
        );

        sorContador();

        if (section === 'notificaciones') {
            pintarNotificacionesAdmin();
        }
    }

    async function sorAbrirNotificacion(aviso) {
        const id = Number(aviso.referencia_id);
        if (!Number.isInteger(id) || id <= 0) {
            feedback('Identificador de notificación inválido.', true);
            return;
        }

        const pago = aviso.tipo === 'pago';

        try {
            const response = await api(
                (pago ? 'pagos/' : 'reservas/') + id
            );

            const json = await response.json();

            if (!response.ok || !json.data) {
                throw new Error(
                    json.message || 'No se encontró el registro.'
                );
            }

            // Abrir el detalle exacto sin depender de la
            // página actual de la tabla.
            if (pago) {
                viewPayment(json.data);
            } else {
                viewReservation(json.data);
            }

            sorMarcarLeida(aviso.clave);
        } catch (error) {
            feedback('No se pudo abrir: ' + error.message, true);
        }
    }

    let sorConsultando = false;

    async function sorConsultarBurbuja() {
        if (currentRole !== 'admin' || sorConsultando) return;

        sorConsultando = true;

        try {
            const response = await api('notificaciones');
            if (!response.ok) return;

            const json = await response.json();
            if (!Array.isArray(json.data)) return;

            actividadNotificaciones = json.data;
            sorContador();

            if (section === 'notificaciones') {
                pintarNotificacionesAdmin();
            }
        } catch (error) {
            console.warn('No fue posible consultar avisos:', error);
        } finally {
            sorConsultando = false;
        }
    }

    function pintarNotificacionesAdmin() {
        const lista = $('so-notificaciones-lista');
        const filtros = $('so-notificaciones-filtros');

        if (!lista || !filtros) return;

        const tipos = [
            ['todas', 'Todas'],
            ['reserva', 'Reservas'],
            ['pago', 'Pagos'],
            ['compra', 'Boletos']
        ];

        filtros.replaceChildren();

        for (const [valor, nombre] of tipos) {
            const boton = nodoNotificacion(
                'button',
                nombre,
                'so-notif-filter'
            );

            boton.type = 'button';
            boton.setAttribute(
                'aria-pressed',
                String(filtroNotificaciones === valor)
            );

            boton.addEventListener('click', () => {
                filtroNotificaciones = valor;
                pintarNotificacionesAdmin();
            });

            filtros.append(boton);
        }

        const conteos = {
            reserva: 0,
            pago: 0,
            compra: 0
        };

        for (const aviso of actividadNotificaciones) {
            if (Object.hasOwn(conteos, aviso.tipo)) {
                conteos[aviso.tipo]++;
            }
        }

        $('so-notif-total').textContent =
            String(actividadNotificaciones.length);

        $('so-notif-reservas').textContent =
            String(conteos.reserva);

        $('so-notif-pagos').textContent =
            String(conteos.pago);

        $('so-notif-compras').textContent =
            String(conteos.compra);

        const visibles = actividadNotificaciones.filter(aviso =>
            filtroNotificaciones === 'todas' ||
            aviso.tipo === filtroNotificaciones
        );

        lista.replaceChildren();

        if (visibles.length === 0) {
            lista.append(
                nodoNotificacion(
                    'p',
                    'No hay actividad para este filtro.',
                    'so-notif-empty'
                )
            );
            return;
        }

        const etiquetas = {
            reserva: 'Reserva registrada',
            pago: 'Pago aprobado',
            compra: 'Boletos emitidos'
        };

        const simbolos = {
            reserva: '◷',
            pago: '✓',
            compra: '✦'
        };

        for (const aviso of visibles) {
            const tarjeta = nodoNotificacion(
                'article', '', 'so-notif-card'
            );

            const icono = nodoNotificacion(
                'span',
                simbolos[aviso.tipo] || '•',
                'so-notif-icon'
            );

            icono.dataset.tipo = aviso.tipo || '';

            const cuerpo = nodoNotificacion(
                'div', '', 'so-notif-body'
            );

            const encabezado = nodoNotificacion(
                'div', '', 'so-notif-card-head'
            );

            encabezado.append(
                nodoNotificacion(
                    'strong',
                    aviso.titulo ||
                    etiquetas[aviso.tipo] ||
                    'Actividad'
                ),
                nodoNotificacion(
                    'span',
                    etiquetas[aviso.tipo] || 'Aviso',
                    'so-notif-type'
                )
            );

            const descripcion = nodoNotificacion(
                'p', aviso.descripcion || ''
            );

            const pie = nodoNotificacion(
                'div', '', 'so-notif-card-foot'
            );

            pie.append(
                nodoNotificacion(
                    'small',
                    String(aviso.fecha || '')
                        .replace('T', ' ')
                        .slice(0, 19)
                )
            );

            const ver = nodoNotificacion(
                'button',
                aviso.tipo === 'pago'
                    ? 'Ver pago #' + aviso.referencia_id
                    : aviso.tipo === 'compra'
                        ? 'Ver boletos · reserva #' + aviso.referencia_id
                        : 'Ver reserva #' + aviso.referencia_id,
                'so-notif-link'
            );

            ver.type = 'button';
            ver.addEventListener('click', () => {
                sorAbrirNotificacion(aviso);
            });

            const leida = sorLeidas().has(String(aviso.clave));

            const marcar = nodoNotificacion(
                'button',
                leida ? 'Leída' : 'Marcar leída',
                'so-notif-mark'
            );

            marcar.type = 'button';
            marcar.disabled = leida;
            marcar.addEventListener('click', () => {
                sorMarcarLeida(aviso.clave);
            });

            tarjeta.classList.toggle('so-notif-read', leida);
            pie.append(ver, marcar);
            cuerpo.append(encabezado, descripcion, pie);
            tarjeta.append(icono, cuerpo);
            lista.append(tarjeta);
        }
    }

    async function cargarNotificacionesAdmin() {
        if (currentRole !== 'admin' ||
            section !== 'notificaciones' ||
            cargandoNotificaciones) {
            return;
        }

        cargandoNotificaciones = true;

        const estado = $('so-notificaciones-estado');
        if (estado) estado.textContent = 'Actualizando actividad...';

        try {
            const response = await api('notificaciones');
            const resultado = await response.json();

            if (!Array.isArray(resultado.data)) {
                throw new Error('La API devolvió un formato inesperado.');
            }

            actividadNotificaciones = resultado.data;
            sorContador();

            if (section !== 'notificaciones') return;

            pintarNotificacionesAdmin();

            if (estado) {
                estado.textContent =
                    'Mostrando hasta 30 actividades recientes.';
            }
        } catch (error) {
            if (estado && section === 'notificaciones') {
                estado.textContent =
                    'No se pudo actualizar la actividad: ' +
                    error.message;
            }
        } finally {
            cargandoNotificaciones = false;
        }
    }

    $('so-notificaciones-recargar')?.addEventListener(
        'click',
        () => cargarNotificacionesAdmin()
    );

    // Solo consulta cuando está abierta la sección.
    setInterval(() => {
        if (section === 'notificaciones') {
            cargarNotificacionesAdmin();
        }
    }, 30000);

    // Comprobar nuevas actividades cada 30 segundos.
    setInterval(() => {
        sorConsultarBurbuja();
    }, 30000);

    function updateSections() {
        if (currentRole === 'admin' &&
            section !== 'notificaciones') {
            sorConsultarBurbuja();
        }

        const isOverview = section === 'resumen';
        const isEvents = section === 'eventos';
        const isReports = section === 'reportes';
        const isNotifications = section === 'notificaciones';
        const isBanner = section === 'banner';   // lo maneja admin-banner.js
        const isCatalogo = section === 'catalogo';   // lo maneja admin-catalogo-eventos.js
        const isBitacora = section === 'bitacora';   // lo maneja admin-bitacora.js
        const isConfig = section === 'configuracion';
        const isPending = pendingSections.has(section) && !isConfig;

        show('dashboard', !isReports && !isPending && !isBanner && !isNotifications && !isConfig && !isCatalogo && !isBitacora);
        show('banner-panel', isBanner);
        show('catalogo-panel', isCatalogo);
        show('reservas-estados', section === 'reservas');
        // El Resumen ya no muestra "Reservas recientes": la misma tabla está en Reservas
        show('tabla-datos', !isOverview);
        show('bitacora-panel', isBitacora);
        show('overview-content', isOverview);
        show('eventos-tools', isEvents);
        show('reports', isReports);
        show('coming-soon', isPending);
        show('so-configuracion', isConfig);
        if (isConfig) renderConfiguracion();
        show('notificaciones-panel', isNotifications);
        if (isNotifications) cargarNotificacionesAdmin();

        if (!isEvents) {
            closeEventForm();
        }

        if (isPending) {
            $('soon-title').textContent = titles[section][0];

            $('soon-description').textContent =
                titles[section][1] +
                ' Esta sección estará disponible cuando se desarrollen sus funciones.';
        }
    }

    function renderFilters() {
        const filters = $('filters');
        filters.replaceChildren();

        if (section !== 'usuarios') {
            return;
        }

        const search = el('input');
        search.placeholder = 'Buscar por nombre o correo';
        search.value = query;
        search.className = 'search';
        search.setAttribute(
            'aria-label',
            'Buscar usuarios'
        );

        const button = el('button', 'Buscar', 'primary');

        const submitSearch = () => {
            query = search.value.trim();
            page = 1;
            load();
        };

        button.addEventListener('click', submitSearch);

        search.addEventListener('keydown', event => {
            if (event.key === 'Enter') {
                event.preventDefault();
                submitSearch();
            }
        });

        const createButton = actionButton(
            '+ Nuevo usuario',
            'primary',
            () => openUserForm()
        );

        filters.append(search, button, createButton);
    }

    // =====================================================
    // GESTIÓN DE EVENTOS
    // =====================================================

    async function loadRecintos(selectedId = '') {
        const select = $('evento-recinto');

        select.replaceChildren(
            el('option', 'Cargando recintos...')
        );

        const response = await api('eventos/recintos');
        const result = await response.json();

        select.replaceChildren();

        const placeholder = el(
            'option',
            'Seleccionar recinto'
        );

        placeholder.value = '';
        select.append(placeholder);

        const recintos = result.data || [];

        recintos.forEach(recinto => {
            const option = el(
                'option',
                recinto.nombre +
                    (recinto.ciudad
                        ? ' — ' + recinto.ciudad
                        : '')
            );

            option.value = String(recinto.id);
            select.append(option);
        });

        if (selectedId !== '') {
            select.value = String(selectedId);
        }

        if (!recintos.length) {
            formFeedback(
                'No hay recintos activos. Primero registra un recinto.',
                true
            );
        }
    }

    function dateForInput(value) {
        if (!value) {
            return '';
        }

        return String(value)
            .replace(' ', 'T')
            .slice(0, 16);
    }

    // =====================================================
    // LOCALIDADES DINÁMICAS DEL FORMULARIO DE EVENTOS
    // =====================================================

    let versionLocalidades = 0;

    async function loadVenueLocalities(recintoId, eventoId = null) {
        const version = ++versionLocalidades;
        const lista = $('evento-localidades-lista');
        const panel = $('evento-localidades-panel');
        const mensaje = $('evento-localidades-feedback');

        if (!lista || !panel) return;

        lista.replaceChildren();
        if (mensaje) mensaje.hidden = true;

        panel.hidden = false;

        // En edición, consultar las localidades y tarifas
        // que pertenecen al evento existente.
        if (eventoId !== null) {
            lista.append(
                el('p', 'Cargando precios del evento...', 'muted')
            );

            try {
                const response = await api(
                    'eventos/' + encodeURIComponent(eventoId) + '/precios'
                );

                const data = await response.json();

                if (version !== versionLocalidades) return;

                lista.replaceChildren();

                for (const localidad of (data.localidades || [])) {
                    const fila = el('div', undefined, 'evento-precio-fila');

                    const informacion = el('div');
                    informacion.append(
                        el('strong', localidad.nombre),
                        el(
                            'small',
                            'Capacidad: ' + localidad.capacidad +
                            ' · ' + (localidad.tipo || 'General')
                        )
                    );

                    const etiqueta = document.createElement('label');
                    etiqueta.textContent = 'Precio (Q)';

                    const input = document.createElement('input');
                    input.type = 'number';
                    input.min = '0.01';
                    input.step = '0.01';
                    input.max = '99999999.99';
                    input.value = localidad.precio ?? '';
                    input.dataset.localidadId = localidad.id;
                    input.dataset.capacidad = localidad.capacidad;
                    input.dataset.tipo = localidad.tipo || '';
                    input.dataset.nombre = localidad.nombre;
                    input.className = 'evento-localidad-precio';

                    // La API determina si las tarifas son editables.
                    input.disabled = !data.editable;

                    etiqueta.append(input);
                    fila.append(informacion, etiqueta);
                    lista.append(fila);
                }

                const boton = actionButton(
                    data.editable
                        ? 'Editar y guardar precios'
                        : 'Consultar gestión de precios',
                    'evento-action',
                    () => openPrices(eventoId)
                );

                lista.append(boton);

                if (!data.editable && mensaje) {
                    mensaje.textContent =
                        'Las tarifas están protegidas para este evento. ' +
                        'Puedes consultarlas, pero no modificarlas ' +
                        'mientras la API no autorice el cambio.';
                    mensaje.hidden = false;
                }

            } catch (error) {
                if (version !== versionLocalidades) return;

                lista.replaceChildren();

                if (mensaje) {
                    mensaje.textContent = error.message;
                    mensaje.hidden = false;
                }
            }

            return;
        }

        if (!recintoId) {
            lista.append(el(
                'p',
                'Selecciona un recinto para cargar sus localidades.',
                'muted'
            ));
            return;
        }

        lista.append(el('p', 'Cargando localidades...', 'muted'));

        try {
            const response = await api(
                'eventos/recintos/' +
                encodeURIComponent(recintoId) +
                '/localidades'
            );

            const data = await response.json();

            if (version !== versionLocalidades) return;

            // Ignorar respuestas de un recinto anterior.
            if ($('evento-recinto').value !== String(recintoId)) return;

            lista.replaceChildren();

            if (!Array.isArray(data.localidades) ||
                data.localidades.length === 0) {

                lista.append(el(
                    'p',
                    'Este recinto no tiene localidades configuradas.',
                    'muted'
                ));
                return;
            }

            for (const localidad of data.localidades) {
                const fila = el('div', undefined, 'evento-precio-fila');

                const informacion = el('div');
                informacion.append(
                    el('strong', localidad.nombre),
                    el(
                        'small',
                        (localidad.tipo === 'numerada'
                            ? 'Asientos numerados'
                            : 'Zona general') +
                        ' · Capacidad: ' + localidad.capacidad
                    )
                );

                const etiqueta = document.createElement('label');
                etiqueta.textContent = 'Precio (Q)';

                const input = document.createElement('input');
                input.type = 'number';
                input.min = '0.01';
                input.max = '99999999.99';
                input.step = '0.01';
                input.placeholder = '0.00';
                input.required = true;
                input.dataset.localidadId = localidad.id;
                input.dataset.capacidad = localidad.capacidad;
                input.dataset.tipo = localidad.tipo || '';
                input.dataset.nombre = localidad.nombre;
                input.className = 'evento-localidad-precio';

                etiqueta.append(input);
                fila.append(informacion, etiqueta);
                lista.append(fila);
            }
        } catch (error) {
            if (version !== versionLocalidades) return;

            lista.replaceChildren();

            if (mensaje) {
                mensaje.textContent = error.message;
                mensaje.hidden = false;
            }
        }
    }

    async function openEventForm(evento = null) {
        if (section !== 'eventos') {
            return;
        }

        editingEventId = evento ? evento.id : null;
        editingPublished = evento?.estado === 'publicado';

        const form = $('evento-form');
        form.reset();

        clearFormFeedback();

        $('evento-form-title').textContent = editingPublished
            ? 'Modificar evento publicado'
            : evento ? 'Editar evento' : 'Crear nuevo evento';

        $('btn-guardar-evento').textContent = evento
            ? 'Guardar cambios'
            : 'Guardar borrador';

        show('evento-form-panel', true);

        if (evento) {
            form.elements.namedItem('nombre').value =
                evento.nombre || '';

            form.elements.namedItem('descripcion').value =
                evento.descripcion || '';

            form.elements.namedItem('frase_destacada').value =
                evento.frase_destacada || '';

            form.elements.namedItem('fecha_evento').value =
                dateForInput(evento.fecha_evento);
        }

        // Cargar las fotografías adicionales al editar.
        for (const campo of ['imagen_url_2', 'imagen_url_3']) {
            const input = form.elements.namedItem(campo);
            if (input) {
                input.value = evento?.[campo] || '';
            }
        }

        // Cargar también la fotografía existente.
        const campoImagen =
            form.elements.namedItem('imagen_url');

        if (campoImagen) {
            campoImagen.value = evento?.imagen_url || '';

            campoImagen.dispatchEvent(
                new Event('input')
            );
        }

        try {
            await loadRecintos(evento?.recinto_id ?? '');
            await loadVenueLocalities(
                $('evento-recinto').value,
                evento?.id ?? null
            );
        } catch (error) {
            formFeedback(error.message, true);
        }

        // En publicados, fecha y recinto son solo de consulta.
        const recintoCampo = $('evento-recinto');
        if (recintoCampo) recintoCampo.disabled = editingPublished;

        const fechaCampo = form.elements.namedItem('fecha_evento');
        if (fechaCampo) fechaCampo.readOnly = editingPublished;

        // Aviso para fotos, costos, plano y borrador (admin-evento-formulario.js, admin-costos.js)
        document.dispatchEvent(new CustomEvent('admin:formulario-evento', {
            detail: { eventoId: evento ? evento.id : null, publicado: editingPublished }
        }));

        $('evento-form-panel').scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }

    async function fetchEvent(id) {
        const response = await api('eventos/' + id);
        const result = await response.json();

        return result.data;
    }

    async function editEvent(id) {
        try {
            const evento = await fetchEvent(id);

            if (evento.estado !== 'borrador') {
                feedback(
                    'Por ahora solo se pueden editar eventos en borrador.',
                    true
                );
                return;
            }

            await openEventForm(evento);
        } catch (error) {
            feedback(error.message, true);
        }
    }

    async function modifyPublishedEvent(id) {
        if (currentRole !== 'admin') {
            feedback('Solo el Administrador puede modificar eventos publicados.', true);
            return;
        }

        try {
            const evento = await fetchEvent(id);

            if (evento.estado !== 'publicado') {
                feedback('Esta opción solo está disponible para eventos publicados.', true);
                return;
            }

            await openEventForm(evento);
        } catch (error) {
            feedback(error.message, true);
        }
    }

    // =====================================================
    // CAMBIAR IMAGEN DE EVENTOS PUBLICADOS
    // =====================================================

    async function changeEventImage(id) {
        try {
            const evento = await fetchEvent(id);

            const entrada = window.prompt(
                'Fotografía del evento: ' + evento.nombre +
                '\n\nIntroduce una URL HTTPS.\n' +
                'Deja el campo vacío para quitar la imagen:',
                evento.imagen_url || ''
            );

            if (entrada === null) {
                return;
            }

            const imagenUrl = entrada.trim();

            if (imagenUrl) {
                try {
                    const url = new URL(imagenUrl);

                    if (url.protocol !== 'https:') {
                        feedback(
                            'La imagen debe utilizar HTTPS.',
                            true
                        );
                        return;
                    }
                } catch {
                    feedback(
                        'Introduce una URL válida.',
                        true
                    );
                    return;
                }

                if (imagenUrl.length > 2048) {
                    feedback(
                        'La URL no puede superar 2048 caracteres.',
                        true
                    );
                    return;
                }
            }

            const confirmado = window.confirm(
                '¿Actualizar la fotografía del evento "' +
                evento.nombre + '"?'
            );

            if (!confirmado) {
                return;
            }

            feedback('Actualizando fotografía...');

            const response = await api(
                'eventos/' + id + '/imagen',
                {
                    method: 'PATCH',
                    body: JSON.stringify({
                        imagen_url: imagenUrl || null
                    })
                }
            );

            const result = await response.json();

            await load();

            feedback(
                result.message ||
                'Fotografía actualizada correctamente.'
            );

        } catch (error) {
            feedback(error.message, true);
        }
    }

    // =====================================================
    // VER EVENTO
    // =====================================================

    // Only render remote imagery from HTTP(S) URLs.
    function safeEventImage(value) {
        if (typeof value !== 'string' || !value.trim()) return null;
        try {
            // Acepta también las fotos subidas al propio sitio (/storage/eventos/...)
            const parsed = new URL(value, window.location.origin);
            return ['https:', 'http:'].includes(parsed.protocol)
                ? parsed.href
                : null;
        } catch (_) {
            return null;
        }
    }

    function ensureEventDrawer() {
        let dialog = $('so-event-dialog');
        if (dialog) return dialog;

        dialog = document.createElement('dialog');
        dialog.id = 'so-event-dialog';
        dialog.className = 'so-event-dialog';
        dialog.setAttribute('aria-labelledby', 'so-event-title');
        dialog.innerHTML = `
            <div class="so-dialog-shell">
                <header class="so-dialog-header">
                    <div><span class="so-dialog-caption">Soirée · Administración</span>
                        <h2 id="so-event-title">Detalle del evento</h2></div>
                    <button type="button" class="so-icon-close" id="so-event-close" aria-label="Cerrar detalles">×</button>
                </header>
                <div id="so-dialog-content" class="so-dialog-content"></div>
                <footer class="so-dialog-actions" id="so-dialog-actions"></footer>
            </div>`;
        document.body.append(dialog);
        $('so-event-close').addEventListener('click', () => dialog.close());
        dialog.addEventListener('click', event => {
            if (event.target === dialog) dialog.close();
        });
        return dialog;
    }

    function detailRow(label, value) {
        const item = el('div', undefined, 'so-detail-row');
        item.append(el('span', label), el('strong', value || 'No especificado'));
        return item;
    }

    async function viewEvent(id) {
        try {
            const evento = await fetchEvent(id);
            const dialog = ensureEventDrawer();
            const content = $('so-dialog-content');
            const actions = $('so-dialog-actions');
            content.replaceChildren();
            actions.replaceChildren();

            const hero = el('div', undefined, 'so-dialog-cover');
            const validImage = safeEventImage(evento.imagen_url);
            if (validImage) {
                const img = document.createElement('img');
                img.src = validImage;
                img.alt = 'Fotografía del evento ' + (evento.nombre || '');
                img.addEventListener('error', () => {
                    hero.replaceChildren(el('span', 'SOIRÉE'));
                }, { once: true });
                hero.append(img);
            } else {
                hero.append(el('span', 'SOIRÉE'));
            }
            content.append(hero);

            const intro = el('section', undefined, 'so-dialog-intro');
            intro.append(el('h3', evento.nombre || 'Evento'));
            const status = el('span', evento.estado || 'Sin estado', 'so-event-status');
            status.dataset.status = evento.estado || '';
            intro.append(status);
            content.append(intro);

            const details = el('section', undefined, 'so-dialog-detail-grid');
            details.append(
                detailRow('Fecha y hora', formatCell('fecha_evento', evento.fecha_evento)),
                detailRow('Recinto', evento.recinto?.nombre || evento.recinto || 'Consulta el catálogo'),
                detailRow('Estado', evento.estado),
                detailRow('Identificador', '#' + evento.id)
            );
            content.append(details);

            const about = el('section', undefined, 'so-dialog-about');
            about.append(
                el('h4', 'Sobre el evento'),
                el('p', evento.descripcion || 'Este evento todavía no tiene descripción.')
            );
            content.append(about);

            const closeButton = actionButton('Cerrar', 'so-button-quiet', () => dialog.close());
            actions.append(closeButton);
            if (evento.estado === 'borrador') {
                actions.append(actionButton('Editar evento', 'so-button-primary', () => {
                    dialog.close();
                    editEvent(evento.id);
                }));
            } else {
                // Inventario: plano de ocupación en vivo
                actions.append(actionButton('Ver asientos', 'so-button-quiet', () => {
                    window.location.href = '/admin/eventos/' + evento.id + '/asientos';
                }));
                actions.append(actionButton('Cambiar imagen', 'so-button-primary', () => {
                    dialog.close();
                    changeEventImage(evento.id);
                }));
            }
            dialog.showModal();
        } catch (error) {
            feedback(error.message, true);
        }
    }

    // =====================================================
    // PRECIOS POR LOCALIDAD
    // Conecta el panel #evento-precios-panel con
    // GET / PUT /api/admin/eventos/{id}/precios.
    // Al publicar, Inventario exige precio en todas las localidades.
    // =====================================================

    let preciosEventoId = null;

    function preciosFeedback(message, error = false) {
        const node = $('evento-precios-feedback');
        if (!node) return;

        node.textContent = message;
        node.className =
            'evento-form-feedback' + (error ? ' error' : '');
        node.hidden = false;
    }

    function closePrices() {
        show('evento-precios-panel', false);
        preciosEventoId = null;
        volverSiHaceFalta();
    }

    async function openPrices(id) {
        clearFeedback();
        preciosEventoId = id;

        const lista = $('evento-precios-lista');
        const guardar = $('btn-guardar-precios');

        lista.replaceChildren(el('p', 'Cargando localidades…'));
        guardar.disabled = true;
        show('evento-precios-feedback', false);
        show('evento-precios-panel', true);
        $('evento-precios-panel').scrollIntoView({ behavior: 'smooth', block: 'start' });

        try {
            const data = await (await api('eventos/' + id + '/precios')).json();

            $('evento-precios-subtitle').textContent =
                data.evento.nombre +
                ': define el precio de cada sección del recinto. ' +
                'Todas deben tener precio para poder publicar.';

            lista.replaceChildren();

            if (!data.localidades.length) {
                lista.append(el('p', 'El recinto de este evento no tiene localidades configuradas.'));
                return;
            }

            data.localidades.forEach(localidad => {
                const fila = el('div', undefined, 'evento-precio-fila');

                const info = el('div');
                info.append(
                    el('strong', localidad.nombre),
                    el(
                        'small',
                        (localidad.tipo === 'numerada' ? 'Asientos numerados' : 'Zona general') +
                        ' · capacidad ' + localidad.capacidad
                    )
                );

                const label = el('label', 'Precio (Q)');
                const input = document.createElement('input');
                input.type = 'number';
                input.min = '0.01';
                input.step = '0.01';
                input.required = true;
                input.placeholder = '0.00';
                input.value = localidad.precio ?? '';
                input.dataset.localidadId = localidad.id;
                input.disabled = !data.editable;
                label.append(input);

                fila.append(info, label);
                lista.append(fila);
            });

            guardar.disabled = !data.editable;

            if (!data.editable) {
                preciosFeedback('El evento ya está publicado: sus precios no se pueden cambiar.');
            }
        } catch (error) {
            preciosFeedback(error.message, true);
        }
    }

    async function savePrices(event) {
        event.preventDefault();
        if (!preciosEventoId) return;

        const precios = [...$('evento-precios-lista').querySelectorAll('input[data-localidad-id]')]
            .map(input => ({
                localidad_id: Number(input.dataset.localidadId),
                precio: input.value
            }));

        if (!precios.length || precios.some(p => !(Number(p.precio) > 0))) {
            preciosFeedback('Escribe un precio mayor a cero para cada localidad.', true);
            return;
        }

        const guardar = $('btn-guardar-precios');
        guardar.disabled = true;

        try {
            const result = await (await api('eventos/' + preciosEventoId + '/precios', {
                method: 'PUT',
                body: JSON.stringify({ precios })
            })).json();

            preciosFeedback(result.message || 'Precios guardados correctamente.');
        } catch (error) {
            preciosFeedback(error.message, true);
        } finally {
            guardar.disabled = false;
        }
    }

    // =====================================================
    // PUBLICAR EVENTOS
    // =====================================================

    async function publishEvent(id) {
        const accepted = window.confirm(
            '¿Deseas publicar este evento?\n\n' +
            'Si tiene fecha futura y recinto activo, ' +
            'podrá aparecer en el catálogo público.'
        );

        if (!accepted) {
            return;
        }

        feedback('Publicando evento...');

        try {
            const response = await api(
                'eventos/' + id + '/publicar',
                {
                    method: 'PATCH'
                }
            );

            const result = await response.json();

            closeEventForm();
            page = 1;

            await load();

            feedback(
                result.message ||
                'Evento publicado correctamente.'
            );

        } catch (error) {
            feedback(error.message, true);
        }
    }

    // =====================================================
    // FORMULARIO DE EVENTOS
    // =====================================================

    const formPrecios = $('evento-precios-form');

    if (formPrecios) {
        formPrecios.addEventListener('submit', savePrices);
    }

    ['btn-cerrar-precios', 'btn-cancelar-precios']
        .forEach(id => {
            const button = $(id);

            if (button) {
                button.addEventListener('click', closePrices);
            }
        });

    const newEventButton = $('btn-nuevo-evento');

    if (newEventButton) {
        newEventButton.addEventListener('click', () => {
            openEventForm();
        });
    }

    ['btn-cerrar-evento', 'btn-cancelar-evento']
        .forEach(id => {
            const button = $(id);

            if (button) {
                button.addEventListener(
                    'click',
                    closeEventForm
                );
            }
        });

    const eventForm = $('evento-form');

    if (eventForm) {
        const recintoSelect = $('evento-recinto');

        if (recintoSelect) {
            recintoSelect.addEventListener('change', () => {
                loadVenueLocalities(
                    recintoSelect.value,
                    editingEventId
                );
            });
        }

        eventForm.addEventListener('submit', async event => {
            event.preventDefault();

            if (savingEvent) {
                return;
            }

            if (!eventForm.reportValidity()) {
                return;
            }

            const formData = new FormData(eventForm);

            const payload = {
                nombre: String(
                    formData.get('nombre') || ''
                ).trim(),

                descripcion: String(
                    formData.get('descripcion') || ''
                ).trim(),

                // Título de la primera escena de la experiencia (opcional)
                frase_destacada: String(
                    formData.get('frase_destacada') || ''
                ).trim() || null,

                imagen_url: String(
                    formData.get('imagen_url') || ''
                ).trim() || null,

                imagen_url_2: String(
                    formData.get('imagen_url_2') || ''
                ).trim() || null,

                imagen_url_3: String(
                    formData.get('imagen_url_3') || ''
                ).trim() || null,

                recinto_id: Number(
                    formData.get('recinto_id')
                ),

                fecha_evento: String(
                    formData.get('fecha_evento') || ''
                ),

                estado: 'borrador'
            };

            const isEditing = editingEventId !== null;
            const isPublishedEdit = editingPublished;

            if (!isEditing) {
                const campos = [
                    ...$('evento-localidades-lista')
                        .querySelectorAll('input[data-localidad-id]')
                ];

                if (!campos.length) {
                    formFeedback(
                        'Selecciona un recinto con localidades configuradas.',
                        true
                    );
                    return;
                }

                const precios = campos.map(input => ({
                    localidad_id: Number(input.dataset.localidadId),
                    precio: input.value.trim()
                }));

                if (precios.some(item =>
                    !item.precio ||
                    !Number.isFinite(Number(item.precio)) ||
                    Number(item.precio) <= 0 ||
                    Number(item.precio) > 99999999.99
                )) {
                    formFeedback(
                        'Asigna un precio válido mayor que Q0 a cada localidad.',
                        true
                    );
                    return;
                }

                payload.precios = precios;

                // Costos de producción (admin-costos.js): se crean junto con el evento
                const costos = window.AdminCostos?.leer() || [];
                if (costos.length) payload.costos = costos;
            }

            const path = isPublishedEdit
                ? 'eventos/' + editingEventId + '/contenido'
                : isEditing ? 'eventos/' + editingEventId : 'eventos';

            const method = isPublishedEdit
                ? 'PATCH' : isEditing ? 'PUT' : 'POST';

            // Solo enviamos los campos autorizados para publicados.
            const datosEnviar = isPublishedEdit
                ? {
                    nombre: payload.nombre,
                    frase_destacada: payload.frase_destacada,
                    descripcion: payload.descripcion,
                    imagen_url: payload.imagen_url,
                    imagen_url_2: payload.imagen_url_2,
                    imagen_url_3: payload.imagen_url_3
                }
                : payload;

            savingEvent = true;

            const saveButton = $('btn-guardar-evento');

            saveButton.disabled = true;
            saveButton.textContent = 'Guardando...';

            clearFormFeedback();

            try {
                const response = await api(path, {
                    method,
                    body: JSON.stringify(datosEnviar)
                });

                const result = await response.json();

                // Al editar, los costos se guardan aparte (admin-costos.js; solo si cambiaron)
                if (isEditing) {
                    await window.AdminCostos?.guardar(editingEventId);
                }

                // El borrador del formulario ya no hace falta (admin-evento-formulario.js)
                document.dispatchEvent(new CustomEvent('admin:evento-guardado', {
                    detail: { creado: !isEditing }
                }));

                closeEventForm();
                page = 1;

                await load();

                feedback(
                    result.message ||
                    'Evento guardado correctamente.'
                );

            } catch (error) {
                formFeedback(error.message, true);

            } finally {
                savingEvent = false;
                saveButton.disabled = false;

                saveButton.textContent =
                    editingEventId !== null
                        ? 'Guardar cambios'
                        : 'Guardar borrador';
            }
        });
    }

    // =====================================================
    // CARGA PRINCIPAL
    // =====================================================

    async function load() {
        // Aviso de recarga: lo escucha el catálogo para actualizarse (publicar, editar, imagen...)
        document.dispatchEvent(new CustomEvent('admin:recargar', { detail: { section } }));

        if (section === 'notificaciones') {
            ++requestVersion;
            updateHeader();
            updateSections();
            clearFeedback();
            return;
        }

        const version = ++requestVersion;

        updateHeader();
        updateSections();

        $('thead').replaceChildren();
        $('tbody').replaceChildren();
        $('pagination').replaceChildren();

        if (
            pendingSections.has(section) ||
            section === 'reportes' ||
            section === 'banner' ||
            section === 'catalogo' ||
            section === 'bitacora'
        ) {
            clearFeedback();
            return;
        }

        feedback('Consultando datos reales de MySQL...');

        try {
            if (section === 'resumen') {
                await loadSummary();
                if (version === requestVersion) clearFeedback();
                return;
            }

            if (section === 'reservas') {
                loadReservationStates();
            }

            if (version !== requestVersion) {
                return;
            }

            const kind = section;

            $('table-title').textContent = titles[section][0];
            $('table-subtitle').textContent = 'Registros disponibles en el sistema.';
            $('view-all').hidden = true;

            renderFilters();

            const params = new URLSearchParams({
                page: String(page)
            });

            if (kind === 'usuarios' && query) {
                params.set('buscar', query);
            }

            const response = await api(
                kind + '?' + params.toString()
            );

            const result = await response.json();

            if (version !== requestVersion) {
                return;
            }

            renderTable(
                result.data || [],
                kind,
                result
            );

            clearFeedback();

        } catch (error) {
            if (version !== requestVersion) {
                return;
            }

            feedback(error.message, true);
        }
    }

    function navigate(target) {
        if (!(target in titles) || !canAccessSection(target)) {
            return;
        }

        section = target;
        page = 1;

        regresarA = null;   // elegir otra sección cancela el regreso pendiente al catálogo
        closeEventForm();

        document.querySelectorAll('.nav-item')
            .forEach(button => {
                const selected =
                    button.dataset.section === target;

                button.classList.toggle(
                    'selected',
                    selected
                );

                if (selected) {
                    button.setAttribute(
                        'aria-current',
                        'page'
                    );
                } else {
                    button.removeAttribute(
                        'aria-current'
                    );
                }
            });

        load();
    }

    document.querySelectorAll('.nav-item')
        .forEach(button => {
            button.addEventListener('click', () => {
                navigate(button.dataset.section);
            });
        });

    document.querySelectorAll('[data-go]')
        .forEach(button => {
            button.addEventListener('click', () => {
                navigate(button.dataset.go);
            });
        });

    // =====================================================
    // BUSCADOR
    // =====================================================

    const globalSearch = $('global-search');

    if (globalSearch) {
        globalSearch.addEventListener('keydown', event => {
            if (event.key !== 'Enter') {
                return;
            }

            event.preventDefault();

            const term = globalSearch.value.trim();

            if (!term) {
                return;
            }

            query = term;
            page = 1;

            navigate('usuarios');
        });
    }

    // =====================================================
    // =====================================================
    // REPORTES SOIRÉE: EXCEL, PDF Y CSV
    // =====================================================

    const reportForm = $('report-form');

    if (reportForm) {
        reportForm.addEventListener('submit', async event => {
            event.preventDefault();

            const args = new URLSearchParams(
                new FormData(reportForm)
            );

            const formato = args.get('formato') || 'excel';
            const tipo = args.get('tipo');
            const desde = args.get('desde') || '';
            const hasta = args.get('hasta') || '';

            if (!['excel', 'pdf', 'csv'].includes(formato)) {
                feedback('Formato de reporte no válido.', true);
                return;
            }

            if (!tipo) {
                feedback('Selecciona un tipo de informe.', true);
                return;
            }

            if (Boolean(desde) !== Boolean(hasta)) {
                feedback(
                    'Selecciona ambas fechas o deja ambas vacías.',
                    true
                );
                return;
            }

            if (desde && hasta && desde > hasta) {
                feedback(
                    'La fecha final debe ser posterior a la inicial.',
                    true
                );
                return;
            }

            args.delete('formato');

            const boton = reportForm.querySelector('button');
            const textoOriginal = boton?.textContent;

            if (boton) {
                boton.disabled = true;
                boton.textContent = 'Generando informe...';
            }

            feedback('Preparando informe Soirée...');

            try {
                const response = await api(
                    'reportes/' + formato + '?' + args.toString()
                );

                const blob = await response.blob();

                const extension =
                    formato === 'excel' ? 'xlsx' : formato;

                const nombre =
                    'soiree_' + tipo + '_' +
                    new Date().toISOString().slice(0, 10) +
                    '.' + extension;

                const url = URL.createObjectURL(blob);

                const enlace = document.createElement('a');
                enlace.href = url;
                enlace.download = nombre;

                document.body.append(enlace);
                enlace.click();
                enlace.remove();

                setTimeout(() => {
                    URL.revokeObjectURL(url);
                }, 3000);

                feedback('Informe generado correctamente.');

            } catch (error) {
                feedback(
                    error.message || 'No se pudo generar el informe.',
                    true
                );
            } finally {
                if (boton) {
                    boton.disabled = false;
                    boton.textContent = textoOriginal;
                }
            }
        });
    }

    // =====================================================
    // SOIRÉE — CIERRE DE SESIÓN SEGURO

    $('so-config-logout')?.addEventListener('click', async () => {
        const confirmado = window.confirm(
            '¿Deseas cerrar tu sesión de Soirée?'
        );

        if (!confirmado) return;

        const boton = $('so-config-logout');
        const mensaje = $('so-config-mensaje');
        const accessToken = token();

        if (!accessToken) {
            if (mensaje) {
                mensaje.textContent = 'No hay una sesión activa.';
            }
            return;
        }

        boton.disabled = true;
        boton.textContent = 'Cerrando sesión...';

        try {
            const response = await fetch(
                '/api/auth/logout-panel',
                {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        Authorization: 'Bearer ' + accessToken
                    }
                }
            );

            if (!response.ok) {
                const error = await response.json()
                    .catch(() => ({}));

                throw new Error(
                    error.message || 'No se pudo cerrar la sesión.'
                );
            }

            localStorage.removeItem('auth_token');
            sessionStorage.removeItem('auth_token');

            window.location.replace('/login');

        } catch (error) {
            if (mensaje) {
                mensaje.textContent = error.message;
            }

            boton.disabled = false;
            boton.textContent = 'Cerrar sesión';
        }
    });

    // =====================================================

    // SOIRÉE — CAMBIAR CONTRASEÑA DESDE CONFIGURACIÓN

    const soPasswordForm = $('so-password-form');

    soPasswordForm?.addEventListener('submit', async event => {
        event.preventDefault();

        const actual = $('so-password-actual')?.value || '';
        const nueva = $('so-password-nueva')?.value || '';
        const confirmar = $('so-password-confirmar')?.value || '';

        const mensaje = $('so-password-mensaje');
        const boton = $('so-password-guardar');

        const informar = (texto, esError = false) => {
            if (!mensaje) return;
            mensaje.textContent = texto;
            mensaje.classList.toggle('is-error', esError);
        };

        if (nueva !== confirmar) {
            informar('Las contraseñas nuevas no coinciden.', true);
            return;
        }

        if (nueva.length < 8) {
            informar(
                'La contraseña debe tener al menos 8 caracteres.',
                true
            );
            return;
        }

        const accessToken = token();

        if (!accessToken) {
            informar('Tu sesión ha caducado.', true);
            return;
        }

        boton.disabled = true;
        boton.textContent = 'Actualizando contraseña...';
        informar('Verificando y actualizando...');

        try {
            const response = await fetch(
                '/api/auth/cambiar-password',
                {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        Authorization: 'Bearer ' + accessToken
                    },
                    body: JSON.stringify({
                        password_actual: actual,
                        password: nueva,
                        password_confirmation: confirmar
                    })
                }
            );

            const resultado = await response.json()
                .catch(() => ({}));

            if (!response.ok) {
                const errores = resultado.errors
                    ? Object.values(resultado.errors)
                        .flat()
                        .join(' ')
                    : '';

                throw new Error(
                    errores ||
                    resultado.message ||
                    'No se pudo actualizar la contraseña.'
                );
            }

            soPasswordForm.reset();

            informar(
                'Contraseña actualizada correctamente.'
            );

        } catch (error) {
            informar(error.message, true);
        } finally {
            boton.disabled = false;
            boton.textContent = 'Actualizar contraseña';
        }
    });

    // =====================================================


    // SOIRÉE — EDITAR MI PERFIL

    function llenarFormularioPerfil(perfil) {
        if (!perfil) return;

        const nombre = $('so-perfil-nombre');
        const apellido = $('so-perfil-apellido');
        const correo = $('so-perfil-email');

        if (nombre) nombre.value = perfil.nombre || '';
        if (apellido) apellido.value = perfil.apellido || '';
        if (correo) correo.value = perfil.email || '';
    }

    $('so-perfil-form')?.addEventListener('submit', async event => {
        event.preventDefault();

        const nombre = $('so-perfil-nombre').value.trim();
        const apellido = $('so-perfil-apellido').value.trim();
        const boton = $('so-perfil-guardar');
        const mensaje = $('so-perfil-mensaje');

        if (!nombre || !apellido) {
            mensaje.textContent = 'Completa el nombre y apellido.';
            return;
        }

        boton.disabled = true;
        boton.textContent = 'Guardando...';
        mensaje.textContent = 'Actualizando tu perfil...';

        try {
            const accessToken = token();

            if (!accessToken) {
                throw new Error('Tu sesión ha caducado.');
            }

            const response = await fetch(
                '/api/auth/actualizar-mi-perfil',
                {
                    method: 'PATCH',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        Authorization: 'Bearer ' + accessToken
                    },
                    body: JSON.stringify({ nombre, apellido })
                }
            );

            const resultado = await response.json()
                .catch(() => ({}));

            if (!response.ok) {
                const detalles = resultado.errors
                    ? Object.values(resultado.errors).flat().join(' ')
                    : '';

                throw new Error(
                    detalles ||
                    resultado.message ||
                    'No se pudieron guardar los cambios.'
                );
            }

            const perfilActualizado = await loadCurrentUser();
            llenarFormularioPerfil(perfilActualizado);
            mensaje.textContent =
                'Información personal actualizada correctamente.';

        } catch (error) {
            mensaje.textContent = error.message;
        } finally {
            boton.disabled = false;
            boton.textContent = 'Guardar cambios';
        }
    });

    // =====================================================


    // SOIRÉE — TARJETAS DESPLEGABLES DE CONFIGURACIÓN

    function configurarTarjetaDesplegable(selector, titulo, descripcion) {
        const tarjeta = document.querySelector(selector);

        if (!tarjeta || tarjeta.querySelector('.so-fold-toggle')) {
            return;
        }

        tarjeta.classList.add('so-fold-card', 'is-collapsed');

        const boton = document.createElement('button');
        boton.type = 'button';
        boton.className = 'so-fold-toggle';
        boton.setAttribute('aria-expanded', 'false');

        const contenido = document.createElement('span');
        contenido.className = 'so-fold-text';

        const nombre = document.createElement('strong');
        nombre.textContent = titulo;

        const detalle = document.createElement('small');
        detalle.textContent = descripcion;

        const flecha = document.createElement('span');
        flecha.className = 'so-fold-arrow';
        flecha.setAttribute('aria-hidden', 'true');
        flecha.textContent = '⌄';

        contenido.append(nombre, detalle);
        boton.append(contenido, flecha);

        tarjeta.prepend(boton);

        boton.addEventListener('click', () => {
            const abrir = tarjeta.classList.contains('is-collapsed');

            tarjeta.classList.toggle('is-collapsed', !abrir);
            boton.setAttribute('aria-expanded', String(abrir));
        });
    }

    configurarTarjetaDesplegable(
        '.so-edit-profile',
        'Editar mi perfil',
        'Nombre, apellido e información personal'
    );

    configurarTarjetaDesplegable(
        '.so-password-card',
        'Cambiar contraseña',
        'Seguridad y protección de mi cuenta'
    );

    // =====================================================

    // INICIO
    // =====================================================

    // No mostrar contenido administrativo antes de validar el token y el rol.
    const nav = $('nav');
    if (nav) nav.hidden = true;
    show('dashboard', false);
    show('banner-panel', false);
    show('reports', false);
    show('coming-soon', false);
    show('so-configuracion', false);

    (async () => {
        try {
            const profile = await loadCurrentUser();
            if (nav) nav.hidden = false;
            navigate(profile.rol === 'organizador' ? 'eventos' : 'resumen');
        } catch (error) {
            currentRole = null;
            feedback(error.message, true);
            show('dashboard', false);
            show('banner-panel', false);
            show('reports', false);
            show('coming-soon', false);
        }
    })();

    // =====================================================
    // SOIRÉE — REPORTES EXCEL Y PDF
    // =====================================================

    async function descargarReporteProfesional(formato) {
        const tipo = $('so-reporte-tipo')?.value;
        const desde = $('so-reporte-desde')?.value || '';
        const hasta = $('so-reporte-hasta')?.value || '';
        const mensaje = $('so-reporte-mensaje');

        if (!tipo || !['excel', 'pdf'].includes(formato)) {
            return;
        }

        if (desde && hasta && desde > hasta) {
            if (mensaje) {
                mensaje.textContent =
                    'La fecha inicial no puede ser posterior a la final.';
            }
            return;
        }

        const params = new URLSearchParams({ tipo });

        if (desde) params.set('desde', desde);
        if (hasta) params.set('hasta', hasta);

        const botonExcel = $('so-descargar-excel');
        const botonPdf = $('so-descargar-pdf');

        if (botonExcel) botonExcel.disabled = true;
        if (botonPdf) botonPdf.disabled = true;

        if (mensaje) {
            mensaje.textContent = 'Preparando el informe...';
        }

        try {
            const tokenActual = localStorage.getItem('auth_token');

            if (!tokenActual) {
                throw new Error('Tu sesión ha caducado.');
            }

            const response = await fetch(
                '/api/admin/reportes/' + formato +
                '?' + params.toString(),
                {
                    headers: {
                        Accept: 'application/json',
                        Authorization: 'Bearer ' + tokenActual
                    }
                }
            );

            if (!response.ok) {
                const error = await response.json().catch(() => ({}));

                let mensajeError = error.message ||
                    'No se pudo generar el informe.';

                if (error.errors) {
                    mensajeError = Object.values(error.errors)
                        .flat()
                        .join(' ');
                }

                throw new Error(mensajeError);
            }

            const archivo = await response.blob();

            const extension = formato === 'excel' ? 'xlsx' : 'pdf';
            const nombre = 'soiree_' + tipo + '_' +
                new Date().toISOString().slice(0, 10) +
                '.' + extension;

            const url = URL.createObjectURL(archivo);
            const enlace = document.createElement('a');

            enlace.href = url;
            enlace.download = nombre;

            document.body.append(enlace);
            enlace.click();
            enlace.remove();

            // Liberar la URL cuando termine el evento actual.
            setTimeout(() => URL.revokeObjectURL(url), 1000);

            if (mensaje) {
                mensaje.textContent =
                    'Informe generado correctamente.';
            }
        } catch (error) {
            if (mensaje) {
                mensaje.textContent = error.message;
            }
        } finally {
            if (botonExcel) botonExcel.disabled = false;
            if (botonPdf) botonPdf.disabled = false;
        }
    }

    $('so-descargar-excel')?.addEventListener(
        'click',
        () => descargarReporteProfesional('excel')
    );

    $('so-descargar-pdf')?.addEventListener(
        'click',
        () => descargarReporteProfesional('pdf')
    );

    // Para el catálogo de eventos (admin-catalogo-eventos.js): mismos botones que la tabla
    window.AdminEventos = {
        acciones: item => eventActionGroup(item, { catalogo: true })
    };

})();
