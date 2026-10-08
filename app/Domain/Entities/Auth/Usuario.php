<?php
namespace App\Domain\Entities\Auth;

use App\Domain\Enums\Auth\EstadoUsuario;
use App\Domain\Enums\Auth\RolUsuario;
use DateTimeImmutable;
use DomainException;

class Usuario
{
    // Intentos fallidos permitidos por código; al llegar aquí el código se invalida.
    public const MAX_INTENTOS_CODIGO = 5;

    public function __construct(
        private ?int $id,
        private string $nombre,
        private string $apellido,
        private string $email,
        private RolUsuario $rol,
        private EstadoUsuario $estado,
        private ?string $telefono = null,
        private ?string $passwordHash = null,
        private ?DateTimeImmutable $emailVerificadoEn = null,
        private ?string $codigoVerificacion = null,
        private ?DateTimeImmutable $codigoExpiraEn = null,
        private int $intentosVerificacion = 0,
        private ?string $codigoRecuperacion = null,
        private ?DateTimeImmutable $recuperacionExpiraEn = null,
        private int $intentosRecuperacion = 0,
    ) {
        $this->validar();          // la entidad se protege al nacer
    }

    private function validar(): void
    {
        if (trim($this->nombre) === '') {
            throw new DomainException('El nombre no puede estar vacío.');
        }
        if (trim($this->apellido) === '') {
            throw new DomainException('El apellido no puede estar vacío.');
        }
        if (! filter_var($this->email, FILTER_VALIDATE_EMAIL)) {
            throw new DomainException('El correo no tiene un formato válido.');
        }
        if ($this->telefono !== null && ! preg_match('/^[0-9+\-\s]{8,15}$/', $this->telefono)) {
            throw new DomainException('El teléfono no tiene un formato válido.');
        }
    }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getNombre(): string { return $this->nombre; }
    public function getApellido(): string { return $this->apellido; }
    public function getEmail(): string { return $this->email; }
    public function getRol(): RolUsuario { return $this->rol; }
    public function getEstado(): EstadoUsuario { return $this->estado; }
    public function getTelefono(): ?string { return $this->telefono; }
    public function getPasswordHash(): ?string { return $this->passwordHash; }
    public function getEmailVerificadoEn(): ?DateTimeImmutable { return $this->emailVerificadoEn; }
    public function getCodigoVerificacion(): ?string { return $this->codigoVerificacion; }
    public function getCodigoExpiraEn(): ?DateTimeImmutable { return $this->codigoExpiraEn; }
    public function getIntentosVerificacion(): int { return $this->intentosVerificacion; }
    public function getCodigoRecuperacion(): ?string { return $this->codigoRecuperacion; }
    public function getRecuperacionExpiraEn(): ?DateTimeImmutable { return $this->recuperacionExpiraEn; }
    public function getIntentosRecuperacion(): int { return $this->intentosRecuperacion; }

    // Comportamiento de negocio
    public function estaBloqueado(): bool
    {
        return $this->estado === EstadoUsuario::BLOQUEADO;
    }

    public function nombreCompleto(): string
    {
        return "{$this->nombre} {$this->apellido}";
    }

    // ---- Verificación de correo ----

    public function estaVerificado(): bool
    {
        return $this->emailVerificadoEn !== null;
    }

    public function asignarCodigoVerificacion(string $codigo, DateTimeImmutable $expiraEn): void
    {
        $this->validarFormatoCodigo($codigo);
        $this->codigoVerificacion = $codigo;
        $this->codigoExpiraEn = $expiraEn;
        $this->intentosVerificacion = 0;   // código nuevo, intentos nuevos
    }

    public function codigoEsValido(string $codigo, DateTimeImmutable $ahora): bool
    {
        return $this->codigoCoincide($this->codigoVerificacion, $this->codigoExpiraEn, $codigo, $ahora);
    }

    /**
     * Suma un intento fallido. Al llegar al máximo, el código se destruye.
     * Devuelve true solo si ESTE intento agotó el código (hay que pedir uno nuevo).
     */
    public function registrarIntentoVerificacionFallido(): bool
    {
        if ($this->codigoVerificacion === null) {
            return false;   // no hay código activo: no hay nada que agotar
        }

        $this->intentosVerificacion++;
        if ($this->intentosVerificacion >= self::MAX_INTENTOS_CODIGO) {
            $this->codigoVerificacion = null;
            $this->codigoExpiraEn = null;

            return true;
        }

        return false;
    }

    public function marcarComoVerificado(DateTimeImmutable $ahora): void
    {
        $this->emailVerificadoEn = $ahora;
        // el código se destruye para que no pueda reutilizarse
        $this->codigoVerificacion = null;
        $this->codigoExpiraEn = null;
        $this->intentosVerificacion = 0;
    }

    // ---- Recuperación de contraseña ----

    public function asignarCodigoRecuperacion(string $codigo, DateTimeImmutable $expiraEn): void
    {
        $this->validarFormatoCodigo($codigo);
        $this->codigoRecuperacion = $codigo;
        $this->recuperacionExpiraEn = $expiraEn;
        $this->intentosRecuperacion = 0;
    }

    public function codigoRecuperacionEsValido(string $codigo, DateTimeImmutable $ahora): bool
    {
        return $this->codigoCoincide($this->codigoRecuperacion, $this->recuperacionExpiraEn, $codigo, $ahora);
    }

    /** Igual que registrarIntentoVerificacionFallido(), para el código de recuperación. */
    public function registrarIntentoRecuperacionFallido(): bool
    {
        if ($this->codigoRecuperacion === null) {
            return false;
        }

        $this->intentosRecuperacion++;
        if ($this->intentosRecuperacion >= self::MAX_INTENTOS_CODIGO) {
            $this->codigoRecuperacion = null;
            $this->recuperacionExpiraEn = null;

            return true;
        }

        return false;
    }

    /** Recibe la contraseña YA hasheada (el hash lo hace el caso de uso con IPasswordHasher). */
    public function cambiarPassword(string $nuevoHash): void
    {
        $this->passwordHash = $nuevoHash;
        // el código de recuperación ya se usó
        $this->codigoRecuperacion = null;
        $this->recuperacionExpiraEn = null;
        $this->intentosRecuperacion = 0;
    }

    // ---- Auxiliares ----

    private function validarFormatoCodigo(string $codigo): void
    {
        if (! preg_match('/^[0-9]{6}$/', $codigo)) {
            throw new DomainException('El código debe tener 6 dígitos.');
        }
    }

    private function codigoCoincide(?string $guardado, ?DateTimeImmutable $expiraEn, string $codigo, DateTimeImmutable $ahora): bool
    {
        return $guardado !== null
            && $expiraEn !== null
            && hash_equals($guardado, $codigo)   // comparación en tiempo constante
            && $ahora <= $expiraEn;
    }
}
