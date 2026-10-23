<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Recintos fijos de la plataforma (máximo 100 lugares cada uno).
 * El administrador no crea recintos: elige uno de estos al crear un evento.
 *
 * - Localidad "numerada": cada lugar es un asiento con fila y número (tabla asientos).
 * - Localidad "general": solo un cupo (capacidad), sin asientos individuales.
 *
 * Se puede correr varias veces: si el recinto ya existe (por nombre), no lo duplica.
 */
class RecintoSeeder extends Seeder
{
    private const RECINTOS = [
        [
            'nombre'      => 'Teatro Soirée',
            'direccion'   => '6a Avenida 8-20, Zona 1',
            'ciudad'      => 'Guatemala',
            'descripcion' => 'Teatro clásico a la italiana, con platea y balcón.',
            'localidades' => [
                // filas y asientos por fila → 5 × 12 = 60
                ['nombre' => 'Platea', 'tipo' => 'numerada', 'color' => '#c8b48c', 'filas' => ['A', 'B', 'C', 'D', 'E'], 'por_fila' => 12],
                // 2 × 10 = 20
                ['nombre' => 'Balcón', 'tipo' => 'numerada', 'color' => '#9fb2c4', 'filas' => ['F', 'G'], 'por_fila' => 10],
            ],
        ],
        [
            'nombre'      => 'Auditorio Central',
            'direccion'   => 'Boulevard Los Próceres 18-60, Zona 10',
            'ciudad'      => 'Guatemala',
            'descripcion' => 'Auditorio con zona VIP numerada y zona general de pie.',
            'localidades' => [
                ['nombre' => 'VIP', 'tipo' => 'numerada', 'color' => '#c8b48c', 'filas' => ['A', 'B'], 'por_fila' => 10],
                ['nombre' => 'General', 'tipo' => 'general', 'color' => '#a9bfa4', 'capacidad' => 60],
            ],
        ],
    ];

    public function run(): void
    {
        foreach (self::RECINTOS as $recinto) {
            DB::transaction(fn () => $this->crearRecinto($recinto));
        }
    }

    private function crearRecinto(array $datos): void
    {
        $existe = DB::table('recintos')->where('nombre', $datos['nombre'])->whereNull('deleted_at')->exists();
        if ($existe) {
            return;
        }

        $ahora = now();
        $recintoId = DB::table('recintos')->insertGetId([
            'nombre'      => $datos['nombre'],
            'direccion'   => $datos['direccion'],
            'ciudad'      => $datos['ciudad'],
            'descripcion' => $datos['descripcion'],
            'estado'      => 'activo',
            'created_at'  => $ahora,
            'updated_at'  => $ahora,
        ]);

        foreach ($datos['localidades'] as $localidad) {
            $numerada = $localidad['tipo'] === 'numerada';
            $capacidad = $numerada ? count($localidad['filas']) * $localidad['por_fila'] : $localidad['capacidad'];

            $localidadId = DB::table('localidades')->insertGetId([
                'recinto_id' => $recintoId,
                'nombre'     => $localidad['nombre'],
                'tipo'       => $localidad['tipo'],
                'capacidad'  => $capacidad,
                'color'      => $localidad['color'],
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);

            if (! $numerada) {
                continue;
            }

            // pos_x / pos_y: columna y fila dentro de la localidad, para dibujar el mapa
            $asientos = [];
            foreach ($localidad['filas'] as $y => $fila) {
                for ($numero = 1; $numero <= $localidad['por_fila']; $numero++) {
                    $asientos[] = [
                        'localidad_id' => $localidadId,
                        'fila'         => $fila,
                        'numero'       => $numero,
                        'pos_x'        => $numero,
                        'pos_y'        => $y + 1,
                        'created_at'   => $ahora,
                        'updated_at'   => $ahora,
                    ];
                }
            }
            DB::table('asientos')->insert($asientos);
        }
    }
}
