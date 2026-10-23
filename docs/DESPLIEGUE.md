# Despliegue en producción (Coolify)

Guía para publicar Soirée en el servidor de Azure donde ya corre la tienda de ropa (Amerishop),
administrado con **Coolify**. Las dos aplicaciones conviven: Traefik (el "recepcionista" de
Coolify) recibe todas las visitas por los puertos 80/443 y, según el nombre que se escribió en el
navegador, las manda a la tienda o a Soirée.

| | Tienda de ropa | Soirée |
|---|---|---|
| Dominio | `amerishop.eastus2.cloudapp.azure.com` (etiqueta DNS de Azure) | `soiree.20.7.59.197.sslip.io` (gratis, ver abajo) |
| Rama | la que tenga configurada | `develop` (luego `main`) |

> **sslip.io** convierte cualquier IP en un dominio sin registrar nada: `soiree.20.7.59.197.sslip.io`
> siempre apunta a `20.7.59.197`. Coolify le pone https (Let's Encrypt) igual que a la tienda.
> Azure solo da una etiqueta `...cloudapp.azure.com` por IP, por eso Soirée usa sslip.io.

## Cómo queda en el servidor

`docker-compose.prod.yml` levanta 6 contenedores:

| Servicio | Qué hace |
|---|---|
| `web` | nginx: recibe las visitas, entrega CSS/JS/imágenes y pasa lo demás a PHP. Es el **único** que recibe tráfico. |
| `app` | PHP-FPM con Laravel (12 trabajadores a la vez). Al arrancar: cachea la configuración, migra, carga recintos y pasarela (`db:seed`) e instala la bitácora. |
| `queue` | La cola de trabajos: envía correos y notificaciones push, emite boletos y avisa al personal. |
| `scheduler` | Tareas cada minuto: libera reservas vencidas, recordatorios, limpieza de fotos. |
| `mysql` | Base principal y base de la bitácora. Sin puertos abiertos a internet. |
| `redis` | Cola de trabajos, caché (órdenes de la pasarela) y sesiones. Sin puertos abiertos. |

Datos permanentes (sobreviven a cada actualización): volúmenes `storage` (fotos de eventos,
banner, logs), `mysql-data` y `redis-data`.

El código **no** se monta desde una carpeta como en desarrollo: va dentro de la imagen
(`docker/production/Dockerfile`), así que cada despliegue construye una imagen nueva
(unos minutos; usa los 2 núcleos del servidor: no despliegues durante una demostración).

---

## Paso 1. Junta las variables (en tu computadora)

Ninguna se sube al repositorio; se pegan en Coolify en el paso 4. Lista explicada en
`.env.production.example`.

| Variable | Cómo obtenerla |
|---|---|
| `APP_KEY` | `docker compose exec app php artisan key:generate --show` — genérala **una vez** y guárdala. |
| `APP_URL` | `https://soiree.20.7.59.197.sslip.io` |
| `DB_PASSWORD`, `DB_ROOT_PASSWORD`, `BITACORA_DB_PASSWORD` | Inventa tres contraseñas largas y distintas (un gestor de contraseñas ayuda). Evita `#`, `$` y comillas. |
| `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY` | `docker compose exec app php artisan webpush:vapid --show` (no modifica tu `.env`). Llaves nuevas para el servidor. |
| `MAIL_USERNAME`, `MAIL_FROM_ADDRESS` | El Gmail que enviará los correos. |
| `MAIL_PASSWORD` | Contraseña de aplicación de esa cuenta (ver abajo), 16 letras **sin espacios**. |
| `MARCA_CONTACTO` | Correo de contacto que verán los clientes (puede ser el mismo Gmail). |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` | Opcional: los de tu `.env` local (ver paso 6). |

**Contraseña de aplicación de Gmail:** en la cuenta de Google → Seguridad → activa la
**verificación en 2 pasos** → entra a <https://myaccount.google.com/apppasswords> → nombre
"Soirée" → **Crear**. Copia las 16 letras sin espacios. Si la cambias o la borras, los correos
dejan de salir hasta actualizar `MAIL_PASSWORD` en Coolify.

## Paso 2. Dar acceso de Coolify al repositorio

En Coolify → **Sources** (Fuentes) revisa con qué se conectó la tienda:
- **GitHub App:** en GitHub → Settings → Applications → la app de Coolify → **Configure** →
  en "Repository access" agrega `plataforma-eventos-masivos` (si es de otra cuenta, el dueño del
  repositorio debe instalar la app ahí).
- **Repositorio público:** no hace falta nada; se usa la URL directamente.

## Paso 3. Crear el recurso

1. Coolify → **Projects** → `my-first-project` (o crea uno nuevo, p. ej. "Soirée") →
   entorno `production` → **+ New** → **Resource**.
2. Elige el repositorio (con la GitHub App: *Private Repository (with GitHub App)*; si es
   público: *Public Repository* y pega la URL).
3. Configuración:
   - **Branch:** `develop`
   - **Build Pack:** `Docker Compose`
   - **Base Directory:** `/`
   - **Docker Compose Location:** `/docker-compose.prod.yml`
4. Guarda. Coolify lee el archivo y muestra los 6 servicios.
5. En el servicio **web** → **Domains**: `https://soiree.20.7.59.197.sslip.io`
   (con `https://`: así Coolify pide el certificado). Los demás servicios **sin** dominio.

## Paso 4. Variables de entorno

Pestaña **Environment Variables** → modo **Developer view** (texto) → pega las variables del
paso 1 en formato `NOMBRE=valor`, una por línea → **Save**. Las obligatorias aparecen marcadas;
si falta alguna, el despliegue se detiene con "Falta …".

## Paso 5. Desplegar

**Deploy**. La primera vez tarda más (descarga PHP, MySQL, Redis y construye la imagen).
En **Logs** del servicio `app` deberías ver las migraciones, `db:seed` y
"Base eventos_bitacora y usuario eventos_bitacora (SELECT, INSERT) listos".

Abre `https://soiree.20.7.59.197.sslip.io`: debe verse la cartelera con el candado 🔒.

**Crear el administrador:** Coolify → el recurso → **Terminal** → contenedor `app` →

```bash
php artisan usuario:crear-admin
```

(pregunta correo, nombre y la contraseña de forma oculta). Entra por
`https://soiree.20.7.59.197.sslip.io/admin/login`.

## Paso 6. Google (opcional)

<https://console.cloud.google.com> → APIs y servicios → Credenciales → el cliente OAuth →
**URI de redireccionamiento autorizados** → agrega
`https://soiree.20.7.59.197.sslip.io/auth/google/callback` (sin borrar la de localhost).

## Paso 7. Actualizaciones automáticas

Por defecto se actualiza con **Redeploy** en Coolify. Para que se despliegue solo con cada push
a `develop`: en el recurso → **Advanced** → activa **Auto Deploy** (con GitHub App funciona
directo; con repositorio público hay que configurar el webhook que indica Coolify en
**Webhooks**).

Para pasar a `main` más adelante: cambia **Branch** a `main` y **Redeploy**.

## Lista de prueba (víspera de la demostración)

- [ ] Registro de un cliente: llega el código de verificación al correo real.
- [ ] Compra con la tarjeta de prueba 4242 4242 4242 4242: llegan el correo y el push con los boletos.
- [ ] Boletos con QR en Mis compras; escanear el QR con la cámara del celular abre `/b`.
- [ ] Panel → Control de acceso con la cámara (necesita https: ya lo tiene).
- [ ] Activar la campana en el celular y recibir un push.
- [ ] Panel → Bitácora muestra los registros.
- [ ] Subir una foto de evento y el banner: siguen ahí después de un Redeploy.

## Problemas frecuentes

| Síntoma | Qué revisar |
|---|---|
| El despliegue se detiene con "Falta …" | Esa variable no está en Environment Variables. |
| No llegan correos | Logs del servicio `queue`. `MAIL_PASSWORD` debe ser la contraseña de aplicación sin espacios. |
| "Bitácora no disponible" | Logs de `app` al arrancar: `DB_ROOT_PASSWORD` y `BITACORA_DB_PASSWORD`. Se puede reintentar en Terminal → `app` → `php artisan bitacora:instalar`. |
| Error 500 | Terminal → `app` → `php artisan about` y los Logs de `app` (en producción los errores no se muestran en pantalla). |
| La página carga sin estilos o con "contenido mixto" | `APP_URL` debe empezar con `https://`. |
| Cambié una variable y no se aplica | Las variables se leen al arrancar: **Restart** (o Redeploy). |

> Seguridad: el panel de Coolify responde en `http://20.7.59.197:8000` sin https. Conviene
> limitar el puerto 8000 en el grupo de seguridad de red (NSG) de Azure a tu IP, o darle a Coolify
> un dominio con https y cerrar ese puerto.
