<?php

namespace Tests\Unit\Reembolsos;

use App\Application\UseCases\Reembolsos\CalcularReembolsoUseCase;
use DateTimeImmutable;
use DomainException;
use Tests\TestCase;

class CalcularReembolsoUseCaseTest extends TestCase
{
    private CalcularReembolsoUseCase $calculadora;
    private DateTimeImmutable $ahora;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculadora = app(
            CalcularReembolsoUseCase::class
        );

        // Fecha fija para evitar pruebas inconsistentes.
        $this->ahora = new DateTimeImmutable(
            '2026-10-15 12:00:00'
        );
    }

    private function calcular(
        int $horasRestantes,
        float $monto = 500.00
    ): array {
        $fechaEvento = $this->ahora->modify(
            "+{$horasRestantes} hours"
        );

        return $this->calculadora->execute(
            $monto,
            $fechaEvento,
            $this->ahora
        );
    }

    public function test_reembolso_completo_con_10_dias(): void
    {
        $resultado = $this->calcular(240);

        $this->assertEquals(0, $resultado['penalizacion']);
        $this->assertEquals(500, $resultado['monto_reembolso']);
        $this->assertEquals(0, $resultado['porcentaje_penalizacion']);
    }

    public function test_reembolso_completo_exactamente_a_7_dias(): void
    {
        $resultado = $this->calcular(168);

        $this->assertEquals(500, $resultado['monto_reembolso']);
    }

    public function test_penalizacion_20_por_ciento_con_5_dias(): void
    {
        $resultado = $this->calcular(120);

        $this->assertEquals(100, $resultado['penalizacion']);
        $this->assertEquals(400, $resultado['monto_reembolso']);
        $this->assertEquals(20, $resultado['porcentaje_penalizacion']);
    }

    public function test_penalizacion_20_por_ciento_exactamente_a_72_horas(): void
    {
        $resultado = $this->calcular(72);

        $this->assertEquals(400, $resultado['monto_reembolso']);
    }

    public function test_penalizacion_50_por_ciento_con_48_horas(): void
    {
        $resultado = $this->calcular(48);

        $this->assertEquals(250, $resultado['penalizacion']);
        $this->assertEquals(250, $resultado['monto_reembolso']);
        $this->assertEquals(50, $resultado['porcentaje_penalizacion']);
    }

    public function test_penalizacion_50_por_ciento_exactamente_a_24_horas(): void
    {
        $resultado = $this->calcular(24);

        $this->assertEquals(250, $resultado['monto_reembolso']);
    }

    public function test_rechaza_cancelacion_con_menos_de_24_horas(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'La cancelación está fuera de los plazos permitidos.'
        );

        $this->calcular(12);
    }

    public function test_rechaza_monto_cero(): void
    {
        $this->expectException(DomainException::class);

        $this->calcular(240, 0);
    }

    public function test_rechaza_politica_deshabilitada(): void
    {
        config()->set('reembolsos.politica_habilitada', false);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage(
            'La política de reembolsos aún no está configurada.'
        );

        $this->calcular(240);
    }
}
