# Peticiones HTTP manuales

Archivos `.http` para probar la API a mano con la extensión **REST Client** de VS Code
(`humao.rest-client`). Abre el archivo y haz clic en **Send Request** sobre cada petición.

Siempre envía `Accept: application/json`: sin ese header, un error de validación
responde con redirección (302) en vez del JSON 422.

## Datos mínimos para `reservas.http`

Mientras no haya seeders, crea un usuario y un evento desde Adminer
(http://localhost:8082 → servidor `mysql`, usuario `eventos`, contraseña `root`, base `eventos`)
con **SQL command**:

```sql
INSERT INTO usuarios (nombre, apellido, email, password, created_at, updated_at)
VALUES ('Ana', 'López', 'ana@example.com', 'x', NOW(), NOW());

INSERT INTO recintos (nombre, direccion, ciudad, created_at, updated_at)
VALUES ('Estadio', 'Zona 1', 'Guatemala', NOW(), NOW());

INSERT INTO eventos (recinto_id, nombre, fecha_evento, estado, created_at, updated_at)
SELECT id, 'Concierto', NOW() + INTERVAL 30 DAY, 'publicado', NOW(), NOW()
FROM recintos WHERE nombre = 'Estadio' ORDER BY id DESC LIMIT 1;

-- ver los ids generados para ponerlos en el .http
SELECT (SELECT MAX(id) FROM usuarios) AS usuario_id, (SELECT MAX(id) FROM eventos) AS evento_id;
```

(No se usa `LAST_INSERT_ID()` porque Adminer abre una conexión nueva en cada ejecución y
ese valor solo vive dentro de la misma conexión.)

Luego ajusta `@usuarioId` y `@eventoId` en el `.http` con los ids que se generaron.
