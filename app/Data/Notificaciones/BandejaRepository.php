<?php

namespace App\Data\Notificaciones;

use App\Domain\Abstractions\Notificaciones\IBandejaRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class BandejaRepository implements IBandejaRepository
{
    public function deUsuario(int $usuarioId, int $pagina, int $porPagina): array
    {
        $base = DB::table('notificaciones')->where('usuario_id', $usuarioId)->whereNull('deleted_at');

        $avisos = (clone $base)
            ->orderByDesc('created_at')->orderByDesc('id')
            ->offset(($pagina - 1) * $porPagina)
            ->limit($porPagina)
            ->get(['id', 'tipo', 'asunto', 'mensaje', 'url', 'leida_en', 'created_at'])
            ->map(fn ($f) => [
                'id' => (int) $f->id,
                'tipo' => $f->tipo,
                'titulo' => $f->asunto,
                'mensaje' => $f->mensaje,
                'url' => $f->url,
                'leida' => $f->leida_en !== null,
                'fecha' => Carbon::parse($f->created_at)->toIso8601String(),
            ])
            ->all();

        return [
            'avisos' => $avisos,
            'total' => (clone $base)->count(),
            'no_leidas' => (clone $base)->whereNull('leida_en')->count(),
        ];
    }

    public function marcarLeidas(int $usuarioId, ?int $notificacionId): int
    {
        return DB::table('notificaciones')
            ->where('usuario_id', $usuarioId)
            ->whereNull('leida_en')
            ->whereNull('deleted_at')
            ->when($notificacionId, fn ($q) => $q->where('id', $notificacionId))
            ->update(['leida_en' => now()]);
    }
}
