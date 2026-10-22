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
            'Próximamente: avisos y comunicaciones.'
        ],
        historial: [
            'Historial',
            'Próximamente: auditoría de operaciones.'
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
        'notificaciones',
        'historial',
        'configuracion'
    ]);

    // El rol se verifica con /api/auth/perfil antes de habilitar la navegación.
    let currentRole = null;
    const operativeSections = new Set([
        'eventos', 'banner', 'notificaciones', 'configuracion'
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

        return profile;
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
                el('span', item.estado),
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
                'Administradores',
                totals.administradores,
                'Cuentas admin',
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
                'Registros de pagos',
                '▣',
                'blue'
            ),
            metric(
                'Monto de pagos',
                currency.format(
                    Number(totals.monto_pagos_registrados || 0)
                ),
                'Sin distinguir estado',
                '◈',
                'orange'
            )
        );

        bars('reservation-bars', data.reservas_por_estado);
        bars('event-bars', data.eventos_por_estado);

        $('reservas-total').textContent =
            'Total: ' + totals.reservas;

        $('eventos-total').textContent =
            'Total: ' + totals.eventos;

        return data.reservas_recientes || [];
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

    function renderEventActions(item) {
        const cell = el('td');

        const group = el(
            'div',
            undefined,
            'eventos-row-actions'
        );

        group.append(
            actionButton(
                'Ver',
                'evento-action',
                () => viewEvent(item.id)
            )
        );

        if (item.estado === 'borrador') {
            group.append(
                actionButton(
                    'Editar',
                    'evento-action',
                    () => editEvent(item.id)
                ),
                // Inventario: sin precio en todas las localidades no se puede publicar
                actionButton(
                    'Precios',
                    'evento-action',
                    () => openPrices(item.id)
                ),
                actionButton(
                    'Publicar',
                    'evento-action publish',
                    () => publishEvent(item.id)
                )
            );
        } else {
            group.append(
                actionButton(
                    'Cambiar imagen',
                    'evento-action',
                    () => changeEventImage(item.id)
                ),
                // Inventario: plano de ocupación en vivo
                actionButton(
                    'Ver asientos',
                    'evento-action',
                    () => { window.location.href = '/admin/eventos/' + item.id + '/asientos'; }
                )
            );
        }

        cell.append(group);
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

        if (kind === 'eventos' || kind === 'usuarios') {
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
                (['eventos', 'usuarios'].includes(kind) ? 1 : 0);

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
        show('evento-form-panel', false);

        const form = $('evento-form');

        if (form) {
            form.reset();
        }

        clearFormFeedback();
    }

    function updateSections() {
        const isOverview = section === 'resumen';
        const isEvents = section === 'eventos';
        const isReports = section === 'reportes';
        const isBanner = section === 'banner';   // lo maneja admin-banner.js
        const isPending = pendingSections.has(section);

        show('dashboard', !isReports && !isPending && !isBanner);
        show('banner-panel', isBanner);
        show('overview-content', isOverview);
        show('eventos-tools', isEvents);
        show('reports', isReports);
        show('coming-soon', isPending);

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

    async function openEventForm(evento = null) {
        if (section !== 'eventos') {
            return;
        }

        editingEventId = evento ? evento.id : null;

        const form = $('evento-form');
        form.reset();

        clearFormFeedback();

        $('evento-form-title').textContent = evento
            ? 'Editar evento'
            : 'Crear nuevo evento';

        $('btn-guardar-evento').textContent = evento
            ? 'Guardar cambios'
            : 'Guardar borrador';

        show('evento-form-panel', true);

        if (evento) {
            form.elements.namedItem('nombre').value =
                evento.nombre || '';

            form.elements.namedItem('descripcion').value =
                evento.descripcion || '';

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
        } catch (error) {
            formFeedback(error.message, true);
        }

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
            const parsed = new URL(value);
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

            const path = isEditing
                ? 'eventos/' + editingEventId
                : 'eventos';

            const method = isEditing ? 'PUT' : 'POST';

            savingEvent = true;

            const saveButton = $('btn-guardar-evento');

            saveButton.disabled = true;
            saveButton.textContent = 'Guardando...';

            clearFormFeedback();

            try {
                const response = await api(path, {
                    method,
                    body: JSON.stringify(payload)
                });

                const result = await response.json();

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
        const version = ++requestVersion;

        updateHeader();
        updateSections();

        $('thead').replaceChildren();
        $('tbody').replaceChildren();
        $('pagination').replaceChildren();

        if (
            pendingSections.has(section) ||
            section === 'reportes' ||
            section === 'banner'
        ) {
            clearFeedback();
            return;
        }

        feedback('Consultando datos reales de MySQL...');

        try {
            let recent = [];

            if (section === 'resumen') {
                recent = await loadSummary();
            }

            if (version !== requestVersion) {
                return;
            }

            const kind = section === 'resumen'
                ? 'reservas'
                : section;

            $('table-title').textContent =
                section === 'resumen'
                    ? 'Reservas recientes'
                    : titles[section][0];

            $('table-subtitle').textContent =
                section === 'resumen'
                    ? 'Últimos movimientos registrados en MySQL.'
                    : 'Registros disponibles en el sistema.';

            $('view-all').hidden =
                section !== 'resumen';

            renderFilters();

            if (section === 'resumen') {
                renderTable(recent, 'reservas');
            } else {
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
            }

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
    // REPORTES CSV
    // =====================================================

    const reportForm = $('report-form');

    if (reportForm) {
        reportForm.addEventListener(
            'submit',
            async event => {
                event.preventDefault();

                const args = new URLSearchParams(
                    new FormData(reportForm)
                );

                const from = args.get('desde');
                const to = args.get('hasta');

                if (Boolean(from) !== Boolean(to)) {
                    feedback(
                        'Selecciona ambas fechas o deja ambas vacías.',
                        true
                    );
                    return;
                }

                if (from && to && from > to) {
                    feedback(
                        'La fecha final debe ser igual o posterior a la inicial.',
                        true
                    );
                    return;
                }

                feedback('Preparando reporte CSV...');

                try {
                    const response = await api(
                        'reportes/csv?' + args.toString()
                    );

                    const blob = await response.blob();
                    const url = URL.createObjectURL(blob);

                    const link = document.createElement('a');

                    link.href = url;
                    link.download =
                        'pulso_' + args.get('tipo') + '.csv';

                    document.body.append(link);
                    link.click();
                    link.remove();

                    setTimeout(() => {
                        URL.revokeObjectURL(url);
                    }, 3000);

                    clearFeedback();

                } catch (error) {
                    feedback(error.message, true);
                }
            }
        );
    }

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

})();
