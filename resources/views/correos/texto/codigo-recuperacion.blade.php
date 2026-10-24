{{-- Versión en texto plano del correo para restablecer la contraseña --}}
Hola {!! $nombre !!}:

Recibimos una solicitud para restablecer tu contraseña en {!! config('marca.nombre') !!}. Tu código es:

{!! $codigo !!}

Vence en {!! $minutosValidez !!} minutos. Si no fuiste tú, ignora este correo: tu contraseña no cambiará.

— {!! config('marca.nombre') !!}
