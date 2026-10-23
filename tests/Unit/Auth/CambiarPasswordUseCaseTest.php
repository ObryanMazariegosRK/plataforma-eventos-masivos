<?php

namespace Tests\Unit\Auth;

use App\Application\DTOs\Auth\CambiarPasswordDTO;
use App\Application\UseCases\Auth\CambiarPasswordUseCase;
use App\Domain\Entities\Auth\Usuario;
use App\Domain\Enums\Auth\EstadoUsuario;
use App\Domain\Enums\Auth\RolUsuario;
use App\Domain\Exceptions\Auth\PasswordActualIncorrectaException;
use App\Domain\Exceptions\Auth\PasswordNuevaIgualException;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\Auth\PasswordHasherFalso;
use Tests\Fakes\Auth\TokenServiceFalso;
use Tests\Fakes\Auth\UsuarioRepositoryEnMemoria;

class CambiarPasswordUseCaseTest extends TestCase
{
    private UsuarioRepositoryEnMemoria $repo;
    private TokenServiceFalso $tokens;
    private CambiarPasswordUseCase $useCase;

    protected function setUp(): void
    {
        $this->repo = new UsuarioRepositoryEnMemoria();
        $this->tokens = new TokenServiceFalso();
        $this->useCase = new CambiarPasswordUseCase($this->repo, new PasswordHasherFalso(), $this->tokens);

        $this->repo->guardar(new Usuario(
            id: null,
            nombre: 'Ana',
            apellido: 'López',
            email: 'ana@example.com',
            rol: RolUsuario::CLIENTE,
            estado: EstadoUsuario::ACTIVO,
            passwordHash: 'hash:Secreto#123',
            emailVerificadoEn: new DateTimeImmutable(),
        ));
    }

    public function test_cambia_la_password_y_cierra_las_otras_sesiones_conservando_la_actual(): void
    {
        $this->useCase->execute(new CambiarPasswordDTO(1, 'Secreto#123', 'Nueva#456', tokenActualId: 7));

        $this->assertSame('hash:Nueva#456', $this->repo->buscarPorId(1)->getPasswordHash());
        $this->assertSame([1], $this->tokens->revocadosTodos);
        $this->assertSame([7], $this->tokens->tokensConservados);
    }

    public function test_rechaza_si_la_password_actual_es_incorrecta(): void
    {
        $this->expectException(PasswordActualIncorrectaException::class);

        $this->useCase->execute(new CambiarPasswordDTO(1, 'Equivocada#1', 'Nueva#456', tokenActualId: 7));
    }

    public function test_rechaza_si_la_nueva_es_igual_a_la_actual(): void
    {
        $this->expectException(PasswordNuevaIgualException::class);

        $this->useCase->execute(new CambiarPasswordDTO(1, 'Secreto#123', 'Secreto#123', tokenActualId: 7));
    }
}
