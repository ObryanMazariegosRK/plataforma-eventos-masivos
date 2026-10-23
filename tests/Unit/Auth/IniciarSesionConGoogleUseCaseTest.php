<?php

namespace Tests\Unit\Auth;

use App\Application\DTOs\Auth\IniciarSesionConGoogleDTO;
use App\Application\UseCases\Auth\IniciarSesionConGoogleUseCase;
use App\Domain\Entities\Auth\Usuario;
use App\Domain\Enums\Auth\EstadoUsuario;
use App\Domain\Enums\Auth\RolUsuario;
use App\Domain\Exceptions\Auth\CorreoNoVerificadoException;
use App\Domain\Exceptions\Auth\UsuarioBloqueadoException;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\Auth\TokenServiceFalso;
use Tests\Fakes\Auth\UsuarioRepositoryEnMemoria;

class IniciarSesionConGoogleUseCaseTest extends TestCase
{
    private UsuarioRepositoryEnMemoria $repo;
    private IniciarSesionConGoogleUseCase $useCase;

    protected function setUp(): void
    {
        $this->repo = new UsuarioRepositoryEnMemoria();
        $this->useCase = new IniciarSesionConGoogleUseCase($this->repo, new TokenServiceFalso());
    }

    public function test_usuario_nuevo_se_crea_verificado_sin_password_y_vinculado(): void
    {
        $sesion = $this->useCase->execute($this->dto());

        $usuario = $this->repo->buscarPorId(1);
        $this->assertSame('token-1', $sesion->token);
        $this->assertSame(RolUsuario::CLIENTE, $usuario->getRol());
        $this->assertTrue($usuario->estaVerificado());
        $this->assertNull($usuario->getPasswordHash());
        $this->assertSame('g-123', $usuario->getGoogleId());
    }

    public function test_la_segunda_vez_reutiliza_la_misma_cuenta(): void
    {
        $this->useCase->execute($this->dto());
        $this->useCase->execute($this->dto());

        $this->assertCount(1, $this->repo->usuarios);
    }

    public function test_cuenta_existente_verificada_se_vincula_y_conserva_su_password(): void
    {
        $this->crearUsuarioLocal(verificado: true);

        $this->useCase->execute($this->dto());

        $usuario = $this->repo->buscarPorId(1);
        $this->assertCount(1, $this->repo->usuarios);
        $this->assertSame('g-123', $usuario->getGoogleId());
        $this->assertSame('hash:Secreto#123', $usuario->getPasswordHash());
    }

    public function test_cuenta_existente_sin_verificar_pierde_la_password_al_vincular(): void
    {
        // Alguien registró este correo con SU contraseña pero nunca lo verificó (no es el dueño)
        $this->crearUsuarioLocal(verificado: false);

        $this->useCase->execute($this->dto());

        $usuario = $this->repo->buscarPorId(1);
        $this->assertTrue($usuario->estaVerificado());
        $this->assertNull($usuario->getPasswordHash(), 'la contraseña del intruso no debe seguir sirviendo');
    }

    public function test_usuario_bloqueado_no_entra_ni_se_vincula(): void
    {
        $this->crearUsuarioLocal(verificado: true, estado: EstadoUsuario::BLOQUEADO);

        try {
            $this->useCase->execute($this->dto());
            $this->fail('Debió lanzar excepción');
        } catch (UsuarioBloqueadoException) {
            $this->assertNull($this->repo->buscarPorId(1)->getGoogleId());
        }
    }

    public function test_rechaza_un_correo_que_google_no_verifico(): void
    {
        $this->crearUsuarioLocal(verificado: true);

        try {
            $this->useCase->execute($this->dto(emailVerificado: false));
            $this->fail('Debió lanzar excepción');
        } catch (CorreoNoVerificadoException) {
            $this->assertNull($this->repo->buscarPorId(1)->getGoogleId());
        }
    }

    private function dto(bool $emailVerificado = true): IniciarSesionConGoogleDTO
    {
        return new IniciarSesionConGoogleDTO('g-123', 'ana@example.com', $emailVerificado, 'Ana', 'López');
    }

    private function crearUsuarioLocal(bool $verificado, EstadoUsuario $estado = EstadoUsuario::ACTIVO): void
    {
        $this->repo->guardar(new Usuario(
            id: null,
            nombre: 'Ana',
            apellido: 'López',
            email: 'ana@example.com',
            rol: RolUsuario::CLIENTE,
            estado: $estado,
            passwordHash: 'hash:Secreto#123',
            emailVerificadoEn: $verificado ? new DateTimeImmutable() : null,
        ));
    }
}
