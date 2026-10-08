<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><title>Recupera tu contraseña</title></head>
<body style="font-family: Arial, sans-serif; color: #222;">
    <p>Hola {{ $nombre }},</p>
    <p>Recibimos una solicitud para restablecer tu contraseña. Tu código es:</p>
    <p style="font-size: 28px; font-weight: bold; letter-spacing: 6px;">{{ $codigo }}</p>
    <p>El código vence en {{ $minutosValidez }} minutos. Si no fuiste tú, ignora este correo: tu contraseña no cambiará.</p>
</body>
</html>
