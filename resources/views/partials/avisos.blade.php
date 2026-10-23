{{-- Avisos de la tienda: campana en la cabecera (campana.js, tras el menú de cuenta de catalogo.js)
     y notificaciones push del dispositivo (notificaciones.js + public/sw.js) --}}
<meta name="vapid-public-key" content="{{ config('webpush.vapid.public_key') }}">
<link rel="stylesheet" href="{{ asset('css/campana.css') }}?v={{ filemtime(public_path('css/campana.css')) }}">
<script src="{{ asset('js/notificaciones.js') }}?v={{ filemtime(public_path('js/notificaciones.js')) }}" defer></script>
<script src="{{ asset('js/campana.js') }}?v={{ filemtime(public_path('js/campana.js')) }}" defer></script>
