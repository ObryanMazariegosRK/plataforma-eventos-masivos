<?php

namespace Tests\Fakes\Notificaciones;

use App\Domain\Abstractions\Notificaciones\IEnviadorPush;

/** Guarda los push en memoria; la prueba decide si la persona tiene dispositivos o si falla. */
class EnviadorPushFalso implements IEnviadorPush
{
    /** @var list<array{usuario_id: int, titulo: string, cuerpo: string, url: ?string}> */
    public array $enviados = [];

    public bool $fallar = false;

    public function enviar(int $usuarioId, string $titulo, string $cuerpo, ?string $url): int
    {
        if ($this->fallar) {
            throw new \RuntimeException('El servicio de push no respondió.');
        }
        $this->enviados[] = ['usuario_id' => $usuarioId, 'titulo' => $titulo, 'cuerpo' => $cuerpo, 'url' => $url];

        return 1;
    }
}
