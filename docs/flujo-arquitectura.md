# Flujo de una petición

Cómo viaja una petición por las capas, con un ejemplo completo de referencia.

## El viaje

```
HTTP (JSON)
  │
  ▼
Http/Controllers/<Modulo>   ─ recibe la petición, valida con un FormRequest,
  │                           arma un DTO y llama al caso de uso VÍA SU INTERFAZ.
  │                           NO tiene lógica de negocio.
  ▼
Application/DTOs/<Modulo>    ─ objeto simple con los datos de entrada (sin comportamiento).
  │
  ▼
Application/Abstractions/<Modulo> ─ interfaz del caso de uso (I<Accion>UseCase, un solo método execute).
  ▲
  │ la implementa
Application/UseCases/<Modulo> ─ orquesta: crea/usa ENTIDADES y pide al repositorio
  │                             (vía INTERFAZ) que persista. Aquí van las reglas de "al crear".
  ▼
Domain/Abstractions/<Modulo> ─ interfaz del repositorio (contrato, habla en Entidades).
  ▲
  │ la implementa
Data/<Modulo>                ─ repositorio Eloquent: traduce Entidad ↔ Modelo (mapeador).
  │                             ÚNICO lugar que conoce Eloquent.
  ▼
Models/<Modulo>  ──►  MySQL
```

La **Entidad** (`Domain/Entities`) es la "moneda" que circula por Controller/UseCase/Domain.
El **Modelo Eloquent** nunca sale de `Data`/`Models`.
`Infrastructure/<Modulo>` queda reservado SOLO para servicios de terceros (correo, pasarelas de pago).

## Responsabilidad de cada capa

| Capa | Carpeta | Hace | NO hace |
|---|---|---|---|
| Controller | `Http/Controllers/<Modulo>` | recibir petición, responder JSON (depende de la interfaz del caso de uso) | lógica de negocio, tocar la BD |
| Request | `Http/Requests/<Modulo>` | validar formato de entrada | reglas de dominio |
| DTO | `Application/DTOs/<Modulo>` | transportar datos de entrada | comportamiento |
| Interfaz caso de uso | `Application/Abstractions/<Modulo>` | definir el contrato `execute()` | implementación |
| Caso de uso | `Application/UseCases/<Modulo>` | orquestar entidades + repos | SQL, HTTP |
| Entidad | `Domain/Entities/<Modulo>` | datos + reglas invariantes + transiciones de estado | conocer Eloquent/HTTP |
| Interfaz repo | `Domain/Abstractions/<Modulo>` | definir el contrato (en Entidades) | implementación |
| Repo Eloquent | `Data/<Modulo>` | implementar el contrato, mapear Entidad↔Modelo | reglas de negocio |
| Modelo | `Models/<Modulo>` | persistencia (tabla, casts, fillable) | lógica de negocio |
| Servicios terceros | `Infrastructure/<Modulo>` | correo, pasarelas de pago, APIs externas | persistencia, reglas de negocio |

## Ejemplo completo: crear una reserva → `POST /api/reservas`

El ejemplo de referencia está implementado en el código; léelo en este orden:

| # | Pieza | Archivo |
|---|---|---|
| 0 | Registro de rutas API (`api:` en `withRouting`, prefija con `/api`) | `bootstrap/app.php` |
| 1 | Ruta | `routes/api.php` |
| 2 | Request (validación de formato) | `app/Http/Requests/Reservas/CrearReservaRequest.php` |
| 3 | DTO | `app/Application/DTOs/Reservas/CrearReservaDTO.php` |
| 4 | Controller | `app/Http/Controllers/Reservas/ReservaController.php` |
| 5 | Interfaz del caso de uso | `app/Application/Abstractions/Reservas/ICrearReservaUseCase.php` |
| 6 | Caso de uso | `app/Application/UseCases/Reservas/CrearReservaUseCase.php` |
| 7 | Interfaz del repositorio | `app/Domain/Abstractions/Reservas/IReservaRepository.php` |
| 8 | Modelo Eloquent | `app/Models/Reservas/Reserva.php` |
| 9 | Repositorio (mapeador Entidad↔Modelo) | `app/Data/Reservas/ReservaRepository.php` |
| 10 | Cableado DI | `app/Providers/AppServiceProvider.php` |
| 11 | Config (TTL) | `config/reservas.php` |
| 12 | Pruebas | `tests/Unit/Reservas/CrearReservaUseCaseTest.php`, `tests/Feature/Reservas/CrearReservaTest.php` |

