<?php
namespace App\Domain\Abstractions\Inventario;

use App\Domain\Entities\Inventario\ItemInventario;

interface IInventarioRepository
{
    /**
     * Asientos/cupos que pertenecen a una reserva.
     * Con $bloquear = true, las filas quedan bloqueadas (FOR UPDATE) hasta el fin de la transacción.
     *
     * @return list<ItemInventario>
     */
    public function buscarPorReserva(int $reservaId, bool $bloquear = false): array;

    public function guardar(ItemInventario $item): ItemInventario;

    // ---- Generación del inventario de un evento ----

    public function existeParaEvento(int $eventoId): bool;

    /**
     * Nombres de las localidades del recinto del evento que NO tienen precio (o tienen precio <= 0).
     *
     * @return list<string>
     */
    public function localidadesSinPrecio(int $eventoId): array;

    /**
     * Crea los lugares a la venta del evento a partir de su recinto:
     * una fila por asiento en localidades numeradas y `capacidad` cupos en las generales.
     * Devuelve cuántos lugares creó.
     */
    public function generarParaEvento(int $eventoId): int;

    // ---- Consulta ----

    /**
     * Disponibilidad de un evento publicado, agrupada por localidad, o null si el evento
     * no existe o no está a la venta. Estructura:
     *   evento:      { id, nombre, fecha_evento, recinto }
     *   localidades: [{ id, nombre, tipo, color, precio, capacidad, disponibles, bloqueados, vendidos,
     *                   asientos: [{ inventario_id, fila, numero, estado }] }]   (asientos solo en numeradas)
     *   resumen:     { total, disponibles, bloqueados, vendidos }
     */
    public function disponibilidad(int $eventoId): ?array;

    /**
     * Huella corta del estado de los lugares de un evento a la venta: cambia en cuanto un lugar
     * se aparta, se vende o se libera. El mapa la consulta seguido y solo descarga
     * disponibilidad() cuando cambió. null si el evento no está a la venta.
     */
    public function huellaDisponibilidad(int $eventoId): ?string;

    /**
     * Plano de un recinto activo con el mismo formato que disponibilidad() (todos los lugares
     * libres y sin precio), para verlo antes de crear un evento. null si no existe.
     */
    public function planoRecinto(int $recintoId): ?array;
}
