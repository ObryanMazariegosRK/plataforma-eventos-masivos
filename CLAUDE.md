# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Mass ticket-sales platform ("Plataforma de Eventos Masivos", Análisis de Sistemas II, UMG). Modular monolith on Laravel 13 / PHP 8.4 / MySQL 8 / Redis, run entirely in Docker. Code, identifiers, comments and commit messages are in **Spanish** — keep that convention.

## Commands

Everything runs inside the `app` container (host PHP is not assumed):

```bash
docker compose up -d --build                     # start stack (first time: --build)
docker compose exec app composer install
docker compose exec app php artisan migrate      # or migrate:fresh while schema is in flux
docker compose exec app php artisan test         # all tests
docker compose exec app php artisan test --filter=NombreDelTest   # single test/method
docker compose exec app php artisan test tests/Unit/AlgoTest.php  # single file
docker compose exec app ./vendor/bin/pint        # code style (Laravel Pint)
docker compose logs -f queue                     # queue worker output
docker compose restart queue                     # REQUIRED after changing code used by jobs/queued mail (the worker keeps old code in memory)
```

If you get HTTP 500 on a fresh clone: `docker compose exec app chmod -R 777 storage bootstrap/cache`.

Services: app http://localhost:8090, Adminer http://localhost:8082, Mailpit http://localhost:8026, MySQL on host port 3308 (user `eventos` / `root`, db `eventos`). Inside the Docker network, hosts are `mysql` and `redis`.

Tests use SQLite in-memory, `QUEUE_CONNECTION=sync` and array cache/session (see `phpunit.xml`), so they do not need the MySQL/Redis containers — but MySQL-specific migration features must stay SQLite-compatible for tests to pass.

## Architecture

Clean architecture, sliced per module. Each layer has one folder per module (`Admin, Auth, Boletos, Catalogo, ColaVirtual, Inventario, Notificaciones, Pagos, Pasarelas, Recintos, Reembolsos, Reservas, Transacciones`), scaffolded by `scripts/create-modules.sh` (empty dirs hold `.gitkeep`):

