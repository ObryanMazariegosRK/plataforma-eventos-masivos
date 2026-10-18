<?php
namespace App\Models\Pasarelas;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pasarela extends Model
{
    use SoftDeletes;

    protected $table = 'pasarelas';

    protected $fillable = ['nombre', 'codigo', 'activa'];

    protected function casts(): array
    {
        return ['activa' => 'boolean'];
    }
}
