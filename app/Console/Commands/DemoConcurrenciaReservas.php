<?php

namespace App\Console\Commands;

use App\Application\Abstractions\Inventario\IGenerarInventarioEventoUseCase;
use App\Domain\Enums\Auth\EstadoUsuario;
use App\Domain\Enums\Auth\RolUsuario;
use App\Models\Auth\Usuario;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Demostración de concurrencia para la exposición: manda reservas REALES en paralelo
 * (HTTP contra nginx → php-fpm → MySQL, como varios navegadores a la vez) y comprueba
 * en la base que no hubo sobreventa ni se pasó el límite por persona.
 *
 * Crea su propio evento y usuarios de prueba y los borra al final (salvo --conservar).
 * Uso: docker compose exec app php artisan reservas:demo-concurrencia
 */
class DemoConcurrenciaReservas extends Command
{
    protected $signature = 'reservas:demo-concurrencia
        {--url=http://nginx : URL base de la app vista desde el contenedor}
        {--conservar : No borra el evento ni los usuarios de prueba (para revisarlos en Adminer)}';

    protected $description = 'Lanza reservas simultáneas y comprueba que no haya sobreventa ni se pase el límite por persona';

    private const CORREO = 'demo-concurrencia-%d@soiree.test';
    private const RECINTO = 'Auditorio Central';

    private ?int $eventoId = null;
    /** @var array<int, array{id: int, token: string}> */
    private array $usuarios = [];
    private bool $todoBien = true;

    public function handle(): int
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->error('Esta demostración necesita MySQL: SQLite no tiene bloqueos de fila (FOR UPDATE / SKIP LOCKED).');
            return self::FAILURE;
        }

