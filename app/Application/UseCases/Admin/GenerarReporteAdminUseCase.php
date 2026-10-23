<?php

namespace App\Application\UseCases\Admin;

use App\Application\Abstractions\Admin\IGenerarReporteAdminUseCase;
use App\Application\DTOs\Admin\GenerarReporteAdminDTO;
use App\Domain\Abstractions\Admin\IReporteAdminRepository;
use App\Domain\Exceptions\Admin\ReporteDemasiadoGrandeException;
use DateTimeImmutable;
use DateTimeZone;

// Arma el informe del panel (eventos, reservas, pagos o usuarios): indicadores, desglose por
// estado (o por rol) y el detalle fila por fila. El Excel y el PDF solo lo dibujan.
class GenerarReporteAdminUseCase implements IGenerarReporteAdminUseCase
{
    private const TITULOS = [
        'eventos' => 'Reporte de eventos',
        'reservas' => 'Reporte de reservas',
        'pagos' => 'Reporte de pagos',
        'usuarios' => 'Reporte de usuarios',
    ];

    // Todos los estados posibles, en orden lógico (los que no tienen registros salen en 0)
    private const ESTADOS = [
        'eventos' => ['borrador' => 'Borrador', 'publicado' => 'Publicado', 'agotado' => 'Agotado', 'finalizado' => 'Finalizado', 'cancelado' => 'Cancelado'],
        'reservas' => ['activa' => 'Activa (apartada)', 'confirmada' => 'Confirmada (pagada)', 'vencida' => 'Vencida', 'cancelada' => 'Cancelada'],
        'pagos' => ['pendiente' => 'Pendiente', 'aprobado' => 'Aprobado', 'rechazado' => 'Rechazado'],
        'usuarios' => ['activo' => 'Activo', 'bloqueado' => 'Bloqueado'],
    ];

    private const ROLES = ['admin' => 'Administrador', 'organizador' => 'Operador', 'cliente' => 'Cliente'];

    private DateTimeZone $zona;

    public function __construct(private IReporteAdminRepository $reportes) {}

    public function execute(GenerarReporteAdminDTO $dto): array
    {
        $this->zona = new DateTimeZone($dto->zonaHoraria);
        $utc = new DateTimeZone('UTC');
        // El día elegido en Guatemala va de sus 00:00 a las 00:00 del día siguiente, en UTC
        $limiteDia = fn (?string $dia, string $mas) => $dia
            ? (new DateTimeImmutable($dia, $this->zona))->modify($mas)->setTime(0, 0)->setTimezone($utc)->format('Y-m-d H:i:s')
            : null;

        $filas = $this->reportes->filas($dto->tipo, $limiteDia($dto->desde, '+0 day'), $limiteDia($dto->hasta, '+1 day'), $dto->limite + 1);
        if (count($filas) > $dto->limite) {
            throw new ReporteDemasiadoGrandeException($dto->limite);
        }

        $informe = match ($dto->tipo) {
            'eventos' => $this->eventos($filas),
            'reservas' => $this->reservas($filas),
            'pagos' => $this->pagos($filas),
            'usuarios' => $this->usuarios($filas),
        };

        return [
            'tipo' => $dto->tipo,
            'titulo' => self::TITULOS[$dto->tipo],
            'periodo' => $this->periodo($dto->desde, $dto->hasta),
            'generado' => new DateTimeImmutable('now', $this->zona),
            'generado_por' => $dto->generadoPor,
            'total' => count($filas),
        ] + $informe;
    }

