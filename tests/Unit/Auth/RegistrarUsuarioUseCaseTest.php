<?php

namespace Tests\Unit\Auth;

use App\Application\DTOs\Auth\RegistrarUsuarioDTO;
use App\Application\UseCases\Auth\RegistrarUsuarioUseCase;
use App\Domain\Enums\Auth\EstadoUsuario;
use App\Domain\Enums\Auth\RolUsuario;
use App\Domain\Exceptions\Auth\CorreoYaRegistradoException;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\Auth\EnviadorCorreoAuthFalso;
use Tests\Fakes\Auth\PasswordHasherFalso;
use Tests\Fakes\Auth\UsuarioRepositoryEnMemoria;

class RegistrarUsuarioUseCaseTest extends TestCase
{
    private UsuarioRepositoryEnMemoria $repo;
    private EnviadorCorreoAuthFalso $correo;
    private RegistrarUsuarioUseCase $useCase;

    protected function setUp(): void
    {
        $this->repo = new UsuarioRepositoryEnMemoria();
        $this->correo = new EnviadorCorreoAuthFalso();
        $this->useCase = new RegistrarUsuarioUseCase($this->repo, new PasswordHasherFalso(), $this->correo);
    }

    public function test_registra_un_cliente_sin_verificar_con_codigo_y_envia_el_correo(): void
    {
        $usuario = $this->useCase->execute($this->dto());

        $this->assertSame(1, $usuario->getId());
        $this->assertSame(RolUsuario::CLIENTE, $usuario->getRol());
        $this->assertSame(EstadoUsuario::ACTIVO, $usuario->getEstado());
        $this->assertSame('hash:Secreto#123', $usuario->getPasswordHash());
        $this->assertFalse($usuario->estaVerificado());
        $this->assertMatchesRegularExpression('/^[0-9]{6}$/', $usuario->getCodigoVerificacion());
        $this->assertGreaterThan(new DateTimeImmutable('+14 minutes'), $usuario->getCodigoExpiraEn());

        $this->assertCount(1, $this->correo->enviados);
        $this->assertSame('ana@example.com', $this->correo->enviados[0]['email']);
        $this->assertSame($usuario->getCodigoVerificacion(), $this->correo->enviados[0]['codigo']);
    }

    public function test_rechaza_un_correo_ya_registrado(): void
    {
        $this->useCase->execute($this->dto());

        $this->expectException(CorreoYaRegistradoException::class);

        $this->useCase->execute($this->dto());
    }

    private function dto(): RegistrarUsuarioDTO
    {
        return new RegistrarUsuarioDTO(
            nombre: 'Ana',
            apellido: 'López',
            email: 'ana@example.com',
            password: 'Secreto#123',
            minutosValidezCodigo: 15,
        );
    }
}
