<?php
namespace App\Models\Auth;

use App\Domain\Enums\Auth\EstadoUsuario;
use App\Domain\Enums\Auth\RolUsuario;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

// Extiende Authenticatable (no Model) para que Laravel/Sanctum
// puedan autenticarlo; HasApiTokens agrega createToken() y tokens().
class Usuario extends Authenticatable
{
    use HasApiTokens, SoftDeletes;

    protected $table = 'usuarios';

    protected $fillable = [
        'nombre', 'apellido', 'email', 'google_id', 'password', 'telefono', 'rol', 'estado',
        'email_verified_at', 'codigo_verificacion', 'codigo_expira_en', 'intentos_verificacion',
        'codigo_recuperacion', 'recuperacion_expira_en', 'intentos_recuperacion',
    ];

    // nunca se incluyen al convertir el modelo a JSON
    protected $hidden = ['password', 'remember_token', 'codigo_verificacion', 'codigo_recuperacion'];

    protected function casts(): array
    {
        return [
            'rol'               => RolUsuario::class,
            'estado'            => EstadoUsuario::class,
            'email_verified_at' => 'datetime',
            'codigo_expira_en'  => 'datetime',
            'recuperacion_expira_en' => 'datetime',
        ];
    }
}
