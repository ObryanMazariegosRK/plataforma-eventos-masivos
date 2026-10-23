<?php

namespace App\Application\Abstractions\Inventario;

interface IConsultarPlanoRecintoUseCase
{
    // Plano del recinto (localidades y butacas) para mostrarlo al crear un evento
    public function execute(int $recintoId): array;
}
