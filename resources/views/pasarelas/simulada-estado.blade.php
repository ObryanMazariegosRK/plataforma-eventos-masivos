{{-- Pasarela simulada: la orden ya no se puede pagar (ya se pagó, se rechazó, venció o no existe).
     Aparece al volver con "Atrás" o al abrir un enlace viejo, en lugar del formulario de la tarjeta. --}}
@php
    $total = $datos ? $datos['moneda'].' '.number_format($datos['monto'], 2) : null;

    [$titulo, $texto, $boton, $destino] = match (true) {
        $estado === 'procesada' && $datos['estado'] === \App\Infrastructure\Pasarelas\PasarelaSimulada::RECHAZADA => [
            'El pago fue rechazado',
            'El banco no autorizó este pago, así que no se cobró nada. Puedes intentarlo de nuevo con otra tarjeta.',
            'Ver detalles e intentar de nuevo',
            $datos['url_retorno'],
        ],
        $estado === 'procesada' => [
            'Este pago ya fue procesado',
            "Ya autorizaste el pago de {$total}. No se volverá a cobrar.",
            'Ver el resultado de mi compra',
            $datos['url_retorno'],
        ],
        $estado === 'vencida' => [
            'Esta orden de pago venció',
            'Se terminó el tiempo de tu reserva antes de completar el pago, así que no se realizó ningún cobro. Los asientos se liberaron: puedes elegirlos de nuevo.',
            'Volver a la cartelera',
            route('catalogo.index'),
        ],
        default => [
            'No encontramos esta orden de pago',
            'El enlace es incorrecto o ya expiró. No se realizó ningún cobro.',
            'Ir a la cartelera',
            route('catalogo.index'),
        ],
    };
    $exito = $estado === 'procesada' && $datos['estado'] !== \App\Infrastructure\Pasarelas\PasarelaSimulada::RECHAZADA;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex,nofollow">
    <meta name="color-scheme" content="dark">
    <title>{{ $titulo }} · Pasarela simulada</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;1,400&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/pasarela-simulada.css') }}?v={{ filemtime(public_path('css/pasarela-simulada.css')) }}">
</head>
<body>
    <svg width="0" height="0" style="position:absolute" aria-hidden="true">
        <symbol id="candado" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>
        </symbol>
    </svg>

    <div class="pagina">
        <main class="caja">
            <div class="cuerpo estado">
                <div class="estado__icono {{ $exito ? 'estado__icono--ok' : '' }}" aria-hidden="true">
                    @if ($exito)
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12.5 4.5 4.5L19 7.5"/></svg>
                    @else
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7.5V12l3 2"/></svg>
                    @endif
                </div>

                @if ($datos)
                    <span class="etiqueta">{{ $datos['referencia'] }} · {{ $total }}</span>
                @endif
                <h1 class="estado__titulo">{{ $titulo }}</h1>
                <p class="estado__texto">{{ $texto }}</p>

                <a class="pagar" href="{{ $destino }}">{{ $boton }}</a>
            </div>
        </main>

        <div class="pie"><svg><use href="#candado"/></svg> Pago cifrado · simulación para pruebas</div>
    </div>
</body>
</html>
