@extends('layouts.autenticacion')

@section('titulo', 'Iniciando sesión')
@section('eslogan', 'Un momento...')

@section('contenido')
    <h1>Iniciando sesión con Google...</h1>
    <p class="subtitulo">Te llevaremos a tu perfil en un instante.</p>
@endsection

@push('scripts')
<script>
    // El servidor nos mandó a /oauth/google#token=XXXX
    // Lo que va después de "#" (fragmento) existe solo en el navegador: nunca viajó al servidor.
    const token = new URLSearchParams(location.hash.substring(1)).get('token');

    if (token) {
        Auth.guardarToken(token);
        // replace(): borra esta URL (con el token) del historial, así "Atrás" no la muestra
        location.replace('/perfil');
    } else {
        location.replace('/login?tipo=error&mensaje=' + encodeURIComponent('No se pudo iniciar sesión con Google.'));
    }
</script>
@endpush
