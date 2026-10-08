<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Código de verificación</title></head>
<body style="font-family: Arial, sans-serif; color: #222;">
    <p>Hola {{ $nombre }},</p>
    <p>Tu código para verificar tu cuenta es:</p>
    <p style="font-size: 28px; font-weight: bold; letter-spacing: 6px;">{{ $codigo }}</p>
    <p>El código vence en {{ $minutosValidez }} minutos. Si no creaste una cuenta, ignora este correo.</p>
</body>
</html>
