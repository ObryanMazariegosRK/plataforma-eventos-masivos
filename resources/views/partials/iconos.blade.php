{{-- Ícono de Soirée en la pestaña (y al guardar la página en el celular). Se genera desde public/img/marca/logo-soiree.png --}}
<link rel="icon" href="{{ asset('favicon.ico') }}?v={{ filemtime(public_path('favicon.ico')) }}" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="{{ asset('img/marca/favicon-32.png') }}?v={{ filemtime(public_path('img/marca/favicon-32.png')) }}">
<link rel="apple-touch-icon" href="{{ asset('img/marca/apple-touch-icon.png') }}?v={{ filemtime(public_path('img/marca/apple-touch-icon.png')) }}">
{{-- Instalable como app: en Android las notificaciones salen a nombre de Soirée y no de Chrome --}}
<link rel="manifest" href="{{ asset('manifest.webmanifest') }}">
