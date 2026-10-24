{{-- Versión en texto plano de los avisos (NotificacionCorreo) --}}
@if (! empty($nombre))
Hola {!! $nombre !!}:

@endif
{!! $mensaje !!}
@if (! empty($enlace))

{!! $textoBoton ?? 'Ver en '.config('marca.nombre') !!}: {!! $enlace !!}
@endif

— {!! config('marca.nombre') !!}
