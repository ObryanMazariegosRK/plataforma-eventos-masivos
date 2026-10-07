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
```

If you get HTTP 500 on a fresh clone: `docker compose exec app chmod -R 777 storage bootstrap/cache`.

Services: app http://localhost:8090, Adminer http://localhost:8082, Mailpit http://localhost:8026, MySQL on host port 3308 (user `eventos` / `root`, db `eventos`). Inside the Docker network, hosts are `mysql` and `redis`.

Tests use SQLite in-memory, `QUEUE_CONNECTION=sync` and array cache/session (see `phpunit.xml`), so they do not need the MySQL/Redis containers — but MySQL-specific migration features must stay SQLite-compatible for tests to pass.

## Architecture

Clean architecture, sliced per module. Each layer has one folder per module (`Admin, Auth, Boletos, Catalogo, ColaVirtual, Inventario, Notificaciones, Pagos, Pasarelas, Recintos, Reembolsos, Reservas, Transacciones`), scaffolded by `scripts/create-modules.sh` (empty dirs hold `.gitkeep`):

- `app/Domain/Entities/<Modulo>/` — plain PHP entities (no Eloquent). Constructor-promoted private props, a private `validar()` called from the constructor that throws `DomainException`, behaviour methods that enforce state transitions (e.g. `Reserva::confirmar()`, `cancelar()`, `vencer()`), and explicit getters. `Reserva` is the reference example.
- `app/Domain/Enums/<Modulo>/` — string-backed enums for states/types (`EstadoReserva`, `EstadoAsiento`, …); values match what's stored in DB `estado` columns.
- `app/Domain/Abstractions/<Modulo>/` — repository interfaces (`I<Entidad>Repository`).
- `app/Application/Abstractions/<Modulo>/` — use-case interfaces (`I<Accion>UseCase`, single `execute()` method). Controllers depend on these interfaces, never on concrete use cases.
- `app/Application/UseCases/<Modulo>/` — use-case implementations (`<Accion>UseCase`).
- `app/Application/DTOs/<Modulo>/` — readonly input DTOs.
- `app/Data/<Modulo>/` — **all repository implementations go here** (`<Entidad>Repository`, mapping Entity↔Model). This is the only layer, besides `Models`, that touches Eloquent. The folders are created per module as repositories are added.
- `app/Infrastructure/<Modulo>/` — third-party services ONLY (mail, payment gateway adapters, external APIs). **Never put repositories here.**
- `app/Models/<Modulo>/` — Eloquent models (persistence only).
- `app/Http/Controllers|Requests/<Modulo>/` — HTTP layer.

Bind every interface to its implementation in `AppServiceProvider::register()` — both the repository (`Domain/Abstractions` → `Data`) and the use case (`Application/Abstractions` → `Application/UseCases`). Read settings via `config()` (e.g. `config/reservas.php`), never `env()` outside `config/`.

Note: the README's architecture tree is slightly out of date (it shows use cases directly under `Application/<Modulo>/`); the actual folders are as listed above.

Most modules are still skeletons: domain entities/enums and migrations exist, but most use cases, repositories, models and controllers are unimplemented. The first complete vertical slice is `POST /api/reservas` (`CrearReservaUseCase`) — use it as the template. API routes live in `routes/api.php` (registered in `bootstrap/app.php`, prefixed `/api`, JSON exceptions).

### Data model notes

- Domain users live in the `usuarios` table (entity `Domain/Entities/Auth/Usuario`); the default Laravel `users` table/`App\Models\User` is leftover scaffolding.
- Business tables carry audit columns (`created_by`/`updated_by` → `usuarios`, timestamps, soft deletes).
- Seat-hold flow: `inventario` rows (per event/localidad/asiento) are locked with `estado` + `bloqueado_hasta` and linked to a `reservas` row with `expira_en` (TTL). The `scheduler` container runs `schedule:run` every minute to sweep expired reservations/holds, and the `queue` container (`queue:work redis --tries=3`) handles seat TTL, payment retries and emails. Both have `(estado, <timestamp>)` indexes for that sweep.
- Circular FK `inventario.reserva_id` is added in the `reservas` migration — keep migration order in mind when adding cross-module FKs.

## Team / Git

Branches: `main` (stable), `develop` (integration), `feat/<modulo>` per module. Module ownership: Christopher — Inventario, Reservas, Pagos, Pasarelas; Freyder — Reembolsos, Boletos, Notificaciones, Transacciones; Luis — Catálogo, Recintos, Auth, ColaVirtual, Admin. Avoid editing other owners' modules unless asked.


For the request flow and a reference vertical slice (controller → use case → repository), see docs/flujo-arquitectura.md and follow that exact pattern.