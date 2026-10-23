{{-- Sesión: va lo más arriba del <head> y sin defer, antes que cualquier script que lea el token (public/js/sesion.js) --}}
<script src="{{ asset('js/sesion.js') }}?v={{ filemtime(public_path('js/sesion.js')) }}"></script>
