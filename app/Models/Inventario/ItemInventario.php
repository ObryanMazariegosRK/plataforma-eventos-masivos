<?php
namespace App\Models\Inventario;

use App\Domain\Enums\Inventario\EstadoAsiento;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ItemInventario extends Model
{
    use SoftDeletes;

    protected $table = 'inventario';

    protected $fillable = ['evento_id', 'localidad_id', 'asiento_id', 'estado', 'reserva_id', 'bloqueado_hasta'];

    protected function casts(): array
    {
        return [
            'estado'          => EstadoAsiento::class,
            'bloqueado_hasta' => 'datetime',
        ];
    }
}