        try {
            $this->preparar();

            $this->mismaPersonaDosPestanas();
            $this->muchasPersonasMismoAsiento();
            $this->zonaGeneralSinSobreventa();

            $this->newLine();
            $this->todoBien
                ? $this->info('✔ Todas las comprobaciones pasaron: los bloqueos de la base evitaron los choques.')
                : $this->error('✘ Alguna comprobación falló: revisa las tablas de arriba.');

            return $this->todoBien ? self::SUCCESS : self::FAILURE;
        } finally {
            $this->option('conservar') ? $this->avisarConservado() : $this->limpiar();
        }
    }

    // ---------------------------------------------------------------- escenarios

    /** La misma persona reserva desde dos pestañas a la vez: 3 + 3 > 5 por persona. */
    private function mismaPersonaDosPestanas(): void
    {
        $maximo = config('reservas.max_boletos_por_persona');
        $this->titulo(1, 'La misma persona, dos pestañas al mismo tiempo',
            "Pide 3 asientos en cada pestaña (6 en total). El máximo es {$maximo} por persona.");

        $usuario = $this->usuarios[0];
        $asientos = $this->asientosVip(6);
        $respuestas = $this->enParalelo([
            'Pestaña 1' => [$usuario, ['inventario_ids' => array_slice($asientos, 0, 3)]],
            'Pestaña 2' => [$usuario, ['inventario_ids' => array_slice($asientos, 3, 3)]],
        ]);
        $this->tablaRespuestas($respuestas);

        $tiene = $this->lugaresDe($usuario['id']);
        $this->comprobar($tiene <= $maximo, "La persona quedó con {$tiene} lugares apartados (máximo {$maximo}).");
        $this->comprobar($this->exitosas($respuestas) === 1, 'Solo una de las dos pestañas logró reservar.');
    }

    /** Muchas personas piden el mismo asiento numerado en el mismo instante. */
    private function muchasPersonasMismoAsiento(): void
    {
        $personas = array_slice($this->usuarios, 1, 10);
        $asiento = $this->asientosVip(1)[0];
        $etiqueta = DB::table('inventario')->join('asientos', 'asientos.id', '=', 'inventario.asiento_id')
            ->where('inventario.id', $asiento)->selectRaw("CONCAT(asientos.fila, asientos.numero) as nombre")->value('nombre');

        $this->titulo(2, 'Diez personas, el mismo asiento',
            "Todas piden el asiento VIP {$etiqueta} a la vez (FOR UPDATE sobre la fila del inventario).");

        $pedidos = [];
        foreach ($personas as $i => $usuario) {
            $pedidos['Persona '.($i + 1)] = [$usuario, ['inventario_ids' => [$asiento]]];
        }
        $respuestas = $this->enParalelo($pedidos);
        $this->tablaRespuestas($respuestas);

        $reservas = DB::table('inventario')->where('id', $asiento)->whereNotNull('reserva_id')->count();
        $this->comprobar($this->exitosas($respuestas) === 1, "El asiento {$etiqueta} se lo llevó una sola persona.");
        $this->comprobar($reservas === 1, 'En la base, el asiento quedó en una sola reserva.');
    }

    /** Zona general por cantidad: más pedidos que lugares; nadie recibe un lugar repetido. */
    private function zonaGeneralSinSobreventa(): void
    {
        $general = DB::table('localidades')->join('recintos', 'recintos.id', '=', 'localidades.recinto_id')
            ->where('recintos.nombre', self::RECINTO)->where('localidades.tipo', 'general')->value('localidades.id');
        $capacidad = DB::table('inventario')->where('evento_id', $this->eventoId)->where('localidad_id', $general)->count();
        $personas = array_slice($this->usuarios, 11, 25);
        $pedido = 3;

        $this->titulo(3, 'Zona general: más pedidos que lugares',
            count($personas)." personas piden {$pedido} lugares cada una (".count($personas) * $pedido." en total) y hay {$capacidad}. "
            .'Los lugares se eligen con FOR UPDATE SKIP LOCKED.');

        $pedidos = [];
        foreach ($personas as $i => $usuario) {
            $pedidos['Persona '.($i + 1)] = [$usuario, ['cupos' => [['localidad_id' => $general, 'cantidad' => $pedido]]]];
        }
        $inicio = microtime(true);
        $respuestas = $this->enParalelo($pedidos);
        $ms = (int) ((microtime(true) - $inicio) * 1000);

        $exitosas = $this->exitosas($respuestas);
        $this->line(sprintf('  %d reservas aceptadas, %d rechazadas, en %d ms.', $exitosas, count($respuestas) - $exitosas, $ms));
        $this->tablaResumenRechazos($respuestas);

        $apartados = DB::table('inventario')->where('evento_id', $this->eventoId)->where('localidad_id', $general)
            ->whereNotNull('reserva_id')->count();
        $porReserva = DB::table('inventario')->where('evento_id', $this->eventoId)->where('localidad_id', $general)
            ->whereNotNull('reserva_id')->groupBy('reserva_id')->selectRaw('COUNT(*) as lugares')->pluck('lugares')->unique()->all();

        $esperadas = intdiv($capacidad, $pedido);
        $this->comprobar($apartados <= $capacidad, "Sin sobreventa: {$apartados} de {$capacidad} lugares apartados.");
        $this->comprobar($exitosas === $esperadas, "Se aceptaron exactamente {$esperadas} reservas (las que caben).");
        $this->comprobar($porReserva === [$pedido] || $porReserva === [], "Cada reserva aceptada tiene exactamente {$pedido} lugares, sin repetidos.");
    }

    // ---------------------------------------------------------------- HTTP en paralelo

    /**
     * @param  array<string, array{0: array{id: int, token: string}, 1: array}>  $pedidos
     * @return array<string, Response>
     */
    private function enParalelo(array $pedidos): array
    {
        $url = rtrim($this->option('url'), '/').'/api/reservas';

        return Http::pool(fn (Pool $pool) => collect($pedidos)->map(
            fn ($pedido, $nombre) => $pool->as($nombre)
                ->acceptJson()
                ->withToken($pedido[0]['token'])
                ->timeout(30)
                ->post($url, ['evento_id' => $this->eventoId] + $pedido[1])
        )->all());
    }

    private function exitosas(array $respuestas): int
    {
        return collect($respuestas)->filter(fn ($r) => $r instanceof Response && $r->status() === 201)->count();
    }

    private function tablaRespuestas(array $respuestas): void
    {
        $this->table(['Quién', 'Respuesta', 'Detalle'], collect($respuestas)->map(fn ($r, $quien) => [
            $quien,
            $this->estado($r),
            $r instanceof Response && $r->status() === 201
                ? 'Reserva #'.$r->json('id').' · Q'.number_format((float) $r->json('total'), 2)
                : Str::limit($this->mensaje($r), 90),
        ])->values()->all());
    }

    private function tablaResumenRechazos(array $respuestas): void
    {
        $rechazos = collect($respuestas)->reject(fn ($r) => $r instanceof Response && $r->status() === 201)
            ->map(fn ($r) => $this->mensaje($r))->countBy();
        if ($rechazos->isNotEmpty()) {
            $this->table(['Motivo del rechazo', 'Veces'], $rechazos->map(fn ($n, $m) => [Str::limit($m, 90), $n])->values()->all());
        }
    }

    private function estado(mixed $r): string
    {
        if (! $r instanceof Response) {
            return '<fg=red>sin respuesta</>';
        }

        return $r->status() === 201 ? '<fg=green>201 reservada</>' : "<fg=yellow>{$r->status()} rechazada</>";
    }

    private function mensaje(mixed $r): string
    {
        return $r instanceof Response ? (string) ($r->json('message') ?? $r->body()) : $r->getMessage();
    }

    // ---------------------------------------------------------------- datos de prueba

    private function preparar(): void
    {
        $recintoId = DB::table('recintos')->where('nombre', self::RECINTO)->value('id')
            ?? throw new \RuntimeException('Falta el recinto "'.self::RECINTO.'": corre php artisan db:seed --class=RecintoSeeder');

        $this->limpiarRestosAnteriores();
        $this->line('Preparando un evento y 36 usuarios de prueba…');

        $this->eventoId = DB::table('eventos')->insertGetId([
            'recinto_id' => $recintoId, 'nombre' => 'Demo de concurrencia (temporal)',
            'fecha_evento' => now()->addMonth(), 'estado' => 'borrador',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach (DB::table('localidades')->where('recinto_id', $recintoId)->pluck('id') as $localidadId) {
            DB::table('precios')->insert([
                'evento_id' => $this->eventoId, 'localidad_id' => $localidadId, 'precio' => 100,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        app(IGenerarInventarioEventoUseCase::class)->execute($this->eventoId);
        DB::table('eventos')->where('id', $this->eventoId)->update(['estado' => 'publicado', 'fecha_publicacion' => now()]);

        $password = Hash::make(Str::random(32));
        foreach (range(1, 36) as $n) {
            $usuario = Usuario::create([
                'nombre' => 'Demo', 'apellido' => (string) $n, 'email' => sprintf(self::CORREO, $n),
                'password' => $password, 'rol' => RolUsuario::CLIENTE, 'estado' => EstadoUsuario::ACTIVO,
                'email_verified_at' => now(),
            ]);
            $this->usuarios[] = ['id' => $usuario->id, 'token' => $usuario->createToken('demo-concurrencia')->plainTextToken];
        }
    }

    /** @return list<int> ids de inventario de asientos VIP aún libres */
    private function asientosVip(int $cantidad): array
    {
        return DB::table('inventario')->where('evento_id', $this->eventoId)->whereNotNull('asiento_id')
            ->where('estado', 'disponible')->orderBy('id')->limit($cantidad)->pluck('id')->all();
    }

    private function lugaresDe(int $usuarioId): int
    {
        return DB::table('inventario')->join('reservas', 'reservas.id', '=', 'inventario.reserva_id')
            ->where('reservas.usuario_id', $usuarioId)->where('reservas.evento_id', $this->eventoId)->count();
    }

    private function limpiar(): void
    {
        if ($this->eventoId === null && $this->usuarios === []) {
            return;
        }
        $this->borrar($this->eventoId ? [$this->eventoId] : [], array_column($this->usuarios, 'id'));
        $this->newLine();
        $this->line('<fg=gray>Se borraron el evento y los usuarios de prueba.</>');
    }

    /** Por si una corrida anterior se interrumpió antes de limpiar. */
    private function limpiarRestosAnteriores(): void
    {
        $usuarios = DB::table('usuarios')->where('email', 'like', 'demo-concurrencia-%@soiree.test')->pluck('id')->all();
        $eventos = DB::table('eventos')->where('nombre', 'Demo de concurrencia (temporal)')->pluck('id')->all();
        if ($usuarios || $eventos) {
            $this->borrar($eventos, $usuarios);
        }
    }

    private function borrar(array $eventos, array $usuarios): void
    {
        DB::transaction(function () use ($eventos, $usuarios) {
            $reservas = DB::table('reservas')->whereIn('evento_id', $eventos)->orWhereIn('usuario_id', $usuarios)->pluck('id');
            DB::table('historial_operaciones')->whereIn('reserva_id', $reservas)->orWhereIn('evento_id', $eventos)->delete();
            DB::table('inventario')->whereIn('reserva_id', $reservas)->update(['reserva_id' => null]);
            DB::table('inventario')->whereIn('evento_id', $eventos)->delete();
            DB::table('reservas')->whereIn('id', $reservas)->delete();
            DB::table('precios')->whereIn('evento_id', $eventos)->delete();
            DB::table('eventos')->whereIn('id', $eventos)->delete();
            DB::table('personal_access_tokens')->where('tokenable_type', Usuario::class)->whereIn('tokenable_id', $usuarios)->delete();
            DB::table('usuarios')->whereIn('id', $usuarios)->delete();
        });
    }

    private function avisarConservado(): void
    {
        $this->newLine();
        $this->warn("Se conservaron el evento #{$this->eventoId} y los usuarios demo-concurrencia-N@soiree.test.");
        $this->line('Vuelve a correr el comando (sin --conservar) para borrarlos.');
    }

    // ---------------------------------------------------------------- salida

    private function titulo(int $numero, string $titulo, string $explicacion): void
    {
        $this->newLine();
        $this->line("<options=bold>Escenario {$numero} · {$titulo}</>");
        $this->line("  <fg=gray>{$explicacion}</>");
    }

    private function comprobar(bool $ok, string $texto): void
    {
        $this->line($ok ? "  <fg=green>✔</> {$texto}" : "  <fg=red>✘</> {$texto}");
        $this->todoBien = $this->todoBien && $ok;
    }
}
