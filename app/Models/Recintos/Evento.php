<?php

namespace App\Models\Recintos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Evento extends Model
{
    use SoftDeletes;

    protected $table = 'eventos';

    protected $fillable = [
        'recinto_id',
        'nombre',
        'descripcion',
        'fecha_evento',
        'estado',
        'fecha_publicacion',
    ];

    protected $casts = [
        'fecha_evento' => 'datetime',
        'fecha_publicacion' => 'datetime',
    ];
}
