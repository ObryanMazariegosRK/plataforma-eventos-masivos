<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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

        // ---- Auth ----
        // repositorio y servicios
        $this->app->bind(
            \App\Domain\Abstractions\Auth\IUsuarioRepository::class,
            \App\Data\Auth\UsuarioRepository::class,
        );
        $this->app->bind(
            \App\Domain\Abstractions\Auth\IPasswordHasher::class,
            \App\Infrastructure\Auth\LaravelPasswordHasher::class,
        );
        $this->app->bind(
            \App\Domain\Abstractions\Auth\ITokenService::class,
            \App\Infrastructure\Auth\SanctumTokenService::class,
        );
        $this->app->bind(
            \App\Domain\Abstractions\Auth\IEnviadorCorreoAuth::class,
            \App\Infrastructure\Auth\LaravelEnviadorCorreoAuth::class,
        );
        // casos de uso
        $this->app->bind(
            \App\Application\Abstractions\Auth\IRegistrarUsuarioUseCase::class,
            \App\Application\UseCases\Auth\RegistrarUsuarioUseCase::class,
        );
        $this->app->bind(
            \App\Application\Abstractions\Auth\IVerificarCorreoUseCase::class,
            \App\Application\UseCases\Auth\VerificarCorreoUseCase::class,
        );
        $this->app->bind(
            \App\Application\Abstractions\Auth\IReenviarCodigoUseCase::class,
            \App\Application\UseCases\Auth\ReenviarCodigoUseCase::class,
        );
        $this->app->bind(
            \App\Application\Abstractions\Auth\IIniciarSesionUseCase::class,
            \App\Application\UseCases\Auth\IniciarSesionUseCase::class,
        );
        $this->app->bind(
            \App\Application\Abstractions\Auth\ICerrarSesionUseCase::class,
            \App\Application\UseCases\Auth\CerrarSesionUseCase::class,
        );
        $this->app->bind(
            \App\Application\Abstractions\Auth\IObtenerPerfilUseCase::class,
            \App\Application\UseCases\Auth\ObtenerPerfilUseCase::class,
        );
        $this->app->bind(
            \App\Application\Abstractions\Auth\ISolicitarRecuperacionUseCase::class,
            \App\Application\UseCases\Auth\SolicitarRecuperacionUseCase::class,
        );
        $this->app->bind(
            \App\Application\Abstractions\Auth\IRestablecerPasswordUseCase::class,
            \App\Application\UseCases\Auth\RestablecerPasswordUseCase::class,
        );
        $this->app->bind(
            \App\Application\Abstractions\Auth\ICambiarPasswordUseCase::class,
            \App\Application\UseCases\Auth\CambiarPasswordUseCase::class,
        );
        $this->app->bind(
            \App\Application\Abstractions\Auth\ICerrarTodasLasSesionesUseCase::class,
            \App\Application\UseCases\Auth\CerrarTodasLasSesionesUseCase::class,
        );
        $this->app->bind(
            \App\Application\Abstractions\Auth\IIniciarSesionConGoogleUseCase::class,
            \App\Application\UseCases\Auth\IniciarSesionConGoogleUseCase::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Reglas de contraseña de toda la app: se usan con Password::defaults()
        // en el registro y al restablecer la contraseña.
        Password::defaults(fn () => Password::min(8)->mixedCase()->numbers()->symbols());
    }
}
