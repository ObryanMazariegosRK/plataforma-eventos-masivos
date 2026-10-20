<?php

namespace Tests\Unit\Catalogo;

use App\Application\DTOs\Catalogo\ActualizarBannerDTO;
use App\Application\UseCases\Catalogo\ActualizarBannerCatalogoUseCase;
use App\Application\UseCases\Catalogo\EliminarBannerCatalogoUseCase;
use App\Domain\Exceptions\Catalogo\ArchivoBannerInvalidoException;
use PHPUnit\Framework\TestCase;
use SplFileInfo;
use Tests\Fakes\Catalogo\AlmacenMultimediaEnMemoria;
use Tests\Fakes\Catalogo\BannerRepositoryEnMemoria;

class ActualizarBannerCatalogoUseCaseTest extends TestCase
{
    private BannerRepositoryEnMemoria $banners;
    private AlmacenMultimediaEnMemoria $almacen;
    private ActualizarBannerCatalogoUseCase $actualizar;

    protected function setUp(): void
    {
        $this->banners = new BannerRepositoryEnMemoria();
        $this->almacen = new AlmacenMultimediaEnMemoria();
        $this->actualizar = new ActualizarBannerCatalogoUseCase($this->banners, $this->almacen);
    }

    private function dto(string $nombre, string $mime): ActualizarBannerDTO
    {
        return new ActualizarBannerDTO(new SplFileInfo($nombre), $mime, 7);
    }

    public function test_guarda_una_imagen_como_banner(): void
    {
        $resultado = $this->actualizar->execute($this->dto('teatro.jpg', 'image/jpeg'));

        $this->assertSame(['tipo' => 'imagen', 'url' => '/storage/banners/archivo-1.jpg'], $resultado);
        $this->assertSame(7, $this->banners->banner->getActualizadoPor());
    }

    public function test_reemplazar_por_un_video_borra_el_archivo_anterior(): void
    {
        $this->actualizar->execute($this->dto('teatro.jpg', 'image/jpeg'));
        $resultado = $this->actualizar->execute($this->dto('telon.mp4', 'video/mp4'));

        $this->assertSame('video', $resultado['tipo']);
        $this->assertSame(['banners/archivo-2.mp4' => true], $this->almacen->archivos);
    }

    public function test_rechaza_tipos_que_no_son_imagen_ni_video(): void
    {
        $this->expectException(ArchivoBannerInvalidoException::class);

        try {
            $this->actualizar->execute($this->dto('documento.pdf', 'application/pdf'));
        } finally {
            $this->assertSame([], $this->almacen->archivos);   // ni siquiera se guardó
        }
    }

    public function test_si_falla_la_base_borra_el_archivo_nuevo_y_conserva_el_anterior(): void
    {
        $this->actualizar->execute($this->dto('teatro.jpg', 'image/jpeg'));
        $this->banners->fallarAlGuardar = true;

        try {
            $this->actualizar->execute($this->dto('telon.mp4', 'video/mp4'));
            $this->fail('Debió propagar el error de la base de datos.');
        } catch (\RuntimeException) {
        }

        $this->assertSame(['banners/archivo-1.jpg' => true], $this->almacen->archivos);
        $this->assertSame('banners/archivo-1.jpg', $this->banners->banner->getRuta());
    }

    public function test_eliminar_quita_el_banner_y_su_archivo(): void
    {
        $this->actualizar->execute($this->dto('teatro.jpg', 'image/jpeg'));

        (new EliminarBannerCatalogoUseCase($this->banners, $this->almacen))->execute();

        $this->assertNull($this->banners->banner);
        $this->assertSame([], $this->almacen->archivos);
    }
}
