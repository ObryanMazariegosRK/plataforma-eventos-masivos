<?php
namespace App\Models\Pagos;

use App\Domain\Enums\Pagos\EstadoPago;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pago extends Model
{
    use SoftDeletes;

    protected $table = 'pagos';

    protected $fillable = [
        'reserva_id', 'monto', 'estado', 'intento', 'pasarela_id', 'orden_externa', 'referencia_externa',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoPago::class,
            'monto'  => 'decimal:2',
        ];
    }
}
