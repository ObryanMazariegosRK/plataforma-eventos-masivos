<?php

namespace Tests\Unit\Auth;

use App\Application\DTOs\Auth\VerificarCorreoDTO;
use App\Application\UseCases\Auth\VerificarCorreoUseCase;
use App\Domain\Entities\Auth\Usuario;
use App\Domain\Enums\Auth\EstadoUsuario;
use App\Domain\Enums\Auth\RolUsuario;
use App\Domain\Exceptions\Auth\CodigoVerificacionInvalidoException;
use App\Domain\Exceptions\Auth\CorreoYaVerificadoException;
use App\Domain\Exceptions\Auth\IntentosAgotadosException;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\Auth\TokenServiceFalso;
use Tests\Fakes\Auth\UsuarioRepositoryEnMemoria;

class VerificarCorreoUseCaseTest extends TestCase
{
    private UsuarioRepositoryEnMemoria $repo;
    private TokenServiceFalso $tokens;
    private VerificarCorreoUseCase $useCase;

    protected function setUp(): void
    {
        $this->repo = new UsuarioRepositoryEnMemoria();
        $this->tokens = new TokenServiceFalso();
        $this->useCase = new VerificarCorreoUseCase($this->repo, $this->tokens);
    }

    public function test_verifica_con_codigo_correcto_borra_el_codigo_y_devuelve_token(): void
    {
        $this->usuarioPendiente('123456', new DateTimeImmutable('+10 minutes'));

        $sesion = $this->useCase->execute(new VerificarCorreoDTO('ana@example.com', '123456'));

        $this->assertSame('token-1', $sesion->token);
        $this->assertTrue($sesion->usuario->estaVerificado());
        $this->assertNull($this->repo->buscarPorId(1)->getCodigoVerificacion());
    }

    public function test_rechaza_un_codigo_incorrecto(): void
    {
        $this->usuarioPendiente('123456', new DateTimeImmutable('+10 minutes'));

        $this->expectException(CodigoVerificacionInvalidoException::class);

        $this->useCase->execute(new VerificarCorreoDTO('ana@example.com', '000000'));
    }

    public function test_al_quinto_intento_fallido_el_codigo_se_destruye(): void
    {
        $this->usuarioPendiente('123456', new DateTimeImmutable('+10 minutes'));

        for ($i = 1; $i <= 4; $i++) {
            try {
                $this->useCase->execute(new VerificarCorreoDTO('ana@example.com', '000000'));
                $this->fail('Debió lanzar excepción');
            } catch (CodigoVerificacionInvalidoException) {
                $this->assertSame($i, $this->repo->buscarPorId(1)->getIntentosVerificacion());
            }
        }

        // 5.º intento: se agota y el código deja de existir...
        try {
            $this->useCase->execute(new VerificarCorreoDTO('ana@example.com', '000000'));
            $this->fail('Debió lanzar excepción');
        } catch (IntentosAgotadosException) {
            $this->assertNull($this->repo->buscarPorId(1)->getCodigoVerificacion());
        }

        // ...así que ni siquiera el código correcto sirve ya.
        $this->expectException(CodigoVerificacionInvalidoException::class);
        $this->useCase->execute(new VerificarCorreoDTO('ana@example.com', '123456'));
    }

    public function test_rechaza_un_codigo_vencido(): void
    {
        $this->usuarioPendiente('123456', new DateTimeImmutable('-1 minute'));

        $this->expectException(CodigoVerificacionInvalidoException::class);

        $this->useCase->execute(new VerificarCorreoDTO('ana@example.com', '123456'));
    }

    public function test_correo_inexistente_da_el_mismo_error_que_codigo_incorrecto(): void
    {
        $this->expectException(CodigoVerificacionInvalidoException::class);

        $this->useCase->execute(new VerificarCorreoDTO('nadie@example.com', '123456'));
    }

    public function test_rechaza_un_correo_ya_verificado(): void
    {
        $this->usuarioPendiente('123456', new DateTimeImmutable('+10 minutes'));
        $this->useCase->execute(new VerificarCorreoDTO('ana@example.com', '123456'));

        $this->expectException(CorreoYaVerificadoException::class);

        $this->useCase->execute(new VerificarCorreoDTO('ana@example.com', '123456'));
    }

    private function usuarioPendiente(string $codigo, DateTimeImmutable $expiraEn): void
    {
        $this->repo->guardar(new Usuario(
            id: null,
            nombre: 'Ana',
            apellido: 'López',
            email: 'ana@example.com',
            rol: RolUsuario::CLIENTE,
            estado: EstadoUsuario::ACTIVO,
            passwordHash: 'hash:Secreto#123',
            codigoVerificacion: $codigo,
            codigoExpiraEn: $expiraEn,
        ));
    }
}
