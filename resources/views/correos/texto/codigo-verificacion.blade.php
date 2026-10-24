{{-- Versión en texto plano (sin HTML) del correo de verificación: los filtros de spam desconfían de los correos que solo traen HTML --}}
Hola {!! $nombre !!}:

Gracias por unirte a {!! config('marca.nombre') !!}. Tu código para verificar tu cuenta es:

{!! $codigo !!}

Vence en {!! $minutosValidez !!} minutos. Si no creaste una cuenta, puedes ignorar este correo.

— {!! config('marca.nombre') !!}
