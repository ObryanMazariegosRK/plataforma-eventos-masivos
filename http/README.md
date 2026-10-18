# Peticiones HTTP manuales

Archivos `.http` para probar la API a mano con la extensión **REST Client** de VS Code
(`humao.rest-client`). Abre el archivo y haz clic en **Send Request** sobre cada petición.

Siempre envía `Accept: application/json`: sin ese header, un error de validación
responde con redirección (302) en vez del JSON 422.

## Datos mínimos para `reservas.http`

1. **Usuario:** `POST /api/reservas` requiere token, así que el usuario debe estar
   **registrado y verificado**. Créalo con `auth.http` o en http://localhost:8090/registro.
   (No lo insertes por SQL: no tendría un password hasheado ni el correo verificado.)
2. **Evento con asientos y precio:** la reserva se hace eligiendo filas de `inventario`
   en estado `disponible`, y el total sale de la tabla `precios`. Desde Adminer
   (http://localhost:8082 → servidor `mysql`, usuario `eventos`, contraseña `root`, base `eventos`)
   → **SQL command**, para un evento existente (cambia `4` por su id):

```sql
-- localidad (zona) en el recinto del evento, si no tiene ninguna
INSERT INTO localidades (recinto_id, nombre, tipo, capacidad, created_at, updated_at)
SELECT e.recinto_id, 'General', 'general', 100, NOW(), NOW()
FROM eventos e
WHERE e.id = 4 AND NOT EXISTS (SELECT 1 FROM localidades l WHERE l.recinto_id = e.recinto_id);

-- precio de esa localidad para el evento
INSERT INTO precios (evento_id, localidad_id, precio, created_at, updated_at)
SELECT 4, l.id, 125, NOW(), NOW()
FROM localidades l JOIN eventos e ON e.recinto_id = l.recinto_id
WHERE e.id = 4 AND NOT EXISTS (SELECT 1 FROM precios p WHERE p.evento_id = 4 AND p.localidad_id = l.id)
LIMIT 1;

-- 5 cupos disponibles (localidad general, sin asiento numerado)
INSERT INTO inventario (evento_id, localidad_id, estado, created_at, updated_at)
SELECT 4, l.id, 'disponible', NOW(), NOW()
FROM localidades l JOIN eventos e ON e.recinto_id = l.recinto_id
CROSS JOIN (SELECT 1 n UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4 UNION ALL SELECT 5) cinco
WHERE e.id = 4
LIMIT 5;

-- ids disponibles para poner en @inventarioIds
SELECT id FROM inventario WHERE evento_id = 4 AND estado = 'disponible';
```

Luego ajusta `@eventoId`, `@inventarioIds`, `@email` y `@password` en `reservas.http` y ejecuta primero la petición 0 (login).
Las reservas vencen en 5 minutos y sus asientos vuelven a `disponible` (tarea programada de Reservas).

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
