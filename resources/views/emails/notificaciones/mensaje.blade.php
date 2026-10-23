{{-- Avisos de Soirée (EnviarNotificacionCorreoJob → NotificacionCorreo) con el diseño común de correos --}}
@extends('correos.layout')

@php
    $parrafos = preg_split('/\R\s*\R/', trim($mensaje));
@endphp

@section('titulo', $asunto)
@section('previa', \Illuminate\Support\Str::limit(preg_replace('/\s+/', ' ', $parrafos[0] ?? ''), 140))

@section('contenido')
    @if (! empty($nombre))
        <p style="margin: 0 0 14px;">Hola {{ $nombre }},</p>
    @endif

    @foreach ($parrafos as $parrafo)
        <p style="margin: 0 0 14px; white-space: pre-line;">{{ $parrafo }}</p>
    @endforeach

    @if (! empty($enlace))
        <table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin: 26px 0 4px;">
            <tr>
                <td style="border-radius: 999px; background-color: #1c1916;">
                    <a href="{{ $enlace }}" style="display: inline-block; padding: 13px 28px; font-family: Arial, Helvetica, sans-serif; font-size: 14px; font-weight: bold; color: #f2ede4; text-decoration: none; border-radius: 999px;">{{ $textoBoton ?? 'Ver en '.config('marca.nombre') }}</a>
                </td>
            </tr>
        </table>
    @endif
@endsection
