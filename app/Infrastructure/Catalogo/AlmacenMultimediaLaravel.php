<?php
namespace App\Infrastructure\Catalogo;

use App\Domain\Abstractions\Catalogo\IAlmacenMultimedia;
use GdImage;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use SplFileInfo;
use Symfony\Component\HttpFoundation\File\File as ArchivoSymfony;

// Misma lógica que la tienda (LaravelImageStorageService): las imágenes se reducen y se
// pasan a WebP (o JPEG si el servidor no tiene WebP); los videos se guardan tal cual.
class AlmacenMultimediaLaravel implements IAlmacenMultimedia
{
    private const DISCO = 'public';
    private const CALIDAD_WEBP = 80;
    private const CALIDAD_JPEG = 82;

    public function guardar(SplFileInfo $archivo, string $carpeta, int $ladoMaximo = 1920): string
    {
        $archivo = $archivo instanceof ArchivoSymfony ? $archivo : new File($archivo->getPathname());

        $optimizada = str_starts_with((string) $archivo->getMimeType(), 'image/')
            ? $this->optimizar($archivo->getRealPath(), $ladoMaximo, $archivo->getSize())
            : null;

        if ($optimizada) {
            [$contenido, $extension] = $optimizada;
            $ruta = $carpeta . '/' . Str::random(40) . '.' . $extension;
            $guardado = Storage::disk(self::DISCO)->put($ruta, $contenido) ? $ruta : false;
        } else {
            // Videos, o una imagen que no se pudo procesar: se guarda el original
            $guardado = Storage::disk(self::DISCO)->putFile($carpeta, $archivo);
        }

        if (!$guardado) {
            throw new RuntimeException('No se pudo guardar el archivo en el servidor. Inténtalo de nuevo.');
        }

        return $guardado;
    }

    public function eliminar(string $ruta): void
    {
        try {
            Storage::disk(self::DISCO)->delete($ruta);
        } catch (\Throwable $e) {
            Log::warning("No se pudo borrar el archivo {$ruta}: " . $e->getMessage());
        }
    }

    public function url(string $ruta): string
    {
        // Relativa (/storage/...): funciona igual en localhost, ngrok o el dominio de producción
        return '/storage/' . ltrim($ruta, '/');
    }

    /**
     * Reduce la imagen a $ladoMaximo px por su lado mayor y la comprime. Retorna
     * [contenido, extensión] o null si no conviene: no se pudo leer, o el resultado no
     * pesa menos que el original.
     */
    private function optimizar(string $rutaAbsoluta, int $ladoMaximo, int $pesoOriginal): ?array
    {
        if (!function_exists('imagecreatefromstring')) {
            return null;
        }

        // Una foto de celular de 12 MP ocupa ~50 MB al descomprimirse en memoria
        $limiteAnterior = ini_get('memory_limit');
        ini_set('memory_limit', '512M');

        try {
            $info = @getimagesize($rutaAbsoluta);
            if (!$info || $info[0] * $info[1] > 40_000_000) {
                return null;   // no es imagen, o es demasiado grande para procesarla con seguridad
            }

            $imagen = @imagecreatefromstring(file_get_contents($rutaAbsoluta));
            if (!$imagen) {
                return null;
            }

            if ($info[2] === IMAGETYPE_JPEG) {
                $imagen = $this->corregirOrientacion($imagen, $rutaAbsoluta);
            }

            $ancho = imagesx($imagen);
            $alto = imagesy($imagen);
            $escala = min(1, $ladoMaximo / max($ancho, $alto));
            $nuevoAncho = max(1, (int) round($ancho * $escala));
            $nuevoAlto = max(1, (int) round($alto * $escala));

            $usarWebp = function_exists('imagewebp');
            $lienzo = imagecreatetruecolor($nuevoAncho, $nuevoAlto);
            if ($usarWebp) {
                imagealphablending($lienzo, false);
                imagesavealpha($lienzo, true);
                imagefill($lienzo, 0, 0, imagecolorallocatealpha($lienzo, 0, 0, 0, 127));
            } else {
                // JPEG no tiene transparencia: fondo negro, como el catálogo
                imagefill($lienzo, 0, 0, imagecolorallocate($lienzo, 13, 12, 11));
            }
            imagecopyresampled($lienzo, $imagen, 0, 0, 0, 0, $nuevoAncho, $nuevoAlto, $ancho, $alto);
            imagedestroy($imagen);

            ob_start();
            $usarWebp ? imagewebp($lienzo, null, self::CALIDAD_WEBP) : imagejpeg($lienzo, null, self::CALIDAD_JPEG);
            $contenido = ob_get_clean();
            imagedestroy($lienzo);

            if (!$contenido || strlen($contenido) >= $pesoOriginal) {
                return null;
            }

            return [$contenido, $usarWebp ? 'webp' : 'jpg'];
        } catch (\Throwable $e) {
            Log::warning('No se pudo optimizar la imagen, se guarda la original: ' . $e->getMessage());
            return null;
        } finally {
            ini_set('memory_limit', $limiteAnterior);
        }
    }

    // Las fotos de celular guardan la rotación en EXIF; al recomprimirlas se pierde,
    // así que se aplica a los píxeles para que no salgan de lado
    private function corregirOrientacion(GdImage $imagen, string $rutaAbsoluta): GdImage
    {
        if (!function_exists('exif_read_data')) {
            return $imagen;
        }

        $orientacion = (int) (@exif_read_data($rutaAbsoluta)['Orientation'] ?? 1);

        $rotada = match ($orientacion) {
            3, 4 => imagerotate($imagen, 180, 0),
            5, 6 => imagerotate($imagen, -90, 0),
            7, 8 => imagerotate($imagen, 90, 0),
            default => $imagen,
        };
        if (in_array($orientacion, [2, 4, 5, 7], true)) {
            imageflip($rotada, IMG_FLIP_HORIZONTAL);
        }

        return $rotada;
    }
}
