<?php

namespace App\Data\Bitacora;

use App\Domain\Abstractions\Bitacora\IBitacoraRepository;
use App\Domain\Entities\Bitacora\RegistroBitacora;
use App\Domain\Exceptions\Bitacora\BitacoraNoDisponibleException;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

// Conexión "bitacora": su usuario de MySQL solo tiene SELECT e INSERT en eventos_bitacora
class BitacoraRepository implements IBitacoraRepository
{
    private function tabla(): Builder
    {
        return DB::connection('bitacora')->table('bitacora');
    }

    public function registrar(RegistroBitacora $registro): void
    {
        $json = fn (?array $datos) => $datos === null ? null : json_encode($datos, JSON_UNESCAPED_UNICODE);

        $this->tabla()->insert([
            'ocurrido_en' => $registro->getOcurridoEn()->format('Y-m-d H:i:s'),
            'usuario_id' => $registro->getUsuarioId(),
            'usuario_nombre' => $registro->getUsuarioNombre(),
            'usuario_correo' => $registro->getUsuarioCorreo(),
            'usuario_rol' => $registro->getUsuarioRol(),
            'categoria' => $registro->getCategoria()->value,
            'accion' => $registro->getAccion(),
            'resultado' => $registro->getResultado()->value,
            'entidad' => $registro->getEntidad(),
            'entidad_id' => $registro->getEntidadId(),
            'descripcion' => $registro->getDescripcion(),
            'datos_antes' => $json($registro->getDatosAntes()),
            'datos_despues' => $json($registro->getDatosDespues()),
            'ip' => $registro->getIp(),
            'user_agent' => $registro->getUserAgent(),
        ]);
    }

    public function buscar(array $filtros, int $limite, int $desde): array
    {
        return $this->disponible(fn () => $this->filtrar($filtros)
            ->orderByDesc('ocurrido_en')
            ->orderByDesc('id')
            ->offset($desde)
            ->limit($limite)
            ->get()
            ->map(fn ($fila) => [
                'id' => (int) $fila->id,
                'ocurrido_en' => $fila->ocurrido_en,
                'usuario' => $fila->usuario_id === null && $fila->usuario_correo === null ? null : [
                    'id' => $fila->usuario_id !== null ? (int) $fila->usuario_id : null,
                    'nombre' => $fila->usuario_nombre,
                    'correo' => $fila->usuario_correo,
                    'rol' => $fila->usuario_rol,
                ],
                'categoria' => $fila->categoria,
                'accion' => $fila->accion,
                'resultado' => $fila->resultado,
                'entidad' => $fila->entidad,
                'entidad_id' => $fila->entidad_id,
                'descripcion' => $fila->descripcion,
                'datos_antes' => $fila->datos_antes ? json_decode($fila->datos_antes, true) : null,
                'datos_despues' => $fila->datos_despues ? json_decode($fila->datos_despues, true) : null,
                'ip' => $fila->ip,
                'user_agent' => $fila->user_agent,
            ])
            ->all());
    }

    public function contar(array $filtros): int
    {
        return $this->disponible(fn () => $this->filtrar($filtros)->count());
    }

    // Base sin instalar o caída: un error claro en vez de un 500
    private function disponible(callable $consulta): mixed
    {
        try {
            return $consulta();
        } catch (QueryException $e) {
            report($e);
            throw new BitacoraNoDisponibleException;
        }
    }

    private function filtrar(array $filtros): Builder
    {
        return $this->tabla()
            ->when($filtros['categoria'] ?? null, fn (Builder $q, $v) => $q->where('categoria', $v))
            ->when($filtros['resultado'] ?? null, fn (Builder $q, $v) => $q->where('resultado', $v))
            ->when($filtros['rol'] ?? null, fn (Builder $q, $v) => $v === 'sin_sesion'
                ? $q->whereNull('usuario_rol')
                : $q->where('usuario_rol', $v))
            ->when($filtros['usuario'] ?? null, function (Builder $q, $v) {
                $patron = '%'.addcslashes($v, '%_\\').'%';
                $q->where(fn (Builder $o) => $o->where('usuario_nombre', 'like', $patron)
                    ->orWhere('usuario_correo', 'like', $patron));
            })
            ->when($filtros['desde'] ?? null, fn (Builder $q, $v) => $q->where('ocurrido_en', '>=', $v))
            ->when($filtros['hasta'] ?? null, fn (Builder $q, $v) => $q->where('ocurrido_en', '<', $v));
    }
}
