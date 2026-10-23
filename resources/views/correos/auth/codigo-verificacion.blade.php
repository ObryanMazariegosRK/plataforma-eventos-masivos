@extends('correos.layout')

@section('titulo', 'Verifica tu correo')
@section('previa', "Tu código es {$codigo}. Vence en {$minutosValidez} minutos.")

@section('contenido')
    <p style="margin: 0 0 14px;">Hola {{ $nombre }}, gracias por unirte a {{ config('marca.nombre') }}. Escribe este código para activar tu cuenta:</p>
    @include('correos.partes.codigo', ['codigo' => $codigo])
    <p style="margin: 0; font-size: 13.5px; color: #6f665d;">Vence en {{ $minutosValidez }} minutos. Si no creaste una cuenta, puedes ignorar este correo.</p>
@endsection
