<?php

namespace Tests\Unit\Boletos;

use App\Application\UseCases\Boletos\ObtenerQrBoletoUseCase;
use App\Domain\Abstractions\Boletos\IBoletoRepository;
use App\Application\Abstractions\Boletos\IGeneradorQrBoleto;
use App\Domain\Entities\Boletos\Boleto;
use App\Domain\Enums\Boletos\EstadoBoleto;
use DomainException;
use PHPUnit\Framework\TestCase;

class ObtenerQrBoletoUseCaseTest extends TestCase
{
    public function test_rechaza_consulta_de_boleto_ajeno(): void
    {
        // Crear un boleto simulado del usuario 20.
        $boleto = new Boleto(
            id: 15,
            reservaId: 1,
            inventarioId: 2,
            usuarioId: 20,
            codigoQr: str_repeat('a', 64),
            estado: EstadoBoleto::EMITIDO
        );

        // Simular el repositorio sin utilizar MySQL.
        $repositorio = $this->createMock(
            IBoletoRepository::class
        );

        $repositorio->method('buscarPorId')
            ->with(15)
            ->willReturn($boleto);

        // Verificar que nunca se genere el QR.
        $generadorQr = $this->createMock(
            IGeneradorQrBoleto::class
        );

        $generadorQr->expects($this->never())
            ->method('generarSvg');

        // Crear el caso de uso.
        $caso = new ObtenerQrBoletoUseCase(
            $repositorio,
            $generadorQr
        );

        // Esperar el rechazo por falta de autorización.
        $this->expectException(DomainException::class);

        $this->expectExceptionMessage(
            'El boleto no está disponible.'
        );

        // El usuario 10 intenta consultar
        // el boleto perteneciente al usuario 20.
        $caso->execute(15, 10);
    }

    
    public function test_permite_obtener_qr_de_boleto_propio(): void
    {
        // 1. Crear un identificador QR de prueba.
        $codigoQr = str_repeat('b', 64);

        // 2. Simular un boleto emitido del usuario 20.
        $boleto = new Boleto(
            id: 16,
            reservaId: 3,
            inventarioId: 4,
            usuarioId: 20,
            codigoQr: $codigoQr,
            estado: EstadoBoleto::EMITIDO
        );

        // 3. Simular la búsqueda del boleto.
        $repositorio = $this->createMock(
            IBoletoRepository::class
        );

        $repositorio->expects($this->once())
            ->method('buscarPorId')
            ->with(16)
            ->willReturn($boleto);

        // 4. Simular la generación del SVG.
        $generadorQr = $this->createMock(
            IGeneradorQrBoleto::class
        );

        $svgEsperado = '<svg xmlns="http://www.w3.org/2000/svg"></svg>';

        $generadorQr->expects($this->once())
            ->method('generarSvg')
            ->with($codigoQr)
            ->willReturn($svgEsperado);

        // 5. Crear el caso de uso.
        $caso = new ObtenerQrBoletoUseCase(
            $repositorio,
            $generadorQr
        );

        // 6. El usuario 20 solicita su propio boleto.
        $resultado = $caso->execute(16, 20);

        // 7. Comprobar que devuelve el SVG esperado.
        $this->assertSame($svgEsperado, $resultado);
    }
    
    public function test_rechaza_qr_de_boleto_usado(): void
    {
        // Crear un boleto que ya fue utilizado.
        $boleto = new Boleto(
            id: 17,
            reservaId: 3,
            inventarioId: 5,
            usuarioId: 20,
            codigoQr: str_repeat('c', 64),
            estado: EstadoBoleto::USADO
        );

        // Simular el repositorio.
        $repositorio = $this->createMock(
            IBoletoRepository::class
        );

        $repositorio->expects($this->once())
            ->method('buscarPorId')
            ->with(17)
            ->willReturn($boleto);

        // El generador QR nunca debe ejecutarse.
        $generadorQr = $this->createMock(
            IGeneradorQrBoleto::class
        );

        $generadorQr->expects($this->never())
            ->method('generarSvg');

        $caso = new ObtenerQrBoletoUseCase(
            $repositorio,
            $generadorQr
        );

        // Esperamos que el sistema rechace el boleto.
        $this->expectException(DomainException::class);

        $this->expectExceptionMessage(
            'El boleto no está en estado emitido.'
        );

        $caso->execute(17, 20);
    }

    public function test_rechaza_qr_de_boleto_anulado(): void
    {
        // Crear un boleto anulado.
        $boleto = new Boleto(
            id: 18,
            reservaId: 3,
            inventarioId: 6,
            usuarioId: 20,
            codigoQr: str_repeat('d', 64),
            estado: EstadoBoleto::ANULADO
        );

        // Simular el repositorio.
        $repositorio = $this->createMock(
            IBoletoRepository::class
        );

        $repositorio->expects($this->once())
            ->method('buscarPorId')
            ->with(18)
            ->willReturn($boleto);

        // No permitir generar el QR.
        $generadorQr = $this->createMock(
            IGeneradorQrBoleto::class
        );

        $generadorQr->expects($this->never())
            ->method('generarSvg');

        $caso = new ObtenerQrBoletoUseCase(
            $repositorio,
            $generadorQr
        );

        // Esperamos que el sistema rechace el boleto.
        $this->expectException(DomainException::class);

        $this->expectExceptionMessage(
            'El boleto no está en estado emitido.'
        );

        $caso->execute(18, 20);
    }
    
    public function test_rechaza_qr_de_boleto_inexistente(): void
    {
        // 1. Simular el repositorio de boletos.
        $repositorio = $this->createMock(
            IBoletoRepository::class
        );

        // 2. Simular que el boleto 999 no existe.
        $repositorio->expects($this->once())
            ->method('buscarPorId')
            ->with(999)
            ->willReturn(null);

        // 3. El generador QR nunca debe ejecutarse.
        $generadorQr = $this->createMock(
            IGeneradorQrBoleto::class
        );

        $generadorQr->expects($this->never())
            ->method('generarSvg');

        // 4. Crear el caso de uso.
        $caso = new ObtenerQrBoletoUseCase(
            $repositorio,
            $generadorQr
        );

        // 5. Esperamos que rechace la solicitud.
        $this->expectException(DomainException::class);

        $this->expectExceptionMessage(
            'El boleto no está disponible.'
        );

        // 6. El usuario 20 solicita un boleto inexistente.
        $caso->execute(999, 20);
    }

}
