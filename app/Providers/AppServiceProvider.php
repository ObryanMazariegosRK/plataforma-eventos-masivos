<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // ---- Reservas ----
        $this->app->bind(
            \App\Domain\Abstractions\Reservas\IReservaRepository::class,
            \App\Data\Reservas\ReservaRepository::class,
        );
        $this->app->bind(
            \App\Application\Abstractions\Reservas\ICrearReservaUseCase::class,
            \App\Application\UseCases\Reservas\CrearReservaUseCase::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
