@extends('layouts.autenticacion')

@section('titulo', 'Pago cancelado')
@section('eslogan', 'Tu reserva sigue apartada por ahora.')

@section('contenido')
    <h1>Cancelaste el pago</h1>
    <p class="subtitulo">No se realizó ningún cobro. Tu reserva sigue activa hasta que venza, así que todavía puedes pagarla.</p>

    <a id="enlaceReintentar" href="{{ route('pagos.pagar') }}" class="boton" style="display:block;text-align:center">Intentar pagar de nuevo</a>
    <p class="pie"><a href="{{ route('perfil') }}">Volver a mi perfil</a></p>
@endsection

@push('scripts')
<script>
    const pagoId = new URLSearchParams(location.search).get('pago');

    // Se consulta el pago para saber qué reserva reintentar
    (async () => {
        if (!pagoId || !Auth.obtenerToken()) return;
        const pago = await Auth.api(`/pagos/${pagoId}`, { metodo: 'GET', conToken: true });
        if (pago.ok) {
            document.getElementById('enlaceReintentar').href = `/pagar?reserva=${pago.data.reserva_id}`;
        }
    })();
</script>
@endpush
