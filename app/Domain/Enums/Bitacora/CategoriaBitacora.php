<?php

namespace App\Domain\Enums\Bitacora;

// Área del sistema a la que pertenece un registro de la bitácora (filtro "Categoría" del panel)
enum CategoriaBitacora: string
{
    case SESION = 'sesion';      // inicios y cierres de sesión, accesos denegados
    case CUENTA = 'cuenta';      // registro, verificación, contraseñas, perfil
    case COMPRA = 'compra';      // reservas, pagos, cola virtual
    case EVENTO = 'evento';      // crear, editar, publicar, precios, cancelar
    case USUARIO = 'usuario';     // gestión de usuarios desde el panel
    case CONTENIDO = 'contenido';   // banner del catálogo
    case REPORTE = 'reporte';     // exportaciones
    case REEMBOLSO = 'reembolso';
    case BOLETO = 'boleto';      // validación de boletos en la entrada

    public function etiqueta(): string
    {
        return match ($this) {
            self::SESION => 'Sesiones',
            self::CUENTA => 'Cuentas',
            self::COMPRA => 'Compras',
            self::EVENTO => 'Eventos',
            self::USUARIO => 'Usuarios',
            self::CONTENIDO => 'Contenido',
            self::REPORTE => 'Reportes',
            self::REEMBOLSO => 'Reembolsos',
            self::BOLETO => 'Boletos',
        };
    }
}
