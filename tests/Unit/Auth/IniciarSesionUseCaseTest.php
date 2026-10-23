<?php

namespace Tests\Unit\Auth;

use App\Application\DTOs\Auth\IniciarSesionDTO;
use App\Application\UseCases\Auth\IniciarSesionUseCase;
use App\Domain\Entities\Auth\Usuario;
use App\Domain\Enums\Auth\EstadoUsuario;
use App\Domain\Enums\Auth\RolUsuario;
use App\Domain\Exceptions\Auth\AccesoDePersonalException;
use App\Domain\Exceptions\Auth\CorreoNoVerificadoException;
use App\Domain\Exceptions\Auth\CredencialesInvalidasException;
use App\Domain\Exceptions\Auth\SinAccesoAlPanelException;
use App\Domain\Exceptions\Auth\UsuarioBloqueadoException;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\Auth\PasswordHasherFalso;
use Tests\Fakes\Auth\TokenServiceFalso;
use Tests\Fakes\Auth\UsuarioRepositoryEnMemoria;

class IniciarSesionUseCaseTest extends TestCase
{
    private UsuarioRepositoryEnMemoria $repo;
    private TokenServiceFalso $tokens;
    private IniciarSesionUseCase $useCase;

    protected function setUp(): void
    {
        $this->repo = new UsuarioRepositoryEnMemoria();
        $this->tokens = new TokenServiceFalso();
        $this->useCase = new IniciarSesionUseCase($this->repo, new PasswordHasherFalso(), $this->tokens);
    }

    public function test_inicia_sesion(): void
    {
        $this->crearUsuario();

        $sesion = $this->useCase->execute(new IniciarSesionDTO('ana@example.com', 'Secreto#123'));

        $this->assertSame('token-1', $sesion->token);
        $this->assertSame('ana@example.com', $sesion->usuario->getEmail());
        $this->assertCount(1, $this->tokens->creados);
    }

    public function test_el_personal_entra_solo_por_su_acceso(): void
    {
        $this->crearUsuario(rol: RolUsuario::ORGANIZADOR);

        $sesion = $this->useCase->execute(new IniciarSesionDTO('ana@example.com', 'Secreto#123', personal: true));
        $this->assertSame(RolUsuario::ORGANIZADOR, $sesion->usuario->getRol());

        $this->expectException(AccesoDePersonalException::class);
        $this->useCase->execute(new IniciarSesionDTO('ana@example.com', 'Secreto#123'));
    }

    public function test_un_cliente_no_entra_por_el_acceso_del_personal(): void
    {
        $this->crearUsuario();

        $this->expectException(SinAccesoAlPanelException::class);
        $this->useCase->execute(new IniciarSesionDTO('ana@example.com', 'Secreto#123', personal: true));
    }

    public function test_con_contrasena_incorrecta_no_revela_si_la_cuenta_es_del_personal(): void
    {
        $this->crearUsuario(rol: RolUsuario::ADMIN);

        $this->expectException(CredencialesInvalidasException::class);
        $this->useCase->execute(new IniciarSesionDTO('ana@example.com', 'otra'));
    }

    public function test_contrasena_incorrecta(): void
    {
        $this->crearUsuario();

        $this->expectException(CredencialesInvalidasException::class);

        $this->useCase->execute(new IniciarSesionDTO('ana@example.com', 'otra'));
    }

    public function test_correo_inexistente_da_el_mismo_error_que_contrasena_incorrecta(): void
    {
        $this->expectException(CredencialesInvalidasException::class);

        $this->useCase->execute(new IniciarSesionDTO('nadie@example.com', 'Secreto#123'));
    }

    public function test_correo_sin_verificar(): void
    {
        $this->crearUsuario(verificado: false);

        $this->expectException(CorreoNoVerificadoException::class);

        $this->useCase->execute(new IniciarSesionDTO('ana@example.com', 'Secreto#123'));
    }

    public function test_usuario_bloqueado(): void
    {
        $this->crearUsuario(estado: EstadoUsuario::BLOQUEADO);

        $this->expectException(UsuarioBloqueadoException::class);

        $this->useCase->execute(new IniciarSesionDTO('ana@example.com', 'Secreto#123'));
    }

    public function test_usuario_bloqueado_con_contrasena_incorrecta_no_revela_el_bloqueo(): void
    {
        $this->crearUsuario(estado: EstadoUsuario::BLOQUEADO);

        $this->expectException(CredencialesInvalidasException::class);

        $this->useCase->execute(new IniciarSesionDTO('ana@example.com', 'otra'));
    }

    private function crearUsuario(bool $verificado = true, EstadoUsuario $estado = EstadoUsuario::ACTIVO, RolUsuario $rol = RolUsuario::CLIENTE): void
    {
        $this->repo->guardar(new Usuario(
            id: null,
            nombre: 'Ana',
            apellido: 'López',
            email: 'ana@example.com',
            rol: $rol,
            estado: $estado,
            passwordHash: 'hash:Secreto#123',
            emailVerificadoEn: $verificado ? new DateTimeImmutable() : null,
        ));
    }
}
