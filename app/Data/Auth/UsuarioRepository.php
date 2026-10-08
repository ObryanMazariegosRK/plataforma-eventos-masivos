<?php
namespace App\Data\Auth;

use App\Domain\Abstractions\Auth\IUsuarioRepository;
use App\Domain\Entities\Auth\Usuario;
use App\Models\Auth\Usuario as UsuarioModel;   // alias, para no chocar con la Entidad

class UsuarioRepository implements IUsuarioRepository
{
    public function guardar(Usuario $usuario): Usuario
    {
        $m = $usuario->getId() ? UsuarioModel::findOrFail($usuario->getId()) : new UsuarioModel();
        $m->nombre              = $usuario->getNombre();
        $m->apellido            = $usuario->getApellido();
        $m->email               = $usuario->getEmail();
        $m->telefono            = $usuario->getTelefono();
        $m->password            = $usuario->getPasswordHash();   // ya viene hasheada del caso de uso
        $m->rol                 = $usuario->getRol();
        $m->estado              = $usuario->getEstado();
        $m->email_verified_at   = $usuario->getEmailVerificadoEn();
        $m->codigo_verificacion = $usuario->getCodigoVerificacion();
        $m->codigo_expira_en    = $usuario->getCodigoExpiraEn();
        $m->intentos_verificacion  = $usuario->getIntentosVerificacion();
        $m->codigo_recuperacion    = $usuario->getCodigoRecuperacion();
        $m->recuperacion_expira_en = $usuario->getRecuperacionExpiraEn();
        $m->intentos_recuperacion  = $usuario->getIntentosRecuperacion();
        $m->save();

        return $this->aEntidad($m);
    }

    public function buscarPorId(int $id): ?Usuario
    {
        $m = UsuarioModel::find($id);

        return $m ? $this->aEntidad($m) : null;
    }

    public function buscarPorEmail(string $email): ?Usuario
    {
        $m = UsuarioModel::where('email', $email)->first();

        return $m ? $this->aEntidad($m) : null;
    }

    public function existeEmail(string $email): bool
    {
        // withTrashed: el índice unique de 'email' también cuenta a los eliminados (soft delete)
        return UsuarioModel::withTrashed()->where('email', $email)->exists();
    }

    private function aEntidad(UsuarioModel $m): Usuario
    {
        return new Usuario(
            id: $m->id,
            nombre: $m->nombre,
            apellido: $m->apellido,
            email: $m->email,
            rol: $m->rol,
            estado: $m->estado,
            telefono: $m->telefono,
            passwordHash: $m->password,
            emailVerificadoEn: $m->email_verified_at?->toDateTimeImmutable(),
            codigoVerificacion: $m->codigo_verificacion,
            codigoExpiraEn: $m->codigo_expira_en?->toDateTimeImmutable(),
            intentosVerificacion: (int) $m->intentos_verificacion,
            codigoRecuperacion: $m->codigo_recuperacion,
            recuperacionExpiraEn: $m->recuperacion_expira_en?->toDateTimeImmutable(),
            intentosRecuperacion: (int) $m->intentos_recuperacion,
        );
    }
}