- `app/Domain/Entities/<Modulo>/` — plain PHP entities (no Eloquent). Constructor-promoted private props, a private `validar()` called from the constructor that throws `DomainException`, behaviour methods that enforce state transitions (e.g. `Reserva::confirmar()`, `cancelar()`, `vencer()`), and explicit getters. `Reserva` is the reference example.
- `app/Domain/Enums/<Modulo>/` — string-backed enums for states/types (`EstadoReserva`, `EstadoAsiento`, …); values match what's stored in DB `estado` columns.
- `app/Domain/Abstractions/<Modulo>/` — repository interfaces (`I<Entidad>Repository`) and service interfaces (e.g. `IPasswordHasher`, `ITokenService`, `IEnviadorCorreoAuth`).
- `app/Domain/Exceptions/<Modulo>/` — business exceptions extending `ReglaDeNegocioException` (itself a `DomainException`). The domain knows nothing about HTTP: the exception → `[status, error code]` map lives in `bootstrap/app.php` (`withExceptions`). Any other `DomainException` (e.g. from an entity's `validar()`) renders as 422. **Controllers never use try/catch** — throw, and register new exceptions in that map.
- `app/Application/Abstractions/<Modulo>/` — use-case interfaces (`I<Accion>UseCase`, single `execute()` method). Controllers depend on these interfaces, never on concrete use cases.
- `app/Application/UseCases/<Modulo>/` — use-case implementations (`<Accion>UseCase`).
- `app/Application/DTOs/<Modulo>/` — readonly input DTOs.
- `app/Data/<Modulo>/` — **all repository implementations go here** (`<Entidad>Repository`, mapping Entity↔Model). This is the only layer, besides `Models`, that touches Eloquent. The folders are created per module as repositories are added.
- `app/Infrastructure/<Modulo>/` — implementations of service interfaces backed by the framework or third parties (hashing, Sanctum tokens, mail/Mailables, payment gateway adapters). **Never put repositories here.**
- `app/Models/<Modulo>/` — Eloquent models (persistence only).
- `app/Http/Controllers|Requests/<Modulo>/` — HTTP layer.

Bind every interface to its implementation in `AppServiceProvider::register()` — both the repository (`Domain/Abstractions` → `Data`) and the use case (`Application/Abstractions` → `Application/UseCases`). Read settings via `config()` (e.g. `config/reservas.php`, `config/autenticacion.php`), never `env()` outside `config/`. Use cases don't call `config()` either: the controller reads it and passes the value in the DTO (e.g. `ttlSegundos`, `minutosValidezCodigo`), so use cases stay unit-testable with plain PHPUnit and in-memory fakes (`tests/Fakes/<Modulo>/`).

Note: the README's architecture tree is slightly out of date (it shows use cases directly under `Application/<Modulo>/`); the actual folders are as listed above.

Most modules are still skeletons: domain entities/enums and migrations exist, but most use cases, repositories, models and controllers are unimplemented. Complete vertical slices: `POST /api/reservas` (`CrearReservaUseCase`; behind `auth:sanctum`, the user id comes from `$request->user()`, never from the request body; receives `evento_id` + `inventario_ids` of `disponible` seats, locks them and computes the total from `precios`; `ExpirarReservasVencidasUseCase` runs every minute and frees seats of expired reservas) and the Auth module under `/api/auth/*`. Scheduled tasks are declared in `routes/console.php` (e.g. `sanctum:prune-expired` daily). API routes live in `routes/api.php` (registered in `bootstrap/app.php`, prefixed `/api`, JSON exceptions).

### Authentication

Laravel Sanctum bearer tokens. The authenticatable model is `App\Models\Auth\Usuario` (table `usuarios`, set in `config/auth.php`). Flow: `registro` (always creates `cliente`, emails a 6-digit code — see Mailpit) → `verificar-correo` (returns a token) → `login` (rejects wrong credentials 401, blocked 403, unverified 403) → `logout`/`perfil` behind `auth:sanctum`. Password recovery: `olvide-password` (separate `codigo_recuperacion`) → `restablecer-password` (revokes all tokens). Codes are destroyed after `Usuario::MAX_INTENTOS_CODIGO` failed attempts, and the code endpoints are throttled. `VerificarUsuarioActivo` is appended to the whole `api` middleware group, so a `bloqueado` user is rejected (403) on every request even with a still-valid token. Logged-in users can `cambiar-password` (requires the current password; keeps the current token, revokes the rest) and `logout-todos`. Password rules live in `Password::defaults()` (`AppServiceProvider::boot`). Auth mailables implement `ShouldQueue`: they go to Redis and the `queue` container sends them — in tests assert with `Mail::assertQueued`, not `assertSent`. No admin UI yet: promote/block users with SQL (see `http/README.md`). Google login (Laravel Socialite) uses WEB routes `/auth/google/redirect` → `/auth/google/callback` (`GoogleAuthController`; Socialite stays in the controller, the use case `IniciarSesionConGoogleUseCase` receives a DTO). It finds by `google_id`, else links by email (wiping the password of an unverified local account to prevent pre-hijacking), else creates a verified `cliente` with null password. The token is handed to the browser in the URL fragment (`/oauth/google#token=...`). Errors in the callback are turned into a redirect to `/login?tipo=error&mensaje=...` by a render callback in `bootstrap/app.php`. Requires `GOOGLE_CLIENT_ID/SECRET/REDIRECT_URI` in `.env`; tests mock `Socialite::driver->user`. Role guard: `->middleware(['auth:sanctum', 'rol:admin,organizador'])` (`App\Http\Middleware\VerificarRol`). Error responses are `{"message", "error"}` where `error` is a stable code for the frontend.

### Payments (Pagos / Pasarelas)

Two-step flow like real gateways (the frontend's `Auth.irAPagar(reservaId)` in `autenticacion.js` starts it right after reserving and from the retry links, skipping the `/pagar` chooser when exactly one gateway is active; the gateway page shows a countdown to the reserva's expiry): `POST /api/pagos` (`IniciarPagoUseCase`: PENDIENTE pago, amount always from the reserva, gateway order → `url_aprobacion`) → the browser approves on the gateway page → `/pagos/retorno?pago=ID` calls `POST /api/pagos/{id}/confirmar` (`ConfirmarPagoUseCase`). Confirm runs in ONE transaction (`IUnidadDeTrabajo`, single attempt — never auto-retry a charge) with pago and reserva locked: reserva confirmed, inventario items sold, then the **capture is the last step**; a rejected capture throws `PagoRechazadoException` (402), rolls back, and a second transaction marks the pago `rechazado`. Confirm is idempotent. Gateways implement `Domain/Abstractions/Pasarelas/IPasarelaPago` and are picked by code in `Infrastructure/Pasarelas/PasarelaPagoResolver` (only `simulada` today: orders in cache, fake gateway page at `/pasarela-simulada/{orden}` where the buyer types a test card from `config('pagos.simulada.tarjetas')`: `aprobada` 4242 4242 4242 4242 is shown there to copy, `sin_fondos` 4000 0000 0000 9995 tests a rejected payment, both 12/30 CVV 123; anything else stays on the page as "datos incorrectos" and leaves the order pending. `IPasarelaPago::crearOrden()` receives `venceEn` = the reserva's `expira_en`, so a gateway order dies with its reserva (`pagos.simulada.minutos_orden` is only an upper cap); it is kept in cache one more day, so revisiting the gateway URL (browser Back, bfcache reload) shows `pasarelas/simulada-estado` — already processed / rejected → link to `url_retorno`, expired → back to the catalog, unknown → 404 — and re-posting the form never processes twice; seed with `PasarelaSeeder`). Cross-module effects go through ports implemented as adapters: `IRegistroVenta` → Transacciones (VENTA + historial `VENTA_CONFIRMADA`, inside the transaction) and `IProcesadorPostVenta` → `ProcesarCompraConfirmadaJob` (afterCommit: Boletos' `IEmitirBoletosUseCase`, then `NotificarCompraConfirmadaUseCase`). Confirm refuses (409 `reserva_no_pagable`, no charge) a reserva without `inventario` rows, since Boletos emits one ticket per seat.

### Venues and inventory (Recintos / Inventario)

Venues are fixed (≤100 seats each), seeded by `RecintoSeeder` (Teatro Soirée: numbered Platea+Balcón; Auditorio Central: numbered VIP + `general` zone). The admin picks a venue when creating an event, sets a price per localidad, and publishing (`PublicarEventoUseCase`) calls `IGenerarInventarioEventoUseCase` in the same transaction: it requires a price on every localidad (422 `localidades_sin_precio`) and creates one `inventario` row per seat (numerada) or `capacidad` rows with null `asiento_id` (general); idempotent. `php artisan inventario:generar {evento}` backfills events published before this existed. `GET /api/eventos/{id}/disponibilidad` (public) returns zones with price, counts and seats. `POST /api/reservas` accepts `inventario_ids` (numbered seats) and/or `cupos: [{localidad_id, cantidad}]` (general zones, rows picked with `FOR UPDATE SKIP LOCKED` so concurrent buyers don't collide; SQLite ignores the lock clause), capped by `reservas.max_asientos_por_reserva` (6) and throttled 10/min per user. Seat map views: client `/eventos/{eventoId}/asientos` (select seats, running total, polls every 8 s, keeps the selection in sessionStorage across login via `/login?volver=`), admin `/admin/eventos/{eventoId}/asientos` (live occupancy every 4 s); shared renderer `public/js/asientos.js`.

### Catalog banner

The catalog hero background is one image or one short video chosen in the admin panel (section "Banner", `public/js/admin-banner.js`): `GET|POST|DELETE /api/admin/banner` (multipart field `archivo`; jpg/png/webp/mp4/webm, ≤ `catalogo.banner.max_mb`; video length is only checked in the browser) and public `GET /api/catalogo/banner` (`data: null` → default theater photo). Single row in `banners`; `ActualizarBannerCatalogoUseCase` stores the file first, deletes it if the DB write fails, and only then deletes the previous file. Files go through `IAlmacenMultimedia` (`Infrastructure/Catalogo/AlmacenMultimediaLaravel`, same resize-to-WebP logic as the tienda-ropa project; videos stored as-is) on the `public` disk, so `php artisan storage:link` is required. The dark tone is pure CSS (`.portada-fondo__medio` in `catalogo.css`). Upload limits: `docker/php/subidas.ini` (copied in the Dockerfile) and `client_max_body_size` in `docker/nginx/default.conf`.

### Data model notes

- Domain users live in the `usuarios` table (entity `Domain/Entities/Auth/Usuario`); the default Laravel `users` table/`App\Models\User` is leftover scaffolding.
- Business tables carry audit columns (`created_by`/`updated_by` → `usuarios`, timestamps, soft deletes).
- Seat-hold flow: `inventario` rows (per event/localidad/asiento) are locked with `estado` + `bloqueado_hasta` and linked to a `reservas` row with `expira_en` (TTL). The `scheduler` container runs `schedule:run` every minute to sweep expired reservations/holds, and the `queue` container (`queue:work redis --tries=3`) handles seat TTL, payment retries and emails. Both have `(estado, <timestamp>)` indexes for that sweep.
- Circular FK `inventario.reserva_id` is added in the `reservas` migration — keep migration order in mind when adding cross-module FKs.

## Team / Git

Branches: `main` (stable), `develop` (integration), `feat/<modulo>` per module. Module ownership: Christopher — Inventario, Reservas, Pagos, Pasarelas; Freyder — Reembolsos, Boletos, Notificaciones, Transacciones; Luis — Catálogo, Recintos, ColaVirtual, Admin. Auth (login) was moved to Christopher by agreement with Luis. Avoid editing other owners' modules unless asked.


For the request flow and a reference vertical slice (controller → use case → repository), see docs/flujo-arquitectura.md and follow that exact pattern.