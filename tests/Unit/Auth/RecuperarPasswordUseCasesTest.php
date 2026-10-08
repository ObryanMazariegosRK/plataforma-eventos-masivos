<?php

namespace Tests\Unit\Auth;

use App\Application\DTOs\Auth\RestablecerPasswordDTO;
use App\Application\DTOs\Auth\SolicitarRecuperacionDTO;
use App\Application\UseCases\Auth\RestablecerPasswordUseCase;
use App\Application\UseCases\Auth\SolicitarRecuperacionUseCase;
use App\Domain\Entities\Auth\Usuario;
use App\Domain\Enums\Auth\EstadoUsuario;
use App\Domain\Enums\Auth\RolUsuario;
use App\Domain\Exceptions\Auth\CodigoRecuperacionInvalidoException;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\Auth\EnviadorCorreoAuthFalso;
use Tests\Fakes\Auth\PasswordHasherFalso;
use Tests\Fakes\Auth\TokenServiceFalso;
use Tests\Fakes\Auth\UsuarioRepositoryEnMemoria;

class RecuperarPasswordUseCasesTest extends TestCase
{
    private UsuarioRepositoryEnMemoria $repo;
    private EnviadorCorreoAuthFalso $correo;
    private TokenServiceFalso $tokens;
    private SolicitarRecuperacionUseCase $solicitar;
    private RestablecerPasswordUseCase $restablecer;

    protected function setUp(): void
    {
        $this->repo = new UsuarioRepositoryEnMemoria();
        $this->correo = new EnviadorCorreoAuthFalso();
        $this->tokens = new TokenServiceFalso();
        $this->solicitar = new SolicitarRecuperacionUseCase($this->repo, $this->correo);
        $this->restablecer = new RestablecerPasswordUseCase($this->repo, new PasswordHasherFalso(), $this->tokens);
    }

    public function test_solicitar_envia_un_codigo_de_recuperacion(): void
    {
        $this->crearUsuario();

        $this->solicitar->execute(new SolicitarRecuperacionDTO('ana@example.com', 15));

        $codigo = $this->repo->buscarPorId(1)->getCodigoRecuperacion();
        $this->assertMatchesRegularExpression('/^[0-9]{6}$/', $codigo);
        $this->assertSame($codigo, $this->correo->recuperaciones[0]['codigo']);
        $this->assertNull($this->repo->buscarPorId(1)->getCodigoVerificacion(), 'no debe tocar el código de verificación');
    }

    public function test_solicitar_no_envia_nada_si_no_existe_esta_bloqueado_o_sin_verificar(): void
    {
        $this->crearUsuario(email: 'bloqueado@example.com', estado: EstadoUsuario::BLOQUEADO);
        $this->crearUsuario(email: 'pendiente@example.com', verificado: false);

        foreach (['nadie@example.com', 'bloqueado@example.com', 'pendiente@example.com'] as $email) {
            $this->solicitar->execute(new SolicitarRecuperacionDTO($email, 15));
        }

        $this->assertSame([], $this->correo->recuperaciones);
    }

    public function test_restablecer_cambia_la_password_borra_el_codigo_y_cierra_todas_las_sesiones(): void
    {
        $this->crearUsuario();
        $this->solicitar->execute(new SolicitarRecuperacionDTO('ana@example.com', 15));
        $codigo = $this->correo->recuperaciones[0]['codigo'];

        $this->restablecer->execute(new RestablecerPasswordDTO('ana@example.com', $codigo, 'Nueva#456'));

        $usuario = $this->repo->buscarPorId(1);
        $this->assertSame('hash:Nueva#456', $usuario->getPasswordHash());
        $this->assertNull($usuario->getCodigoRecuperacion());
        $this->assertSame([1], $this->tokens->revocadosTodos);
    }

    public function test_restablecer_con_codigo_incorrecto_no_cambia_nada(): void
    {
        $this->crearUsuario();
        $this->solicitar->execute(new SolicitarRecuperacionDTO('ana@example.com', 15));

        try {
            $this->restablecer->execute(new RestablecerPasswordDTO('ana@example.com', '000000', 'Nueva#456'));
            $this->fail('Debió lanzar excepción');
        } catch (CodigoRecuperacionInvalidoException) {
            $usuario = $this->repo->buscarPorId(1);
            $this->assertSame('hash:Secreto#123', $usuario->getPasswordHash());
            $this->assertSame(1, $usuario->getIntentosRecuperacion());
            $this->assertSame([], $this->tokens->revocadosTodos);
        }
    }

    public function test_el_codigo_de_verificacion_no_sirve_para_restablecer(): void
    {
        $this->crearUsuario(verificado: false);
        $usuario = $this->repo->buscarPorId(1);
        $usuario->asignarCodigoVerificacion('123456', new DateTimeImmutable('+10 minutes'));
        $this->repo->guardar($usuario);

        $this->expectException(CodigoRecuperacionInvalidoException::class);

        $this->restablecer->execute(new RestablecerPasswordDTO('ana@example.com', '123456', 'Nueva#456'));
    }

    private function crearUsuario(
        string $email = 'ana@example.com',
        bool $verificado = true,
        EstadoUsuario $estado = EstadoUsuario::ACTIVO,
    ): void {
        $this->repo->guardar(new Usuario(
            id: null,
            nombre: 'Ana',
            apellido: 'López',
            email: $email,
            rol: RolUsuario::CLIENTE,
            estado: $estado,
            passwordHash: 'hash:Secreto#123',
            emailVerificadoEn: $verificado ? new DateTimeImmutable() : null,
        ));
    }
}