### Puntos clave

**El controller depende de la interfaz, nunca del caso de uso concreto:**
```php
public function store(CrearReservaRequest $request, ICrearReservaUseCase $crearReserva): JsonResponse
{
    $dto = new CrearReservaDTO(
        usuarioId: $request->integer('usuario_id'),
        eventoId: $request->integer('evento_id'),
        total: $request->float('total'),
        ttlSegundos: config('reservas.ttl_segundos'),
    );

    $reserva = $crearReserva->execute($dto);
    // ... responder JSON con datos de la Entidad
}
```

**Interfaz del caso de uso — un solo método `execute`:**
```php
interface ICrearReservaUseCase
{
    public function execute(CrearReservaDTO $dto): Reserva;
}
```

**El caso de uso implementa su interfaz y pide el repositorio por SU interfaz:**
```php
class CrearReservaUseCase implements ICrearReservaUseCase
{
    public function __construct(private IReservaRepository $reservas) {}

    public function execute(CrearReservaDTO $dto): Reserva { /* ... */ }
}
```

**El repositorio vive en `Data` y usa alias para el modelo** (para no chocar con la Entidad):
```php
use App\Models\Reservas\Reserva as ReservaModel;

class ReservaRepository implements IReservaRepository { /* guardar, buscarPorId, aEntidad */ }
```

**Cableado — dos binds por caso de uso nuevo** (repositorio + caso de uso), en `AppServiceProvider::register()`:
```php
$this->app->bind(IReservaRepository::class, ReservaRepository::class);
$this->app->bind(ICrearReservaUseCase::class, CrearReservaUseCase::class);
```
Sin esto, Laravel no sabe qué implementación inyectar cuando alguien pide una interfaz.

**Configuración:** leer valores con `config('modulo.clave')`, nunca `env()` fuera de `config/`
(con `php artisan config:cache`, `env()` devuelve `null`).

## Convenciones de nombres

| Pieza | Patrón | Ejemplo |
|---|---|---|
| Interfaz caso de uso | `I<Verbo><Sustantivo>UseCase` | `ICrearReservaUseCase` |
| Caso de uso | `<Verbo><Sustantivo>UseCase` | `CrearReservaUseCase`, `ConfirmarPagoUseCase` |
| Método del caso de uso | `execute` | `execute(CrearReservaDTO $dto)` |
| DTO | `<Verbo><Sustantivo>DTO` | `CrearReservaDTO` |
| Request | `<Verbo><Sustantivo>Request` | `CrearReservaRequest` |
| Interfaz repo | `I<Entidad>Repository` | `IReservaRepository` |
| Impl repo (en `Data`) | `<Entidad>Repository` | `ReservaRepository` |
| Modelo | `<Entidad>` (importar con alias `<Entidad>Model`) | `Reserva` |
| Controller | `<Entidad>Controller` | `ReservaController` |

## Reglas de oro

- **Validación de formato** (campos requeridos, tipos) → en el **Request**.
- **Reglas invariantes** (siempre verdad: nombre no vacío) → en la **Entidad** (`validar()`).
- **Reglas de contexto** (ej. "al crear, la fecha debe ser futura") → en el **Caso de uso**.
- El **Controller** nunca toca Eloquent ni la BD; solo arma DTO y llama al caso de uso **por su interfaz**.
- Eloquent **solo** en `Data`/`Models`. Las demás capas hablan en **Entidades**.
- `Infrastructure` **solo** para servicios de terceros (correo, pasarelas), nunca repos.
- Cada cambio de esquema = **migración nueva** (nunca editar una ya subida).