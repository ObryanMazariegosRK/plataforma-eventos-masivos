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
        'frase_destacada',
        'descripcion',
        'imagen_url',
        'imagen_url_2',
        'imagen_url_3',
        'fecha_evento',
        'estado',
        'fecha_publicacion',
    ];

    protected $casts = [
        'fecha_evento' => 'datetime',
        'fecha_publicacion' => 'datetime',
    ];
}
