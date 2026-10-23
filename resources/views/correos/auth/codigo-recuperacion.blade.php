@extends('correos.layout')

@section('titulo', 'Restablece tu contraseña')
@section('previa', "Tu código es {$codigo}. Vence en {$minutosValidez} minutos.")

@section('contenido')
    <p style="margin: 0 0 14px;">Hola {{ $nombre }}, recibimos una solicitud para restablecer tu contraseña. Tu código es:</p>
    @include('correos.partes.codigo', ['codigo' => $codigo])
    <p style="margin: 0; font-size: 13.5px; color: #6f665d;">Vence en {{ $minutosValidez }} minutos. Si no fuiste tú, ignora este correo: tu contraseña no cambiará.</p>
@endsection
