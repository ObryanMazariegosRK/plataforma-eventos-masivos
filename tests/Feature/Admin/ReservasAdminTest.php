<?php

namespace Tests\Feature\Admin;

use App\Domain\Enums\Auth\EstadoUsuario;
use App\Domain\Enums\Auth\RolUsuario;
use App\Models\Auth\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

// Panel → Reservas: filtros por estado y por evento
class ReservasAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_filtra_las_reservas_por_estado_y_por_evento(): void
    {
        $admin = Usuario::create([
            'nombre' => 'Ana', 'apellido' => 'López', 'email' => 'admin@test.com', 'password' => bcrypt('Secreto#123'),
            'rol' => RolUsuario::ADMIN, 'estado' => EstadoUsuario::ACTIVO, 'email_verified_at' => now(),
        ]);
        $recinto = DB::table('recintos')->insertGetId(['nombre' => 'Teatro', 'direccion' => 'Z1', 'ciudad' => 'Guatemala', 'created_at' => now(), 'updated_at' => now()]);
        $evento = fn (string $nombre) => DB::table('eventos')->insertGetId(['recinto_id' => $recinto, 'nombre' => $nombre, 'estado' => 'publicado', 'fecha_evento' => now()->addMonth(), 'created_at' => now(), 'updated_at' => now()]);
        [$gala, $jazz] = [$evento('Gala'), $evento('Jazz')];
        $reserva = fn (int $eventoId, string $estado) => DB::table('reservas')->insertGetId(['usuario_id' => $admin->id, 'evento_id' => $eventoId, 'estado' => $estado, 'total' => 100, 'expira_en' => now(), 'created_at' => now(), 'updated_at' => now()]);
        $pagada = $reserva($gala, 'confirmada');
        $reserva($gala, 'vencida');
        $deJazz = $reserva($jazz, 'confirmada');

        Sanctum::actingAs($admin);
        $ids = fn (string $consulta) => array_column($this->getJson('/api/admin/reservas?'.$consulta)->assertOk()->json('data'), 'id');

        $this->assertSame([$deJazz, $pagada], $ids('estado=confirmada'));
        $this->assertSame([$pagada], $ids("estado=confirmada&evento_id={$gala}"));
        $this->assertCount(3, $ids(''));
        $this->getJson('/api/admin/reservas?estado=inventado')->assertStatus(422);
    }
}
