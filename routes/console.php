<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ---- Tareas programadas ----
// El contenedor "scheduler" ejecuta `php artisan schedule:run` cada minuto;
// Laravel revisa esta lista y corre lo que toque según su frecuencia.

// Borra de personal_access_tokens los tokens que vencieron hace más de 24 h
// (un token vencido ya no sirve, pero su fila se queda en la tabla si nadie la limpia).
Schedule::command('sanctum:prune-expired --hours=24')->daily();
