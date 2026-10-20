<?php

namespace App\Models\Catalogo;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    protected $table = 'banners';

    protected $fillable = [
        'tipo',
        'ruta',
        'created_by',
        'updated_by',
    ];
}
