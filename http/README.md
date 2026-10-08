# Peticiones HTTP manuales

Archivos `.http` para probar la API a mano con la extensión **REST Client** de VS Code
(`humao.rest-client`). Abre el archivo y haz clic en **Send Request** sobre cada petición.

Siempre envía `Accept: application/json`: sin ese header, un error de validación
responde con redirección (302) en vez del JSON 422.

## Datos mínimos para `reservas.http`

1. **Usuario:** `POST /api/reservas` requiere token, así que el usuario debe estar
   **registrado y verificado**. Créalo con `auth.http` o en http://localhost:8090/registro.
   (No lo insertes por SQL: no tendría un password hasheado ni el correo verificado,
   y no podría iniciar sesión.)
2. **Evento:** se crea a mano desde Adminer
   (http://localhost:8082 → servidor `mysql`, usuario `eventos`, contraseña `root`, base `eventos`)
   con **SQL command**:

```sql
INSERT INTO recintos (nombre, direccion, ciudad, created_at, updated_at)
VALUES ('Estadio', 'Zona 1', 'Guatemala', NOW(), NOW());

INSERT INTO eventos (recinto_id, nombre, fecha_evento, estado, created_at, updated_at)
SELECT id, 'Concierto', NOW() + INTERVAL 30 DAY, 'publicado', NOW(), NOW()
FROM recintos WHERE nombre = 'Estadio' ORDER BY id DESC LIMIT 1;

-- ver el id del evento para ponerlo en el .http
SELECT MAX(id) AS evento_id FROM eventos;
```

(No se usa `LAST_INSERT_ID()` porque Adminer abre una conexión nueva en cada ejecución y
ese valor solo vive dentro de la misma conexión.)

Luego ajusta `@eventoId`, `@email` y `@password` en `reservas.http` y ejecuta primero la petición 0 (login).

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
