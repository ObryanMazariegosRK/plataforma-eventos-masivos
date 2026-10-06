# Plataforma de Eventos Masivos

Sistema de venta masiva de tickets — Análisis de Sistemas II (UMG).
Monolito modular en **Laravel + PHP 8.4 + MySQL 8 + Redis**, con arquitectura
limpia por módulo, orquestado con **Docker**.

---

## 1. Requisitos
- Docker Desktop (en Windows, con WSL2)
- Git

## 2. Primer arranque (una sola vez al clonar)

```bash
# 1) Clonar
git clone <url-del-repo> plataforma-eventos
cd plataforma-eventos

# 2) Copiar variables de entorno
cp .env.example .env

# 3) Levantar los contenedores (construye las imágenes)
docker compose up -d --build

# 4) Instalar dependencias de PHP
docker compose exec app composer install

# 5) Generar la llave de la app
docker compose exec app php artisan key:generate

# 6) Permisos de escritura (evita el error 500)
docker compose exec app chmod -R 777 storage bootstrap/cache

# 7) Crear las tablas
docker compose exec app php artisan migrate
```

Listo: la app queda en **http://localhost:8090**

## 3. Uso diario

```bash
docker compose up -d            # levantar
docker compose down             # bajar
docker compose exec app bash    # entrar al contenedor de la app
docker compose logs -f queue    # ver el worker de colas
```

## 4. Servicios y puertos

| Servicio | URL / Puerto | Para qué |
|---|---|---|
| App (web) | http://localhost:8090 | la aplicación |
| Adminer (BD) | http://localhost:8082 | ver/editar la base de datos |
| Mailpit (correos) | http://localhost:8026 | correos enviados en dev |
| MySQL | localhost:3308 | conexión directa a la BD |

**Credenciales de BD (dev):** servidor `mysql`, usuario `eventos`, contraseña `root`, base `eventos`.

## 5. Contenedores

| Contenedor | Rol |
|---|---|
| `app` | PHP 8.4-FPM + Laravel |
| `nginx` | servidor web |
| `mysql` | base de datos (InnoDB / ACID) |
| `redis` | cache, sesiones, colas, locks |
| `queue` | worker de jobs (TTL de asiento, reintentos de pago, correos) |
| `scheduler` | corre `schedule:run` cada minuto (barre reservas vencidas) |
| `mailpit` | captura de correos en desarrollo |
| `adminer` | interfaz web de la BD |

## 6. Arquitectura del código (monolito modular + capas)

```
app/
  Domain/
    Entities/<Modulo>/       # entidades de negocio (auto-validadas)
    Abstractions/<Modulo>/   # interfaces de repositorio
    Enums/<Modulo>/          # enums del dominio
  Application/<Modulo>/       # casos de uso
  Data/<Modulo>/             # implementaciones de repositorio (Eloquent)
  Infrastructure/            # servicios de terceros (correo, pasarelas)
  Models/<Modulo>/           # modelos Eloquent (persistencia)
  Http/<Modulo>/             # controladores, requests
```

### Reparto por módulos
| Persona | Módulos |
|---|---|
| **Christopher** | Inventario, Reservas, Pagos, Pasarelas (motor de compra) |
| **Freyder** | Reembolsos, Boletos, Notificaciones, Transacciones (post-venta) |
| **Luis** | Catálogo, Recintos, Auth, Cola Virtual, Admin (frente y config) |

## 7. Git

```
main             -> estable
develop          -> integración
feat/<modulo>    -> rama por módulo/persona  (ej. feat/inventario)
```

Primero se mergea el cimiento (BD + dominio) en `develop`; luego cada quien
ramifica desde ahí.