<?php

namespace App\Models\Boletos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Reservas\Reserva;
use App\Models\Auth\Usuario;

class Boleto extends Model
{
    use SoftDeletes;

    protected $table = 'boletos';

    protected $fillable = [
        'reserva_id',
        'inventario_id',
        'usuario_id',
        'codigo_qr',
    ];


   protected function casts(): array
  {
    return [
        'estado' => EstadoBoleto::class,
        'usado_en' => 'datetime',
    ];
  }


    // Relación con la reserva
    public function reserva()
    {
        return $this->belongsTo(Reserva::class, 'reserva_id');
    }

    // Relación con el propietario del boleto
    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}

use App\Domain\Enums\Boletos\EstadoBoleto;