<?php

namespace App\Providers;

use App\Application\Abstractions\Boletos\IGeneradorQrBoleto;
use App\Infrastructure\Boletos\GeneradorQrBoletoSvg;

use App\Application\Abstractions\Boletos\IObtenerQrBoletoUseCase;
use App\Application\UseCases\Boletos\ObtenerQrBoletoUseCase;

use Illuminate\Mail\Events\MessageSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
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

        // ---- Notificaciones: push a los dispositivos (Web Push) y avisos al personal ----
        $this->app->bind(
            \App\Domain\Abstractions\Notificaciones\IPersonalRepository::class,
            \App\Data\Notificaciones\PersonalRepository::class,
        );
        $this->app->bind(
            \App\Domain\Abstractions\Notificaciones\IEnviadorPush::class,
            \App\Infrastructure\Notificaciones\Push\EnviadorPushWeb::class,
        );

        // ---- Reembolsos ----
        $this->app->bind(
            \App\Domain\Abstractions\Reembolsos\IReembolsoRepository::class,
            \App\Data\Reembolsos\ReembolsoRepository::class,
        );
        $this->app->bind(
            \App\Application\Abstractions\Reembolsos\IProcesarReembolsoUseCase::class,
            \App\Application\UseCases\Reembolsos\ProcesarReembolsoUseCase::class,
        );
        $this->app->bind(
            \App\Application\Abstractions\Reembolsos\IConsultarReembolsoDeCompraUseCase::class,
            \App\Application\UseCases\Reembolsos\ConsultarReembolsoDeCompraUseCase::class,
        );
        $this->app->bind(
            \App\Application\Abstractions\Reembolsos\ISolicitarReembolsoDeCompraUseCase::class,
            \App\Application\UseCases\Reembolsos\SolicitarReembolsoDeCompraUseCase::class,
        );
        $this->app->bind(
            \App\Application\Abstractions\Reembolsos\IListarReembolsosAdminUseCase::class,
            \App\Application\UseCases\Reembolsos\ListarReembolsosAdminUseCase::class,
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
        $this->app->bind(
            \App\Domain\Abstractions\Boletos\IConsultaBoletoRepository::class,
            \App\Data\Boletos\ConsultaBoletoRepository::class,
        );
        $this->app->bind(
            \App\Application\Abstractions\Boletos\IConsultarBoletoEscaneadoUseCase::class,
            \App\Application\UseCases\Boletos\ConsultarBoletoEscaneadoUseCase::class,
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

        $this->app->bind(
            \App\Application\Abstractions\Reservas\IConsultarReservaUseCase::class,
            \App\Application\UseCases\Reservas\ConsultarReservaUseCase::class,
        );
        $this->app->bind(
            \App\Application\Abstractions\Reservas\IListarMisComprasUseCase::class,
            \App\Application\UseCases\Reservas\ListarMisComprasUseCase::class,
        );
        $this->app->bind(
            \App\Application\Abstractions\Reservas\IConsultarMiCupoUseCase::class,
            \App\Application\UseCases\Reservas\ConsultarMiCupoUseCase::class,
        );

        // Crear reserva y registrar automáticamente su historial.
        $this->app->bind(
            \App\Application\Abstractions\Reservas\ICrearReservaUseCase::class,
            \App\Application\UseCases\Transacciones\CrearReservaConHistorialUseCase::class,
        );

        // ---- Cola Virtual ----
        $this->app->bind(
            \App\Domain\Abstractions\ColaVirtual\ITurnoRepository::class,
            \App\Data\ColaVirtual\TurnoRepository::class,
        );

        $this->app->bind(
            \App\Application\Abstractions\ColaVirtual\IIngresarColaVirtualUseCase::class,
            \App\Application\UseCases\ColaVirtual\IngresarColaVirtualUseCase::class,
        );
        
        // Consulta de posición y estado del turno (RF-09).
        $this->app->bind(
            \App\Application\Abstractions\ColaVirtual\IConsultarMiTurnoUseCase::class,
            \App\Application\UseCases\ColaVirtual\ConsultarMiTurnoUseCase::class,
        );

        // Procesamiento automático de Cola Virtual (RF-09).
        $this->app->bind(
            \App\Application\Abstractions\ColaVirtual\IProcesarColaVirtualUseCase::class,
            \App\Application\UseCases\ColaVirtual\ProcesarColaVirtualUseCase::class,
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

        // ---- Admin: catálogo de eventos del panel ----
        $this->app->bind(
            \App\Domain\Abstractions\Admin\ICatalogoEventosAdminRepository::class,
            \App\Data\Admin\CatalogoEventosAdminRepository::class,
        );
        $this->app->bind(
            \App\Application\Abstractions\Admin\IBuscarEventosAdminUseCase::class,
            \App\Application\UseCases\Admin\BuscarEventosAdminUseCase::class,
        );

        // ---- Inventario: plano de un recinto (formulario de evento) ----
        $this->app->bind(
            \App\Application\Abstractions\Inventario\IConsultarPlanoRecintoUseCase::class,
            \App\Application\UseCases\Inventario\ConsultarPlanoRecintoUseCase::class,
        );

        // ---- Admin: fotos de los eventos ----
        $this->app->bind(
            \App\Application\Abstractions\Admin\ISubirImagenEventoUseCase::class,
            \App\Application\UseCases\Admin\SubirImagenEventoUseCase::class,
        );

        // ---- Admin: reportes en Excel y PDF ----
        $this->app->bind(
            \App\Domain\Abstractions\Admin\IReporteAdminRepository::class,
            \App\Data\Admin\ReporteAdminRepository::class,
        );
        $this->app->bind(
            \App\Application\Abstractions\Admin\IGenerarReporteAdminUseCase::class,
            \App\Application\UseCases\Admin\GenerarReporteAdminUseCase::class,
        );

        // ---- Finanzas: costos de producción y ganancias por evento ----
        $this->app->bind(
            \App\Domain\Abstractions\Finanzas\ICostoEventoRepository::class,
            \App\Data\Finanzas\CostoEventoRepository::class,
        );
        $this->app->bind(
            \App\Domain\Abstractions\Finanzas\IFinanzasRepository::class,
            \App\Data\Finanzas\FinanzasRepository::class,
        );
        $this->app->bind(
            \App\Application\Abstractions\Finanzas\IListarCostosEventoUseCase::class,
            \App\Application\UseCases\Finanzas\ListarCostosEventoUseCase::class,
        );
        $this->app->bind(
            \App\Application\Abstractions\Finanzas\IGuardarCostosEventoUseCase::class,
            \App\Application\UseCases\Finanzas\GuardarCostosEventoUseCase::class,
        );
        $this->app->bind(
            \App\Application\Abstractions\Finanzas\IObtenerFinanzasUseCase::class,
            \App\Application\UseCases\Finanzas\ObtenerFinanzasUseCase::class,
        );

        // ---- Bitácora (base aparte; la llena el middleware RegistrarBitacora) ----
        $this->app->bind(
            \App\Domain\Abstractions\Bitacora\IBitacoraRepository::class,
            \App\Data\Bitacora\BitacoraRepository::class,
        );
        $this->app->bind(
            \App\Domain\Abstractions\Bitacora\IContextoBitacora::class,
            \App\Data\Bitacora\ContextoBitacora::class,
        );
        $this->app->bind(
            \App\Application\Abstractions\Bitacora\IRegistrarEnBitacoraUseCase::class,
            \App\Application\UseCases\Bitacora\RegistrarEnBitacoraUseCase::class,
        );
        $this->app->bind(
            \App\Application\Abstractions\Bitacora\IBuscarEnBitacoraUseCase::class,
            \App\Application\UseCases\Bitacora\BuscarEnBitacoraUseCase::class,
        );

        // ---- Catálogo: banner de la portada ----
        $this->app->bind(
            \App\Domain\Abstractions\Catalogo\IBannerRepository::class,
            \App\Data\Catalogo\BannerRepository::class,
        );
        $this->app->bind(
            \App\Domain\Abstractions\Catalogo\IAlmacenMultimedia::class,
            \App\Infrastructure\Catalogo\AlmacenMultimediaLaravel::class,
        );
        $this->app->bind(
            \App\Application\Abstractions\Catalogo\IObtenerBannerCatalogoUseCase::class,
            \App\Application\UseCases\Catalogo\ObtenerBannerCatalogoUseCase::class,
        );
        $this->app->bind(
            \App\Application\Abstractions\Catalogo\IActualizarBannerCatalogoUseCase::class,
            \App\Application\UseCases\Catalogo\ActualizarBannerCatalogoUseCase::class,
        );
        $this->app->bind(
            \App\Application\Abstractions\Catalogo\IEliminarBannerCatalogoUseCase::class,
            \App\Application\UseCases\Catalogo\EliminarBannerCatalogoUseCase::class,
        );

        // ---- Inventario ----
        $this->app->bind(
            \App\Application\Abstractions\Inventario\IGenerarInventarioEventoUseCase::class,
            \App\Application\UseCases\Inventario\GenerarInventarioEventoUseCase::class,
        );
        $this->app->bind(
            \App\Application\Abstractions\Inventario\IConsultarDisponibilidadUseCase::class,
            \App\Application\UseCases\Inventario\ConsultarDisponibilidadUseCase::class,
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

        // Cierra la conexión SMTP en cuanto sale cada correo. El trabajador de la cola vive horas y
        // reutilizaría la conexión, pero Azure corta en silencio las conexiones de salida inactivas
        // (~4 min): el siguiente correo escribía en una conexión muerta y se quedaba colgado hasta
        // que mataban al trabajador (el código llegaba 3–5 minutos tarde). Abrir una nueva tarda ~0.3 s.
        Event::listen(MessageSent::class, function (): void {
            $transporte = Mail::mailer()->getSymfonyTransport();
            if ($transporte instanceof SmtpTransport) {
                $transporte->stop();
            }
        });
    }
}
