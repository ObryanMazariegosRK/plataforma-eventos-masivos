<?php

namespace App\Infrastructure\Bitacora;

use App\Domain\Abstractions\Bitacora\IContextoBitacora;
use App\Domain\Enums\Bitacora\CategoriaBitacora as C;
use Illuminate\Http\Request;

// Qué acciones van a la bitácora. Cada entrada se identifica por "MÉTODO uri-de-la-ruta":
//   categoria, accion, entidad → cómo se clasifica (accion: "area.verbo")
//   campos           → LISTA BLANCA de datos de la petición que se guardan (nunca contraseñas,
//                      códigos ni el QR de un boleto)
//   id               → (opcional) de dónde sale el id del registro afectado; por defecto el
//                      parámetro de la ruta o el id que devuelve la respuesta al crear
//   antes            → (opcional) cómo estaba el registro antes del cambio
//   describir        → texto legible para el panel; recibe la petición, el "antes", el id,
//                      si tuvo éxito y el mensaje de error de la respuesta
//   registrarFallos  → si también se guarda cuando la operación es rechazada
//                      (ej. contraseña incorrecta, pago rechazado, QR inválido)
// Agregar una acción nueva = agregar una entrada aquí (y un test en BitacoraTest).
class CatalogoBitacora
{
    private const ROLES = ['admin' => 'Administrador', 'organizador' => 'Operador', 'cliente' => 'Cliente'];

    public function __construct(private IContextoBitacora $contexto) {}

    public function buscar(Request $request): ?array
    {
        $ruta = $request->route();
        if (! $ruta) {
            return null;
        }

        return $this->definiciones()[$request->method().' '.$ruta->uri()] ?? null;
    }

