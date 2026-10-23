{{--
    Diseño común de los correos de Soirée (avisos y códigos de la cuenta).
    Los clientes de correo ignoran casi todo el CSS externo: tablas + estilos en línea.
    El logo va incrustado en el mensaje (cid), así se ve aunque el sitio no sea público.
    Secciones: titulo, previa (texto que se ve en la bandeja antes de abrirlo), contenido.
--}}
@php
    $marca = config('marca.nombre', 'Soirée');
    $logo = isset($message)
        ? $message->embed(public_path('img/marca/logo-correo.png'))
        : asset('img/marca/logo-correo.png');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light only">
    <title>@yield('titulo') · {{ $marca }}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f3eee6; -webkit-text-size-adjust: 100%;">

    <div style="display: none; max-height: 0; overflow: hidden; opacity: 0; color: transparent;">@yield('previa')</div>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f3eee6;">
        <tr>
            <td align="center" style="padding: 32px 14px;">

                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width: 560px;">

                    {{-- Encabezado: logo y nombre sobre negro --}}
                    <tr>
                        <td align="center" style="padding: 30px 24px 26px; background-color: #070602; border-radius: 18px 18px 0 0;">
                            <img src="{{ $logo }}" width="56" height="68" alt="" style="display: block; width: 56px; height: auto; border: 0;">
                            <div style="margin-top: 12px; font-family: Georgia, 'Times New Roman', serif; font-size: 26px; letter-spacing: 1px; color: #f2ede4;">{{ $marca }}</div>
                            <div style="margin-top: 4px; font-family: Arial, Helvetica, sans-serif; font-size: 10px; letter-spacing: 3px; text-transform: uppercase; color: #c8b48c;">Funciones y experiencias</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="height: 3px; line-height: 3px; font-size: 0; background-color: #b8925a;">&nbsp;</td>
                    </tr>

                    {{-- Contenido --}}
                    <tr>
                        <td style="padding: 36px 36px 34px; background-color: #fffdf9; border-radius: 0 0 18px 18px; font-family: Arial, Helvetica, sans-serif; font-size: 15px; line-height: 1.65; color: #3a332c;">
                            <h1 style="margin: 0 0 18px; font-family: Georgia, 'Times New Roman', serif; font-size: 27px; font-weight: normal; line-height: 1.25; color: #1c1916;">@yield('titulo')</h1>
                            @yield('contenido')
                        </td>
                    </tr>

                    {{-- Pie --}}
                    <tr>
                        <td align="center" style="padding: 22px 24px 0; font-family: Arial, Helvetica, sans-serif; font-size: 12px; line-height: 1.6; color: #8a8076;">
                            Este es un mensaje automático de {{ $marca }}; no es necesario responderlo.<br>
                            @if (config('marca.contacto'))
                                ¿Dudas? Escríbenos a <a href="mailto:{{ config('marca.contacto') }}" style="color: #7a5a24;">{{ config('marca.contacto') }}</a>.<br>
                            @endif
                            © {{ date('Y') }} {{ $marca }}
                        </td>
                    </tr>

                </table>

            </td>
        </tr>
    </table>

</body>
</html>
