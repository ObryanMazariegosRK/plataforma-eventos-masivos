<?php
// Regenera los íconos de Soirée desde public/img/marca/logo-soiree.png (favicon.ico, favicon-32,
// apple-touch-icon, íconos de la app instalable, ícono e insignia de las notificaciones y logo de
// los correos). Uso: docker compose exec -T app php < scripts/generar-iconos.php
$origen = imagecreatefrompng('public/img/marca/logo-soiree.png');
$fondo = [7, 6, 2];   // el negro del propio logo

function lienzo(int $w, int $h, ?array $color) {
    $im = imagecreatetruecolor($w, $h);
    imagealphablending($im, false); imagesavealpha($im, true);
    imagefill($im, 0, 0, $color ? imagecolorallocatealpha($im, ...[...$color, 0]) : imagecolorallocatealpha($im, 0, 0, 0, 127));
    imagealphablending($im, true);
    return $im;
}
// Esquinas redondeadas: borra (alfa) lo que queda fuera
function redondear($im, float $radio) {
    $n = imagesx($im); $r = $radio * $n;
    imagealphablending($im, false);
    $t = imagecolorallocatealpha($im, 0, 0, 0, 127);
    for ($y = 0; $y < $n; $y++) for ($x = 0; $x < $n; $x++) {
        $cx = $x < $r ? $r : ($x > $n - 1 - $r ? $n - 1 - $r : $x);
        $cy = $y < $r ? $r : ($y > $n - 1 - $r ? $n - 1 - $r : $y);
        if (hypot($x - $cx, $y - $cy) > $r) imagesetpixel($im, $x, $y, $t);
    }
    imagesavealpha($im, true);
    return $im;
}
// La S sola (sin el aro), más clara para que se lea en tamaños chicos
$S = [50, 66, 198, 250];
function iconoS($origen, array $S, int $n, array $fondo, float $alto = .82, bool $redondo = true) {
    $im = lienzo($n, $n, $fondo);
    $h = $alto * $n; $w = $h * $S[2] / $S[3];
    imagecopyresampled($im, $origen, (int) round(($n - $w) / 2), (int) round(($n - $h) / 2), $S[0], $S[1], (int) round($w), (int) round($h), $S[2], $S[3]);
    if ($n <= 48) { imagefilter($im, IMG_FILTER_BRIGHTNESS, 18); imagefilter($im, IMG_FILTER_CONTRAST, -12); }
    return $redondo ? redondear($im, .22) : $im;
}
// Logo completo (con el aro) centrado en un cuadrado
function iconoCompleto($origen, int $n, array $fondo, float $alto = .94) {
    $im = lienzo($n, $n, $fondo);
    $h = $alto * $n; $w = $h * imagesx($origen) / imagesy($origen);
    imagecopyresampled($im, $origen, (int) round(($n - $w) / 2), (int) round(($n - $h) / 2), 0, 0, (int) round($w), (int) round($h), imagesx($origen), imagesy($origen));
    return $im;
}

$archivos = [];
foreach ([16, 32, 48] as $n) { ob_start(); imagepng(iconoS($origen, $S, $n, $fondo, .86)); $archivos[$n] = ob_get_clean(); }
// favicon.ico con PNG dentro (16, 32, 48)
$ico = pack('vvv', 0, 1, count($archivos)); $offset = 6 + 16 * count($archivos); $datos = '';
foreach ($archivos as $n => $png) { $ico .= pack('CCCCvvVV', $n, $n, 0, 0, 1, 32, strlen($png), $offset); $offset += strlen($png); $datos .= $png; }
file_put_contents('public/favicon.ico', $ico . $datos);
file_put_contents('public/img/marca/favicon-32.png', $archivos[32]);
imagepng(iconoS($origen, $S, 180, $fondo, .74, false), 'public/img/marca/apple-touch-icon.png');
// App instalable (manifest): cuadrado completo, con margen para el recorte "maskable" de Android
imagepng(iconoS($origen, $S, 512, $fondo, .62, false), 'public/img/marca/icono-512.png');
imagepng(iconoS($origen, $S, 192, $fondo, .62, false), 'public/img/marca/icono-app-192.png');

// Notificación: logo completo (Android lo recorta en círculo)
imagepng(iconoCompleto($origen, 192, $fondo), 'public/img/notificacion.png');

// Insignia (barra de estado de Android): S blanca sobre transparente; el alfa sale del brillo
$n = 96; $ins = lienzo($n, $n, null); imagealphablending($ins, false);
$h = .86 * $n; $w = $h * $S[2] / $S[3]; $x0 = ($n - $w) / 2; $y0 = ($n - $h) / 2;
$tmp = imagecreatetruecolor((int) round($w), (int) round($h));
imagecopyresampled($tmp, $origen, 0, 0, $S[0], $S[1], (int) round($w), (int) round($h), $S[2], $S[3]);
for ($y = 0; $y < imagesy($tmp); $y++) for ($x = 0; $x < imagesx($tmp); $x++) {
    $c = imagecolorat($tmp, $x, $y); $b = max($c >> 16 & 255, $c >> 8 & 255, $c & 255);
    $a = min(1, max(0, ($b - 35) / 110));
    imagesetpixel($ins, (int) round($x0) + $x, (int) round($y0) + $y, imagecolorallocatealpha($ins, 255, 255, 255, (int) round(127 * (1 - $a))));
}
imagesavealpha($ins, true); imagepng($ins, 'public/img/notificacion-insignia.png');

// Correo: logo completo a 2x para pantallas nítidas (se muestra a 64 px de ancho)
$w = 128; $h = (int) round($w * imagesy($origen) / imagesx($origen));
$correo = imagecreatetruecolor($w, $h); imagecopyresampled($correo, $origen, 0, 0, 0, 0, $w, $h, imagesx($origen), imagesy($origen));
imagepng($correo, 'public/img/marca/logo-correo.png');
echo "listo\n";
