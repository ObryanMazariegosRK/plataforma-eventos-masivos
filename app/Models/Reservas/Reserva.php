<?php
namespace App\Models\Reservas;

use App\Domain\Enums\Reservas\EstadoReserva;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reserva extends Model
{
    use SoftDeletes;

    protected $table = 'reservas';

    protected $fillable = ['usuario_id', 'evento_id', 'estado', 'expira_en', 'total'];

    //Conversiones de tipos
    protected function casts(): array
    {
        return [
            'estado'    => EstadoReserva::class,
            'expira_en' => 'datetime',
            'total'     => 'decimal:2',
        ];
    }
}
