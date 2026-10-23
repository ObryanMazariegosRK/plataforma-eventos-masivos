<?php

namespace App\Domain\Abstractions\Bitacora;

// Datos legibles de lo que se va a modificar, para que la bitácora diga "Editó el evento
// «Concierto»" y guarde cómo estaba antes del cambio. Devuelve null si no existe.
interface IContextoBitacora
{
    public function evento(int $id): ?array;

    public function usuario(int $id): ?array;

    public function reserva(int $id): ?array;

    public function pago(int $id): ?array;

    public function reembolso(int $id): ?array;

    // Suma de los costos de producción vigentes del evento
    public function costosDeEvento(int $eventoId): float;
}