    private function definiciones(): array
    {
        $c = $this->contexto;
        $evento = fn (Request $r) => $c->evento((int) $r->route('id'));
        $usuario = fn (Request $r) => $c->usuario($this->idUsuarioDeRuta($r));
        $nombreEvento = fn (?array $antes, Request $r) => '«'.($antes['nombre'] ?? $r->input('nombre') ?? 'sin nombre').'»';
        $nombreUsuario = fn (?array $antes) => $antes ? trim("{$antes['nombre']} {$antes['apellido']}")." ({$antes['email']})" : 'un usuario';
        $motivo = fn (?string $error) => $error ? ": {$error}" : '';

        return [
            // ---------- Sesiones ----------
            'POST api/auth/login' => [
                'categoria' => C::SESION, 'accion' => 'sesion.inicio', 'entidad' => 'usuario',
                'campos' => ['email'], 'registrarFallos' => true,
                'describir' => fn (Request $r, $antes, $id, bool $exito, ?string $error, ?string $codigo) => $exito
                    ? 'Inició sesión con correo y contraseña'
                    : match ($codigo) {
                        'credenciales_invalidas' => "Intento de inicio de sesión con contraseña incorrecta ({$r->input('email')})",
                        'usuario_bloqueado' => "Una cuenta bloqueada intentó iniciar sesión ({$r->input('email')})",
                        'correo_no_verificado' => "Intento de inicio de sesión sin verificar el correo ({$r->input('email')})",
                        'demasiados_intentos' => "Demasiados intentos de inicio de sesión ({$r->input('email')})",
                        default => "Intento fallido de inicio de sesión ({$r->input('email')})",
                    },
            ],
            'GET auth/google/callback' => [
                'categoria' => C::SESION, 'accion' => 'sesion.inicio_google', 'entidad' => 'usuario',
                'campos' => [], 'registrarFallos' => true,
                'describir' => fn (Request $r, $antes, $id, bool $exito) => $exito
                    ? 'Inició sesión con Google' : 'Intento fallido de inicio de sesión con Google',
            ],
            'POST api/auth/logout' => [
                'categoria' => C::SESION, 'accion' => 'sesion.cierre', 'entidad' => 'usuario', 'campos' => [],
                'describir' => fn () => 'Cerró sesión',
            ],
            'POST api/auth/logout-panel' => [
                'categoria' => C::SESION, 'accion' => 'sesion.cierre', 'entidad' => 'usuario', 'campos' => [],
                'describir' => fn () => 'Cerró sesión en el panel administrativo',
            ],
            'POST api/auth/logout-todos' => [
                'categoria' => C::SESION, 'accion' => 'sesion.cierre_todas', 'entidad' => 'usuario', 'campos' => [],
                'describir' => fn () => 'Cerró sesión en todos sus dispositivos',
            ],

            // ---------- Cuentas ----------
            'POST api/auth/registro' => [
                'categoria' => C::CUENTA, 'accion' => 'cuenta.registro', 'entidad' => 'usuario',
                'campos' => ['nombre', 'apellido', 'email'],
                'describir' => fn (Request $r) => "Creó una cuenta con el correo {$r->input('email')}",
            ],
            'POST api/auth/verificar-correo' => [
                'categoria' => C::CUENTA, 'accion' => 'cuenta.correo_verificado', 'entidad' => 'usuario',
                'campos' => ['email'], 'registrarFallos' => true,
                'describir' => fn (Request $r, $antes, $id, bool $exito, ?string $error) => $exito
                    ? "Verificó su correo ({$r->input('email')}) e inició sesión"
                    : "Intento fallido de verificar el correo {$r->input('email')}{$motivo($error)}",
            ],
            'POST api/auth/olvide-password' => [
                'categoria' => C::CUENTA, 'accion' => 'cuenta.recuperacion_solicitada', 'entidad' => 'usuario',
                'campos' => ['email'],
                'describir' => fn (Request $r) => "Solicitó recuperar la contraseña de {$r->input('email')}",
            ],
            'POST api/auth/restablecer-password' => [
                'categoria' => C::CUENTA, 'accion' => 'cuenta.password_restablecida', 'entidad' => 'usuario',
                'campos' => ['email'], 'registrarFallos' => true,
                'describir' => fn (Request $r, $antes, $id, bool $exito, ?string $error) => $exito
                    ? "Restableció la contraseña de {$r->input('email')}"
                    : "Intento fallido de restablecer la contraseña de {$r->input('email')}{$motivo($error)}",
            ],
            'POST api/auth/cambiar-password' => [
                'categoria' => C::CUENTA, 'accion' => 'cuenta.password_cambiada', 'entidad' => 'usuario',
                'campos' => [], 'registrarFallos' => true,
                'describir' => fn (Request $r, $antes, $id, bool $exito, ?string $error) => $exito
                    ? 'Cambió su contraseña' : "Intento fallido de cambiar su contraseña{$motivo($error)}",
            ],
            'PATCH api/auth/actualizar-mi-perfil' => [
                'categoria' => C::CUENTA, 'accion' => 'cuenta.perfil_editado', 'entidad' => 'usuario',
                'campos' => ['nombre', 'apellido'],
                'antes' => fn (Request $r) => ($u = $c->usuario((int) $r->user('sanctum')?->id)) ? array_intersect_key($u, array_flip(['nombre', 'apellido'])) : null,
                'describir' => fn (Request $r) => "Actualizó su nombre a {$r->input('nombre')} {$r->input('apellido')}",
            ],

            // ---------- Compras ----------
            'POST api/reservas' => [
                'categoria' => C::COMPRA, 'accion' => 'compra.reserva_creada', 'entidad' => 'reserva',
                'campos' => ['evento_id', 'inventario_ids', 'cupos'], 'registrarFallos' => true,
                'describir' => function (Request $r, $antes, $id, bool $exito, ?string $error) use ($c, $motivo) {
                    $lugares = count((array) $r->input('inventario_ids', []))
                        + array_sum(array_map(fn ($cupo) => (int) ($cupo['cantidad'] ?? 0), (array) $r->input('cupos', [])));
                    $evento = '«'.($c->evento((int) $r->input('evento_id'))['nombre'] ?? "evento #{$r->input('evento_id')}").'»';
                    $texto = $lugares === 1 ? '1 lugar' : "{$lugares} lugares";

                    return $exito
                        ? "Apartó {$texto} para {$evento} (reserva #{$id})"
                        : "No pudo apartar {$texto} para {$evento}{$motivo($error)}";
                },
            ],
            'DELETE api/reservas/{id}' => [
                'categoria' => C::COMPRA, 'accion' => 'compra.reserva_cancelada', 'entidad' => 'reserva',
                'campos' => [], 'antes' => fn (Request $r) => $c->reserva((int) $r->route('id')),
                'describir' => fn (Request $r, $antes) => "Canceló la reserva #{$r->route('id')}"
                    .($antes ? " de «{$antes['evento']}»" : ''),
            ],
            'POST api/cola-virtual/ingresar' => [
                'categoria' => C::COMPRA, 'accion' => 'compra.cola_virtual', 'entidad' => 'evento',
                'campos' => ['evento_id'], 'id' => fn (Request $r) => $r->input('evento_id'),
                'describir' => fn (Request $r) => 'Entró a la cola virtual de «'
                    .($c->evento((int) $r->input('evento_id'))['nombre'] ?? "evento #{$r->input('evento_id')}").'»',
            ],
            'POST api/pagos' => [
                'categoria' => C::COMPRA, 'accion' => 'compra.pago_iniciado', 'entidad' => 'pago',
                'campos' => ['reserva_id', 'pasarela'], 'id' => fn (Request $r, ?array $respuesta) => $respuesta['pago_id'] ?? null,
                'describir' => fn (Request $r, $antes, $id) => "Inició el pago #{$id} de la reserva #{$r->input('reserva_id')}",
            ],
            'POST api/pagos/{id}/confirmar' => [
                'categoria' => C::COMPRA, 'accion' => 'compra.pago_confirmado', 'entidad' => 'pago',
                'campos' => [], 'registrarFallos' => true,
                'antes' => fn (Request $r) => $c->pago((int) $r->route('id')),
                'describir' => function (Request $r, $antes, $id, bool $exito, ?string $error) use ($motivo) {
                    $detalle = $antes ? ' de Q'.number_format((float) $antes['monto'], 2)." para «{$antes['evento']}»" : '';

                    return $exito
                        ? "Compró: pago #{$id}{$detalle} aprobado"
                        : "Pago #{$id}{$detalle} rechazado{$motivo($error)}";
                },
            ],

            // ---------- Eventos ----------
            'POST api/admin/eventos' => [
                'categoria' => C::EVENTO, 'accion' => 'evento.creado', 'entidad' => 'evento',
                'campos' => ['nombre', 'recinto_id', 'fecha_evento', 'estado', 'costos'],
                'describir' => fn (Request $r) => "Creó el evento «{$r->input('nombre')}»"
                    . (($n = count((array) $r->input('costos', []))) ? ' con ' . ($n === 1 ? '1 costo' : "{$n} costos") . ' de producción' : ''),
            ],
            'PUT api/admin/eventos/{id}/costos' => [
                'categoria' => C::EVENTO, 'accion' => 'evento.costos_guardados', 'entidad' => 'evento',
                'campos' => ['costos'],
                'antes' => fn (Request $r) => ($e = $c->evento((int) $r->route('id')))
                    ? ['nombre' => $e['nombre'], 'costos_total' => $c->costosDeEvento((int) $r->route('id'))] : null,
                'describir' => fn (Request $r, $antes) => "Actualizó los costos de producción de {$nombreEvento($antes, $r)}: Q"
                    . number_format(array_sum(array_map(fn ($costo) => (float) ($costo['monto'] ?? 0), (array) $r->input('costos', []))), 2)
                    . ($antes ? ' (antes Q' . number_format((float) $antes['costos_total'], 2) . ')' : ''),
            ],
            'PUT api/admin/eventos/{id}' => [
                'categoria' => C::EVENTO, 'accion' => 'evento.editado', 'entidad' => 'evento',
                'campos' => ['nombre', 'recinto_id', 'fecha_evento', 'estado'], 'antes' => $evento,
                'describir' => fn (Request $r, $antes) => "Editó el evento {$nombreEvento($antes, $r)}",
            ],
            'PATCH api/admin/eventos/{id}/contenido' => [
                'categoria' => C::EVENTO, 'accion' => 'evento.contenido_editado', 'entidad' => 'evento',
                'campos' => ['nombre', 'descripcion', 'imagen_url'], 'antes' => $evento,
                'describir' => fn (Request $r, $antes) => "Modificó el evento publicado {$nombreEvento($antes, $r)}",
            ],
            'PATCH api/admin/eventos/{id}/publicar' => [
                'categoria' => C::EVENTO, 'accion' => 'evento.publicado', 'entidad' => 'evento',
                'campos' => [], 'antes' => $evento,
                'describir' => fn (Request $r, $antes) => "Publicó el evento {$nombreEvento($antes, $r)}",
            ],
            'PATCH api/admin/eventos/{id}/imagen' => [
                'categoria' => C::EVENTO, 'accion' => 'evento.imagen_cambiada', 'entidad' => 'evento',
                'campos' => ['imagen_url'], 'antes' => fn (Request $r) => ($e = $c->evento((int) $r->route('id'))) ? ['nombre' => $e['nombre'], 'imagen_url' => $e['imagen_url']] : null,
                'describir' => fn (Request $r, $antes) => "Cambió la imagen del evento {$nombreEvento($antes, $r)}",
            ],
            'PUT api/admin/eventos/{id}/precios' => [
                'categoria' => C::EVENTO, 'accion' => 'evento.precios_guardados', 'entidad' => 'evento',
                'campos' => ['precios'], 'antes' => $evento,
                'describir' => fn (Request $r, $antes) => "Guardó los precios del evento {$nombreEvento($antes, $r)}",
            ],
            'DELETE api/admin/eventos/{id}' => [
                'categoria' => C::EVENTO, 'accion' => 'evento.eliminado', 'entidad' => 'evento',
                'campos' => [], 'antes' => $evento,
                'describir' => fn (Request $r, $antes) => "Eliminó el borrador del evento {$nombreEvento($antes, $r)}",
            ],
            'POST api/eventos/{id}/cancelar' => [
                'categoria' => C::EVENTO, 'accion' => 'evento.cancelado', 'entidad' => 'evento',
                'campos' => [], 'antes' => $evento,
                'describir' => fn (Request $r, $antes) => "Canceló el evento {$nombreEvento($antes, $r)} (reembolsos pendientes)",
            ],

            // ---------- Usuarios (panel) ----------
            'POST api/admin/usuarios' => [
                'categoria' => C::USUARIO, 'accion' => 'usuario.creado', 'entidad' => 'usuario',
                'campos' => ['nombre', 'apellido', 'email', 'rol'],
                'describir' => fn (Request $r) => "Creó la cuenta de {$r->input('nombre')} {$r->input('apellido')} ({$r->input('email')})",
            ],
            'PUT api/admin/usuarios/{usuario}' => [
                'categoria' => C::USUARIO, 'accion' => 'usuario.editado', 'entidad' => 'usuario',
                'campos' => ['nombre', 'apellido', 'email'], 'antes' => $usuario,
                'id' => fn (Request $r) => $this->idUsuarioDeRuta($r),
                'describir' => fn (Request $r, $antes) => "Editó los datos de {$nombreUsuario($antes)}",
            ],
            'PATCH api/admin/usuarios/{usuario}/rol' => [
                'categoria' => C::USUARIO, 'accion' => 'usuario.rol_cambiado', 'entidad' => 'usuario',
                'campos' => ['rol'], 'antes' => $usuario,
                'id' => fn (Request $r) => $this->idUsuarioDeRuta($r),
                'describir' => fn (Request $r, $antes) => "Cambió el rol de {$nombreUsuario($antes)} de "
                    .(self::ROLES[$antes['rol'] ?? ''] ?? ($antes['rol'] ?? '?')).' a '
                    .(self::ROLES[$r->input('rol')] ?? $r->input('rol')),
            ],
            'PATCH api/admin/usuarios/{usuario}/estado' => [
                'categoria' => C::USUARIO, 'accion' => 'usuario.estado_cambiado', 'entidad' => 'usuario',
                'campos' => ['estado'], 'antes' => $usuario,
                'id' => fn (Request $r) => $this->idUsuarioDeRuta($r),
                'describir' => fn (Request $r, $antes) => ($r->input('estado') === 'bloqueado' ? 'Bloqueó' : 'Desbloqueó')
                    ." la cuenta de {$nombreUsuario($antes)}",
            ],
            'DELETE api/admin/usuarios/{usuario}' => [
                'categoria' => C::USUARIO, 'accion' => 'usuario.eliminado', 'entidad' => 'usuario',
                'campos' => [], 'antes' => $usuario,
                'id' => fn (Request $r) => $this->idUsuarioDeRuta($r),
                'describir' => fn (Request $r, $antes) => "Eliminó la cuenta de {$nombreUsuario($antes)}",
            ],

            // ---------- Contenido ----------
            'POST api/admin/banner' => [
                'categoria' => C::CONTENIDO, 'accion' => 'contenido.banner_cambiado', 'entidad' => 'banner', 'campos' => [],
                'describir' => fn (Request $r) => 'Cambió el banner del catálogo'
                    .($r->file('archivo') ? ' ('.$r->file('archivo')->getClientOriginalName().')' : ''),
            ],
            'DELETE api/admin/banner' => [
                'categoria' => C::CONTENIDO, 'accion' => 'contenido.banner_quitado', 'entidad' => 'banner', 'campos' => [],
                'describir' => fn () => 'Quitó el banner del catálogo (vuelve la foto por defecto)',
            ],

            // ---------- Reportes ----------
            'GET api/admin/reportes/excel' => $this->reporte('Excel'),
            'GET api/admin/reportes/pdf' => $this->reporte('PDF'),
            'GET api/admin/reportes/csv' => $this->reporte('CSV'),

            // ---------- Reembolsos ----------
            'POST api/reembolsos' => [
                'categoria' => C::REEMBOLSO, 'accion' => 'reembolso.solicitado', 'entidad' => 'reembolso',
                'campos' => ['pago_id', 'motivo'],
                'describir' => fn (Request $r) => "Solicitó el reembolso del pago #{$r->input('pago_id')}",
            ],
            'PATCH api/reembolsos/{id}/resolver' => [
                'categoria' => C::REEMBOLSO, 'accion' => 'reembolso.resuelto', 'entidad' => 'reembolso',
                'campos' => ['decision'], 'antes' => fn (Request $r) => $c->reembolso((int) $r->route('id')),
                'describir' => fn (Request $r) => ($r->input('decision') === 'aprobar' ? 'Aprobó' : 'Rechazó')
                    ." el reembolso #{$r->route('id')}",
            ],

            // ---------- Boletos (entrada al evento) ----------
            // El código QR es secreto: nunca se guarda
            'POST api/boletos/validar' => [
                'categoria' => C::BOLETO, 'accion' => 'boleto.validado', 'entidad' => 'boleto',
                'campos' => ['evento_id'], 'registrarFallos' => true,
                'id' => fn (Request $r, ?array $respuesta) => $respuesta['boleto']['id'] ?? null,
                'describir' => fn (Request $r, $antes, $id, bool $exito) => ($exito
                    ? "Validó el boleto #{$id} en la entrada de «"
                    : 'Boleto rechazado en la entrada de «')
                    .($c->evento((int) $r->input('evento_id'))['nombre'] ?? "evento #{$r->input('evento_id')}").'»',
            ],
        ];
    }

    private function reporte(string $formato): array
    {
        return [
            'categoria' => C::REPORTE, 'accion' => 'reporte.exportado', 'entidad' => 'reporte',
            'campos' => ['tipo', 'desde', 'hasta'], 'id' => fn () => null,
            'describir' => fn (Request $r) => "Exportó el reporte de {$r->input('tipo')} en {$formato}"
                .($r->input('desde') || $r->input('hasta')
                    ? ' ('.($r->input('desde') ?: 'inicio').' a '.($r->input('hasta') ?: 'hoy').')'
                    : ''),
        ];
    }

    // {usuario} llega ya convertido en modelo (route model binding) o como id
    private function idUsuarioDeRuta(Request $request): int
    {
        $usuario = $request->route('usuario');

        return (int) (is_object($usuario) ? $usuario->getKey() : $usuario);
    }
}
