# Peticiones HTTP manuales

Archivos `.http` para probar la API a mano con la extensión **REST Client** de VS Code
(`humao.rest-client`). Abre el archivo y haz clic en **Send Request** sobre cada petición.

Siempre envía `Accept: application/json`: sin ese header, un error de validación
responde con redirección (302) en vez del JSON 422.

## Datos mínimos para `reservas.http`

Los recintos son fijos y el inventario se crea solo al publicar un evento:

1. Una vez: `docker compose exec app php artisan db:seed --class=RecintoSeeder`
   (Teatro Soirée y Auditorio Central, con sus zonas y asientos).
2. En el panel de administración (`/admin`): crea un evento eligiendo uno de esos recintos,
   pon **precio a todas sus zonas** y **publícalo**. Al publicar se generan sus lugares a la venta.
3. Mira el mapa en `/eventos/{id}/asientos` o pide `GET /api/eventos/{id}/disponibilidad`
   para conocer los `inventario_id` disponibles.

Para un evento que ya estaba publicado antes de esto: `docker compose exec app php artisan inventario:generar {id}`.

El usuario debe estar **registrado y verificado** (créalo con `auth.http` o en `/registro`).

## `auth.http`

No necesita datos previos: la petición 1 crea el usuario. El código de verificación
llega a **Mailpit** (http://localhost:8026); cópialo en `@codigo` y ejecuta la 2.

Las peticiones protegidas usan el token del login automáticamente
(`# @name login` + `{{login.response.body.token}}`): ejecuta primero la 4.

## Roles y bloqueo (desde Adminer → SQL command)

No hay pantalla de administración todavía; se cambia directo en la BD.
El cambio aplica en la **siguiente petición** del usuario, sin que cierre sesión.

```sql
-- convertir un usuario ya registrado en administrador (u 'organizador')
UPDATE usuarios SET rol = 'admin' WHERE email = 'ana@example.com';

-- bloquear / desbloquear (bloqueado → todas sus peticiones con token responden 403)
UPDATE usuarios SET estado = 'bloqueado' WHERE email = 'ana@example.com';
UPDATE usuarios SET estado = 'activo'    WHERE email = 'ana@example.com';
```

## Login con Google

No se prueba con `.http` (son redirecciones del navegador): abre http://localhost:8090/login
y usa **Continuar con Google**. Requiere en `.env`:

```
GOOGLE_CLIENT_ID=...apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=...
GOOGLE_REDIRECT_URI=http://localhost:8090/auth/google/callback
```

Mientras la app de Google esté en modo **Prueba**, solo pueden entrar las cuentas agregadas
como *usuarios de prueba* en Google Cloud Console.

## Pagos (pasarela simulada)

1. Una vez: `docker compose exec app php artisan db:seed --class=PasarelaSeeder` (crea la pasarela `simulada`).
2. Crea una reserva con `reservas.http` (con asientos, ver arriba) y anota su `id`.
3. En el navegador (con sesión iniciada): http://localhost:8090/pagar?reserva=ID → **Ir a pagar**
   → en la pasarela simulada elige **Aprobar**, **Rechazar** o **Cancelar**.

Al aprobar se emiten los boletos (`SELECT * FROM boletos WHERE reserva_id = ID;`) y llega el correo a Mailpit.