    private function eventos(array $filas): array
    {
        $vendidos = array_sum(array_column($filas, 'vendidos'));
        $lugares = array_sum(array_column($filas, 'lugares'));

        return [
            'indicadores' => [
                $this->indicador('Eventos', count($filas)),
                $this->indicador('Publicados', $this->contar($filas, 'estado', 'publicado')),
                $this->indicador('Boletos vendidos', (int) $vendidos, 'entero', $lugares ? "de {$lugares} lugares" : null),
                $this->indicador('Ingresos', (float) array_sum(array_column($filas, 'ingresos')), 'moneda', 'pagos aprobados'),
            ],
            'desglose' => $this->desglose('Por estado', $filas, 'estado', self::ESTADOS['eventos']),
            'columnas' => [
                $this->columna('id', 'N.º', 'entero', 8),
                $this->columna('nombre', 'Evento', 'texto', 30),
                $this->columna('recinto', 'Recinto', 'texto', 24),
                $this->columna('ciudad', 'Ciudad', 'texto', 14),
                $this->columna('fecha_evento', 'Fecha del evento', 'fecha', 19),
                $this->columna('estado', 'Estado', 'texto', 13),
                $this->columna('lugares', 'Lugares', 'entero', 10),
                $this->columna('vendidos', 'Vendidos', 'entero', 10),
                $this->columna('ocupacion', 'Ocupación', 'porcentaje', 11),
                $this->columna('ingresos', 'Ingresos', 'moneda', 14),
                $this->columna('created_at', 'Creado el', 'fecha', 19),
                $this->columna('creado_por', 'Creado por', 'texto', 22),
            ],
            'filas' => array_map(fn (array $f) => [
                'id' => (int) $f['id'],
                'nombre' => $f['nombre'],
                'recinto' => $f['recinto'],
                'ciudad' => $f['ciudad'],
                'fecha_evento' => $this->fecha($f['fecha_evento']),
                'estado' => self::ESTADOS['eventos'][$f['estado']] ?? $f['estado'],
                'lugares' => (int) $f['lugares'],
                'vendidos' => (int) $f['vendidos'],
                'ocupacion' => $f['lugares'] ? $f['vendidos'] / $f['lugares'] : null,
                'ingresos' => (float) $f['ingresos'],
                'created_at' => $this->fecha($f['created_at']),
                'creado_por' => $this->nombre($f['creador_nombre'], $f['creador_apellido']),
            ], $filas),
        ];
    }

    private function reservas(array $filas): array
    {
        $confirmadas = array_filter($filas, fn ($f) => $f['estado'] === 'confirmada');

        return [
            'indicadores' => [
                $this->indicador('Reservas', count($filas)),
                $this->indicador('Confirmadas', count($confirmadas), 'entero', 'ya pagadas'),
                $this->indicador('Boletos vendidos', (int) array_sum(array_column($confirmadas, 'lugares'))),
                $this->indicador('Total confirmado', (float) array_sum(array_column($confirmadas, 'total')), 'moneda'),
            ],
            'desglose' => $this->desglose('Por estado', $filas, 'estado', self::ESTADOS['reservas']),
            'columnas' => [
                $this->columna('id', 'N.º', 'entero', 8),
                $this->columna('evento', 'Evento', 'texto', 28),
                $this->columna('cliente', 'Cliente', 'texto', 24),
                $this->columna('email', 'Correo', 'texto', 30),
                $this->columna('lugares', 'Lugares', 'entero', 10),
                $this->columna('total', 'Total', 'moneda', 13),
                $this->columna('estado', 'Estado', 'texto', 19),
                $this->columna('created_at', 'Reservada el', 'fecha', 19),
                $this->columna('pagada_en', 'Pagada el', 'fecha', 19),
            ],
            'filas' => array_map(fn (array $f) => [
                'id' => (int) $f['id'],
                'evento' => $f['evento'],
                'cliente' => $this->nombre($f['nombre'], $f['apellido']),
                'email' => $f['email'],
                // Las reservas vencidas o canceladas ya liberaron sus lugares
                'lugares' => $f['lugares'] ? (int) $f['lugares'] : null,
                'total' => (float) $f['total'],
                'estado' => self::ESTADOS['reservas'][$f['estado']] ?? $f['estado'],
                'created_at' => $this->fecha($f['created_at']),
                'pagada_en' => $this->fecha($f['pagada_en']),
            ], $filas),
        ];
    }

    private function pagos(array $filas): array
    {
        $aprobados = array_filter($filas, fn ($f) => $f['estado'] === 'aprobado');

        return [
            'indicadores' => [
                $this->indicador('Pagos', count($filas)),
                $this->indicador('Aprobados', count($aprobados)),
                $this->indicador('Rechazados', $this->contar($filas, 'estado', 'rechazado')),
                $this->indicador('Monto aprobado', (float) array_sum(array_column($aprobados, 'monto')), 'moneda', 'sin pendientes ni rechazados'),
            ],
            'desglose' => $this->desglose('Por estado', $filas, 'estado', self::ESTADOS['pagos']),
            'columnas' => [
                $this->columna('id', 'N.º', 'entero', 8),
                $this->columna('reserva_id', 'Reserva', 'entero', 10),
                $this->columna('evento', 'Evento', 'texto', 26),
                $this->columna('cliente', 'Cliente', 'texto', 22),
                $this->columna('email', 'Correo', 'texto', 28),
                $this->columna('pasarela', 'Pasarela', 'texto', 22),
                $this->columna('intento', 'Intento', 'entero', 9),
                $this->columna('monto', 'Monto', 'moneda', 13),
                $this->columna('estado', 'Estado', 'texto', 12),
                $this->columna('referencia_externa', 'Referencia', 'texto', 22),
                $this->columna('created_at', 'Fecha', 'fecha', 19),
            ],
            'filas' => array_map(fn (array $f) => [
                'id' => (int) $f['id'],
                'reserva_id' => (int) $f['reserva_id'],
                'evento' => $f['evento'],
                'cliente' => $this->nombre($f['nombre'], $f['apellido']),
                'email' => $f['email'],
                'pasarela' => $f['pasarela'],
                'intento' => (int) $f['intento'],
                'monto' => (float) $f['monto'],
                'estado' => self::ESTADOS['pagos'][$f['estado']] ?? $f['estado'],
                'referencia_externa' => $f['referencia_externa'],
                'created_at' => $this->fecha($f['created_at']),
            ], $filas),
        ];
    }

