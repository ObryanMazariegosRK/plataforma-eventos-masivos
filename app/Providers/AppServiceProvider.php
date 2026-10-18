<?php

namespace App\Providers;

use App\Application\Abstractions\Boletos\IGeneradorQrBoleto;
use App\Infrastructure\Boletos\GeneradorQrBoletoSvg;

use App\Application\Abstractions\Boletos\IObtenerQrBoletoUseCase;
use App\Application\UseCases\Boletos\ObtenerQrBoletoUseCase;

use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {

        // ---- Notificaciones ----
        $this->app->bind(
            \App\Domain\Abstractions\Notificaciones\INotificacionRepository::class,
            \App\Data\Notificaciones\NotificacionRepository::class
        );

        // ---- Reembolsos ----
        $this->app->bind(
            \App\Domain\Abstractions\Reembolsos\IReembolsoRepository::class,
            \App\Data\Reembolsos\ReembolsoRepository::class,
        );

        //Transacciones
        $this->app->bind(
            \App\Domain\Abstractions\Transacciones\ITransaccionRepository::class,
            \App\Data\Transacciones\TransaccionRepository::class
        );

        // Caso de uso para emitir boletos
        $this->app->bind(
            IGeneradorQrBoleto::class,
            GeneradorQrBoletoSvg::class
        );

        // Caso de uso para obtener el QR de un boleto.
        $this->app->bind(
            IObtenerQrBoletoUseCase::class,
            ObtenerQrBoletoUseCase::class
        );

        // ---- Boletos ----
        $this->app->bind(
            \App\Domain\Abstractions\Boletos\IBoletoRepository::class,
            \App\Data\Boletos\BoletoRepository::class,
        );

         // Caso de uso para emitir boletos
        $this->app->bind(
            \App\Application\Abstractions\Boletos\IEmitirBoletosUseCase::class,
            \App\Application\UseCases\Boletos\EmitirBoletosUseCase::class,
        );

        // ---- Reservas ----
        $this->app->bind(
            \App\Domain\Abstractions\Reservas\IReservaRepository::class,
            \App\Data\Reservas\ReservaRepository::class,
        );

        // Crear reserva y registrar automáticamente su historial.
        $this->app->bind(
            \App\Application\Abstractions\Reservas\ICrearReservaUseCase::class,
            \App\Application\UseCases\Transacciones\CrearReservaConHistorialUseCase::class,
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

        // ---- Catalogo de Eventos ----

        // Repositorio de eventos
        $this->app->bind(
            \App\Domain\Abstractions\Catalogo\IEventoRepository::class,
            \App\Data\Catalogo\EventoRepository::class,
        );

        // Caso de uso para listar eventos
        $this->app->bind(
            \App\Application\Abstractions\Catalogo\IListarEventosUseCase::class,
            \App\Application\UseCases\Catalogo\ListarEventosUseCase::class,
        );

        // ---- Pagos y Pasarelas ----
        // repositorios
        $this->app->bind(
            \App\Domain\Abstractions\Pagos\IPagoRepository::class,
            \App\Data\Pagos\PagoRepository::class,
        );
        $this->app->bind(
            \App\Domain\Abstractions\Pasarelas\IPasarelaRepository::class,
            \App\Data\Pasarelas\PasarelaRepository::class,
        );
        $this->app->bind(
            \App\Domain\Abstractions\Inventario\IInventarioRepository::class,
            \App\Data\Inventario\InventarioRepository::class,
        );
        // servicios
        $this->app->bind(
            \App\Domain\Abstractions\Shared\IUnidadDeTrabajo::class,
            \App\Infrastructure\Shared\UnidadDeTrabajoLaravel::class,
        );
        $this->app->bind(
            \App\Domain\Abstractions\Pasarelas\IPasarelaPagoResolver::class,
            \App\Infrastructure\Pasarelas\PasarelaPagoResolver::class,
        );
        $this->app->bind(
            \App\Domain\Abstractions\Pagos\IRegistroVenta::class,
            \App\Infrastructure\Pagos\RegistroVentaTransacciones::class,
        );
        $this->app->bind(
            \App\Domain\Abstractions\Pagos\IProcesadorPostVenta::class,
            \App\Infrastructure\Pagos\ProcesadorPostVentaEnCola::class,
        );
        // casos de uso
        $this->app->bind(
            \App\Application\Abstractions\Pagos\IIniciarPagoUseCase::class,
            \App\Application\UseCases\Pagos\IniciarPagoUseCase::class,
        );
        $this->app->bind(
            \App\Application\Abstractions\Pagos\IConfirmarPagoUseCase::class,
            \App\Application\UseCases\Pagos\ConfirmarPagoUseCase::class,
        );
        $this->app->bind(
            \App\Application\Abstractions\Pagos\IConsultarPagoUseCase::class,
            \App\Application\UseCases\Pagos\ConsultarPagoUseCase::class,
        );
        $this->app->bind(
            \App\Application\Abstractions\Pasarelas\IListarPasarelasUseCase::class,
            \App\Application\UseCases\Pasarelas\ListarPasarelasUseCase::class,
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
