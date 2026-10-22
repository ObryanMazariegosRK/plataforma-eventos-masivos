<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Application\UseCases\Notificaciones\ProgramarRecordatoriosEventosUseCase;
use App\Application\UseCases\Reservas\ExpirarReservasVencidasUseCase;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ---- Tareas programadas ----
// El contenedor "scheduler" ejecuta `php artisan schedule:run` cada minuto;
// Laravel revisa esta lista y corre lo que toque según su frecuencia.

// Borra de personal_access_tokens los tokens que vencieron hace más de 24 h
// (un token vencido ya no sirve, pero su fila se queda en la tabla si nadie la limpia).
Schedule::command('sanctum:prune-expired --hours=24')->daily();


Schedule::call(function (): void {
    app(ProgramarRecordatoriosEventosUseCase::class)
        ->execute();
})
    ->name('notificaciones.recordatorios.24h')
    ->everyFiveMinutes()
    ->withoutOverlapping();


 // ---- Expiración automática de reservas ----
 // Revisa cada minuto las reservas cuyo TTL terminó.
 // Libera los elementos de inventario que siguen bloqueados.

Schedule::call(function (): void {
    app(ExpirarReservasVencidasUseCase::class)
        ->execute();
})
    ->name('reservas.expirar.vencidas')
    ->everyMinute()
    ->withoutOverlapping();

 // ---- Procesamiento automático de Cola Virtual (RF-09) ----
 // Revisa los turnos cada minuto cuando esté habilitado.
 // Expira los vencidos y habilita a los siguientes usuarios.

Schedule::call(function (): void {

    // Protección: no procesar mientras esté desactivado.
    if (!config('cola_virtual.procesamiento_automatico_habilitado', false)) {
        return;
    }

    app(\App\Application\Abstractions\ColaVirtual\IProcesarColaVirtualUseCase::class)
        ->execute();

})
    ->name('cola-virtual.procesar.turnos')
    ->everyMinute()
    ->withoutOverlapping();

// Genera el inventario de un evento que se publicó antes de que existiera la generación automática.
// Uso: php artisan inventario:generar 4
Artisan::command('inventario:generar {evento : id del evento}', function (int $evento) {
    $creados = app(\App\Application\Abstractions\Inventario\IGenerarInventarioEventoUseCase::class)->execute($evento);
    $this->info($creados > 0 ? "Se crearon {$creados} lugares para el evento {$evento}." : "El evento {$evento} ya tenía inventario.");
})->purpose('Crea los lugares a la venta de un evento a partir de su recinto');

// Crea un administrador (ya verificado y activo) o convierte en admin una cuenta que ya existe.
// Uso: php artisan usuario:crear-admin            → pregunta los datos
//      php artisan usuario:crear-admin --email=ana@correo.com --nombre=Ana --apellido=López
// La contraseña siempre se pide oculta (nunca como parámetro, para que no quede en el historial).
Artisan::command('usuario:crear-admin {--email=} {--nombre=} {--apellido=}', function () {
    $email = strtolower(trim($this->option('email') ?: $this->ask('Correo')));

    if (\Illuminate\Support\Facades\Validator::make(['email' => $email], ['email' => 'required|email'])->fails()) {
        $this->error('El correo no es válido.');
        return 1;
    }

    $usuario = \App\Models\Auth\Usuario::withTrashed()->where('email', $email)->first();

    // La cuenta ya existe: se promueve a admin (sin tocar su contraseña salvo que se pida)
    if ($usuario) {
        $this->warn("Ya existe una cuenta con {$email} ({$usuario->nombre} {$usuario->apellido}, rol {$usuario->rol->value}).");
        if (!$this->confirm('¿Convertirla en administrador, verificada y activa?', true)) {
            return 0;
        }
        $cambiarPassword = $this->confirm('¿Cambiarle también la contraseña?', false);
    }

    // Una cuenta existente conserva sus datos; para una nueva se piden
    $datos = $usuario ? $usuario->only(['nombre', 'apellido']) : [
        'nombre'   => $this->option('nombre') ?: $this->ask('Nombre'),
        'apellido' => $this->option('apellido') ?: $this->ask('Apellido'),
    ];

    $password = null;
    if (!$usuario || $cambiarPassword) {
        $password = $this->secret('Contraseña');
        $validacion = \Illuminate\Support\Facades\Validator::make(
            ['password' => $password, 'password_confirmation' => $this->secret('Repite la contraseña')],
            ['password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()]],
        );
        if ($validacion->fails()) {
            foreach ($validacion->errors()->all() as $error) {
                $this->error($error);
            }
            return 1;
        }
    }

    $usuario ??= new \App\Models\Auth\Usuario(['email' => $email]);
    $usuario->fill([
        'nombre'            => $datos['nombre'],
        'apellido'          => $datos['apellido'],
        'rol'               => \App\Domain\Enums\Auth\RolUsuario::ADMIN,
        'estado'            => \App\Domain\Enums\Auth\EstadoUsuario::ACTIVO,
        'email_verified_at' => $usuario->email_verified_at ?? now(),
    ]);
    if ($password !== null) {
        $usuario->password = \Illuminate\Support\Facades\Hash::make($password);
    }
    $usuario->deleted_at = null;   // por si la cuenta estaba eliminada
    $usuario->save();

    $this->info("Listo: {$usuario->email} es administrador. Inicia sesión en /login y entrarás a /admin.");
    return 0;
})->purpose('Crea un usuario administrador o promueve una cuenta existente');