    private function usuarios(array $filas): array
    {
        return [
            'indicadores' => [
                $this->indicador('Usuarios', count($filas)),
                $this->indicador('Clientes', $this->contar($filas, 'rol', 'cliente')),
                $this->indicador('Operadores', $this->contar($filas, 'rol', 'organizador')),
                $this->indicador('Con compras', count(array_filter($filas, fn ($f) => $f['compras'] > 0))),
            ],
            'desglose' => $this->desglose('Por rol', $filas, 'rol', self::ROLES),
            'columnas' => [
                $this->columna('id', 'N.º', 'entero', 8),
                $this->columna('nombre', 'Nombre', 'texto', 26),
                $this->columna('email', 'Correo', 'texto', 30),
                $this->columna('rol', 'Rol', 'texto', 15),
                $this->columna('estado', 'Estado', 'texto', 12),
                $this->columna('verificado', 'Correo verificado', 'texto', 12),
                $this->columna('google', 'Cuenta Google', 'texto', 11),
                $this->columna('compras', 'Compras', 'entero', 10),
                $this->columna('created_at', 'Registro', 'fecha', 19),
            ],
            'filas' => array_map(fn (array $f) => [
                'id' => (int) $f['id'],
                'nombre' => $this->nombre($f['nombre'], $f['apellido']),
                'email' => $f['email'],
                'rol' => self::ROLES[$f['rol']] ?? $f['rol'],
                'estado' => self::ESTADOS['usuarios'][$f['estado']] ?? $f['estado'],
                'verificado' => $f['email_verified_at'] ? 'Sí' : 'No',
                'google' => $f['google_id'] ? 'Sí' : 'No',
                'compras' => (int) $f['compras'],
                'created_at' => $this->fecha($f['created_at']),
            ], $filas),
        ];
    }

    // ---- Ayudas ----

    private function indicador(string $etiqueta, int|float $valor, string $formato = 'entero', ?string $nota = null): array
    {
        return compact('etiqueta', 'valor', 'formato', 'nota');
    }

    private function columna(string $clave, string $titulo, string $formato, int $ancho): array
    {
        return compact('clave', 'titulo', 'formato', 'ancho');
    }

    private function desglose(string $titulo, array $filas, string $campo, array $etiquetas): array
    {
        $total = count($filas);
        $conteo = array_count_values(array_map(fn ($f) => (string) $f[$campo], $filas));
        // Los valores que no están en el catálogo (si algún día aparece uno nuevo) también se muestran
        $claves = array_unique([...array_keys($etiquetas), ...array_keys($conteo)]);

        return [
            'titulo' => $titulo,
            'filas' => array_map(fn ($clave) => [
                'etiqueta' => $etiquetas[$clave] ?? ucfirst($clave),
                'cantidad' => $conteo[$clave] ?? 0,
                'porcentaje' => $total ? ($conteo[$clave] ?? 0) / $total : 0,
            ], array_values($claves)),
        ];
    }

    private function contar(array $filas, string $campo, string $valor): int
    {
        return count(array_filter($filas, fn ($f) => $f[$campo] === $valor));
    }

    private function nombre(?string $nombre, ?string $apellido): ?string
    {
        return trim("{$nombre} {$apellido}") ?: null;
    }

    // La base guarda UTC: se muestra en la hora de Guatemala
    private function fecha(?string $valor): ?DateTimeImmutable
    {
        return $valor ? (new DateTimeImmutable($valor, new DateTimeZone('UTC')))->setTimezone($this->zona) : null;
    }

    private function periodo(?string $desde, ?string $hasta): string
    {
        $dia = fn (string $d) => (new DateTimeImmutable($d))->format('d/m/Y');

        return match (true) {
            $desde && $hasta => "Del {$dia($desde)} al {$dia($hasta)}",
            (bool) $desde => "Desde el {$dia($desde)}",
            (bool) $hasta => "Hasta el {$dia($hasta)}",
            default => 'Todo el historial',
        };
    }
}
