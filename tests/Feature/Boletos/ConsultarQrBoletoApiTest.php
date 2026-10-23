<?php

namespace Tests\Feature\Boletos;

use Tests\TestCase;
use App\Application\Abstractions\Boletos\IObtenerQrBoletoUseCase;
use Illuminate\Foundation\Auth\User as UsuarioSimulado;
use Laravel\Sanctum\Sanctum;

use App\Domain\Abstractions\Boletos\IBoletoRepository;
use App\Domain\Entities\Boletos\Boleto;
use App\Domain\Enums\Boletos\EstadoBoleto;

class ConsultarQrBoletoApiTest extends TestCase
{
    public function test_rechaza_consulta_qr_sin_autenticacion(): void
    {
        // Simular una solicitud sin token Sanctum.
        $respuesta = $this->getJson(
            '/api/boletos/1/qr'
        );

        // Comprobar que Laravel rechaza el acceso.
        $respuesta->assertStatus(401);

        // Comprobar que devuelve un mensaje JSON.
        $respuesta->assertJson([
            'message' => 'Unauthenticated.'
        ]);
    }
    
    public function test_usuario_autenticado_puede_obtener_qr(): void
    {
        
        // 1. Crear un usuario simulado compatible con Sanctum.
        $usuario = new class extends UsuarioSimulado {
            use \Laravel\Sanctum\HasApiTokens;
        };

        $usuario->id = 20;


        // 2. Simular autenticacion con Sanctum.
        Sanctum::actingAs($usuario);

        // 3. Preparar una respuesta SVG de prueba.
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"></svg>';

        // 4. Simular el caso de uso.
        $caso = $this->createMock(
            IObtenerQrBoletoUseCase::class
        );

        $caso->expects($this->once())
            ->method('execute')
            ->with(15, 20)
            ->willReturn($svg);

        // 5. Registrar el caso de uso simulado.
        $this->app->instance(
            IObtenerQrBoletoUseCase::class,
            $caso
        );

        // 6. Realizar una solicitud HTTP autenticada.
        $respuesta = $this->get(
            '/api/boletos/15/qr',
            ['Accept' => 'image/svg+xml']
        );

        // 7. Verificar la respuesta HTTP.
        $respuesta->assertStatus(200);

        // 8. Comprobar el tipo de contenido.
        $respuesta->assertHeader(
            'Content-Type',
            'image/svg+xml; charset=UTF-8'
        );

        // 9. Verificar que devuelve el SVG esperado.
        $this->assertSame(
            $svg,
            $respuesta->getContent()
        );
    }
    
    public function test_devuelve_404_si_boleto_no_disponible(): void
    {
        // 1. Crear un usuario simulado compatible con Sanctum.
        $usuario = new class extends UsuarioSimulado {
            use \Laravel\Sanctum\HasApiTokens;
        };

        $usuario->id = 20;

        // 2. Simular la autenticación.
        Sanctum::actingAs($usuario);

        // 3. Simular el caso de uso.
        $caso = $this->createMock(
            IObtenerQrBoletoUseCase::class
        );

        // 4. Simular que el boleto no está disponible.
        $caso->expects($this->once())
            ->method('execute')
            ->with(999, 20)
            ->willThrowException(
                new \DomainException(
                    'El boleto no está disponible.'
                )
            );

        // 5. Registrar el caso de uso simulado.
        $this->app->instance(
            IObtenerQrBoletoUseCase::class,
            $caso
        );

        // 6. Realizar la solicitud HTTP.
        $respuesta = $this->getJson(
            '/api/boletos/999/qr'
        );

        // 7. Verificar que devuelve HTTP 404.
        $respuesta->assertStatus(404);

        // 8. Comprobar el mensaje de respuesta.
        $respuesta->assertJson([
            'message' => 'Boleto no disponible.'
        ]);
    }
    
    public function test_api_genera_codigo_qr_svg_real(): void
    {
        // 1. Crear un usuario simulado compatible con Sanctum.
        $usuario = new class extends UsuarioSimulado {
            use \Laravel\Sanctum\HasApiTokens;
        };

        $usuario->id = 20;

        // 2. Simular la autenticación.
        Sanctum::actingAs($usuario);

        // 3. Crear un boleto de prueba en memoria.
        $boleto = new Boleto(
            id: 15,
            reservaId: 3,
            inventarioId: 4,
            usuarioId: 20,
            codigoQr: str_repeat('e', 64),
            estado: EstadoBoleto::EMITIDO
        );

        // 4. Simular solamente el repositorio.
        $repositorio = $this->createMock(
            IBoletoRepository::class
        );

        $repositorio->expects($this->once())
            ->method('buscarPorId')
            ->with(15)
            ->willReturn($boleto);

        // 5. Registrar el repositorio simulado.
        $this->app->instance(
            IBoletoRepository::class,
            $repositorio
        );

        // 6. Solicitar el QR mediante la API.
        $respuesta = $this->get(
            '/api/boletos/15/qr',
            ['Accept' => 'image/svg+xml']
        );

        // 7. Verificar que la respuesta sea exitosa.
        $respuesta->assertStatus(200);

        // 8. Comprobar que devuelve un SVG real.
        $respuesta->assertHeader(
            'Content-Type',
            'image/svg+xml; charset=UTF-8'
        );

        $this->assertStringContainsString(
            '<svg',
            $respuesta->getContent()
        );

        // 9. Verificar los encabezados de seguridad.
        
        $this->assertTrue(
            $respuesta->headers->hasCacheControlDirective('private')
        );

        $this->assertTrue(
            $respuesta->headers->hasCacheControlDirective('no-store')
        );


        $respuesta->assertHeader(
            'X-Content-Type-Options',
            'nosniff'
        );
    }

}
