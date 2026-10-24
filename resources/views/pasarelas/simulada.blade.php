{{-- Imita la página EXTERNA de una pasarela (como la de PayPal o Stripe). No usa el diseño de la app a propósito. --}}
@php($tarjetaPrueba = config('pagos.simulada.tarjetas.aprobada'))
@php($total = $datos['moneda'].' '.number_format($datos['monto'], 2))
{{-- Segundos que quedan para pagar (la orden vence junto con la reserva) --}}
@php($restante = isset($datos['vence_en']) ? max(0, $datos['vence_en'] - now()->getTimestamp()) : null)
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    @include('partials.sesion')
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex,nofollow">
    <meta name="color-scheme" content="dark">
    <title>Pago seguro · Pasarela simulada</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;1,400&family=Inter:wght@300;400;500;600&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/pasarela-simulada.css') }}?v={{ filemtime(public_path('css/pasarela-simulada.css')) }}">
    @include('partials.iconos')
</head>
<body>
    <svg width="0" height="0" style="position:absolute" aria-hidden="true">
        <symbol id="candado" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="4" y="11" width="16" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/>
        </symbol>
        <symbol id="alerta" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/>
        </symbol>
    </svg>

    <div class="pagina">
        <main class="caja">
            <div class="cuerpo">
                <div class="resumen">
                    <div>
                        <span class="etiqueta">Pagar a {{ config('marca.nombre') }}</span>
                        <div class="monto">{{ $total }}</div>
                    </div>
                    <div class="ref">
                        @if ($restante !== null)
                            <div class="reloj" id="reloj" data-restante="{{ $restante }}" title="Tu reserva vence y los asientos se liberan">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" aria-hidden="true"><circle cx="12" cy="13" r="8"/><path d="M12 9v4l2.5 1.5M10 2h4"/></svg>
                                Vence en <strong id="relojTiempo">{{ intdiv($restante, 60) }}:{{ str_pad($restante % 60, 2, '0', STR_PAD_LEFT) }}</strong>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Vista de la tarjeta: solo decorativa, se actualiza con lo que se escribe --}}
                <div class="plastico" aria-hidden="true">
                    <div class="plastico__arriba">
                        <span class="chip"></span>
                        <span class="red" id="vistaRed">VISA</span>
                    </div>
                    <div class="plastico__numero" id="vistaNumero">•••• •••• •••• ••••</div>
                    <div class="plastico__abajo">
                        <div><small>Titular</small><div class="plastico__titular" id="vistaTitular">Nombre Apellido</div></div>
                        <div><small>Vence</small><div id="vistaVence">MM/AA</div></div>
                    </div>
                </div>

                <div class="error" id="error" role="alert" @if (!$errors->any()) hidden @endif>
                    <svg><use href="#alerta"/></svg>
                    <span id="errorTexto">{{ $errors->first() }}</span>
                </div>

                <form method="POST" action="{{ route('pasarela-simulada.decidir', $orden) }}" id="formPago" novalidate>
                    @csrf
                    <input type="hidden" name="accion" value="pagar">

                    <div class="datos-tarjeta">
                        <span>Datos de la tarjeta</span>
                        <button type="button" class="autollenar" id="autollenar">Llenado automático</button>
                    </div>

                    <div class="campo">
                        <label for="titular">Nombre en la tarjeta</label>
                        <input id="titular" name="titular" autocomplete="cc-name" placeholder="Como aparece en la tarjeta"
                               value="{{ old('titular') }}" maxlength="80" required>
                    </div>

                    <div class="campo">
                        <label for="numero">Número de tarjeta</label>
                        <div class="con-icono">
                            <input id="numero" name="numero" inputmode="numeric" autocomplete="cc-number"
                                   placeholder="1234 1234 1234 1234" value="{{ old('numero') }}" maxlength="23" required>
                            <span id="marcaCampo">VISA</span>
                        </div>
                    </div>

                    <div class="fila">
                        <div class="campo">
                            <label for="vence">Vencimiento</label>
                            <input id="vence" name="vence" inputmode="numeric" autocomplete="cc-exp"
                                   placeholder="MM/AA" value="{{ old('vence') }}" maxlength="5" required>
                        </div>
                        <div class="campo">
                            <label for="cvv">CVV</label>
                            <input id="cvv" name="cvv" inputmode="numeric" autocomplete="cc-csc"
                                   placeholder="123" maxlength="4" required>
                        </div>
                    </div>

                    <button type="submit" class="pagar">
                        <svg><use href="#candado"/></svg> Pagar
                        <span class="pagar__separador" aria-hidden="true"></span>
                        <span class="pagar__monto">{{ $total }}</span>
                    </button>
                </form>

                <form method="POST" action="{{ route('pasarela-simulada.decidir', $orden) }}">
                    @csrf
                    <button class="cancelar" name="accion" value="cancelar">Cancelar y volver a la tienda</button>
                </form>
            </div>
        </main>

        <div class="pie"><svg><use href="#candado"/></svg> Pago cifrado · simulación para pruebas</div>
    </div>

    <div class="procesando" id="procesando" role="status" aria-live="polite">
        <div>
            <div class="girando"></div>
            <strong>Procesando pago…</strong>
            <span>Estamos confirmando con tu banco. No cierres esta ventana.</span>
        </div>
    </div>

    <script>
    (() => {
        const PRUEBA = @json(['numero' => $tarjetaPrueba['numero'], 'vence' => $tarjetaPrueba['vence'], 'cvv' => $tarjetaPrueba['cvv']]);
        const form = document.getElementById('formPago');
        const titular = document.getElementById('titular');
        const numero = document.getElementById('numero');
        const vence = document.getElementById('vence');
        const cvv = document.getElementById('cvv');
        const error = document.getElementById('error');
        const errorTexto = document.getElementById('errorTexto');
        const procesando = document.getElementById('procesando');
        const soloDigitos = (texto) => texto.replace(/\D/g, '');

        // ---- Formato mientras se escribe o se pega ----
        function marca(digitos) {
            if (/^4/.test(digitos)) return 'VISA';
            if (/^(5[1-5]|2[2-7])/.test(digitos)) return 'MASTERCARD';
            if (/^3[47]/.test(digitos)) return 'AMEX';
            return 'TARJETA';
        }

        function pintar() {
            const digitos = soloDigitos(numero.value);
            const relleno = (digitos + '•'.repeat(16)).slice(0, Math.max(16, digitos.length));
            document.getElementById('vistaNumero').textContent = relleno.replace(/(.{4})(?=.)/g, '$1 ');
            document.getElementById('vistaTitular').textContent = titular.value.trim() || 'Nombre Apellido';
            document.getElementById('vistaVence').textContent = vence.value || 'MM/AA';
            document.getElementById('vistaRed').textContent = marca(digitos);
            document.getElementById('marcaCampo').textContent = digitos ? marca(digitos) : '';
        }

        numero.addEventListener('input', () => {
            numero.value = soloDigitos(numero.value).slice(0, 19).replace(/(.{4})(?=.)/g, '$1 ');
            pintar();
        });

        vence.addEventListener('input', (evento) => {
            let digitos = soloDigitos(vence.value).slice(0, 4);
            // "1" → espera; "13" no es mes → "01/3"
            if (digitos.length >= 2 && Number(digitos.slice(0, 2)) > 12) digitos = '0' + digitos;
            const borrando = evento.inputType === 'deleteContentBackward';
            vence.value = digitos.length > 2 || (digitos.length === 2 && !borrando)
                ? `${digitos.slice(0, 2)}/${digitos.slice(2, 4)}`
                : digitos;
            pintar();
        });

        cvv.addEventListener('input', () => { cvv.value = soloDigitos(cvv.value).slice(0, 4); });
        titular.addEventListener('input', pintar);

        [titular, numero, vence, cvv].forEach((campo) => campo.addEventListener('input', () => {
            campo.classList.remove('invalido');
        }));

        // ---- Revisión rápida antes de enviar (el servidor decide si la tarjeta es válida) ----
        function luhn(digitos) {
            let suma = 0;
            [...digitos].reverse().forEach((d, i) => {
                let n = Number(d);
                if (i % 2) { n *= 2; if (n > 9) n -= 9; }
                suma += n;
            });
            return digitos.length >= 13 && suma % 10 === 0;
        }

        function mostrarError(texto, campos) {
            errorTexto.textContent = texto;
            error.hidden = false;
            campos.forEach((campo) => campo.classList.add('invalido'));
            campos[0]?.focus();
        }

        form.addEventListener('submit', (evento) => {
            evento.preventDefault();
            error.hidden = true;

            const [mes, anio] = vence.value.split('/').map(Number);
            const invalidos = [
                !titular.value.trim() && titular,
                !luhn(soloDigitos(numero.value)) && numero,
                !(mes >= 1 && mes <= 12 && anio >= 0 && vence.value.length === 5) && vence,
                !/^\d{3,4}$/.test(cvv.value) && cvv,
            ].filter(Boolean);

            if (invalidos.length) {
                mostrarError(
                    invalidos.length === 1 && invalidos[0] === titular
                        ? 'Escribe el nombre que aparece en la tarjeta.'
                        : 'Los datos de la tarjeta no son correctos. Revisa el número, la fecha de vencimiento y el CVV.',
                    invalidos
                );
                return;
            }

            // Pantalla de "procesando" como en un pago real; luego se envía al servidor
            procesando.classList.add('activo');
            form.querySelector('.pagar').disabled = true;
            setTimeout(() => form.submit(), 1800);
        });

        // Si el servidor rechazó los datos, marcar los campos de la tarjeta
        if (!error.hidden) {
            [numero, vence, cvv].forEach((campo) => campo.classList.add('invalido'));
        }

        // ---- Tiempo para pagar: al llegar a cero se recarga y el servidor muestra "venció" ----
        const reloj = document.getElementById('reloj');
        if (reloj) {
            const fin = performance.now() + Number(reloj.dataset.restante) * 1000;
            const tiempo = document.getElementById('relojTiempo');

            const tic = () => {
                const segundos = Math.max(0, Math.ceil((fin - performance.now()) / 1000));
                tiempo.textContent = `${Math.floor(segundos / 60)}:${String(segundos % 60).padStart(2, '0')}`;
                reloj.classList.toggle('reloj--poco', segundos <= 60);

                if (segundos === 0) {
                    // si ya está procesando el pago, el servidor decide; si no, mostrar el aviso
                    if (!procesando.classList.contains('activo')) location.reload();
                    return;
                }
                setTimeout(tic, 250);
            };
            tic();
        }

        // ---- Llenado automático con la tarjeta de prueba (se "escribe" para que se vea) ----
        const autollenar = document.getElementById('autollenar');
        const TITULAR = @json($datos['titular'] ?? null) || 'Cliente de prueba';
        const esperar = (ms) => new Promise((listo) => setTimeout(listo, ms));
        const sinAnimacion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        async function escribir(campo, texto) {
            campo.value = '';
            campo.classList.remove('invalido');
            for (const letra of texto) {
                campo.value += letra;
                campo.dispatchEvent(new Event('input', { bubbles: true }));   // aplica el formato y pinta la tarjeta
                if (!sinAnimacion) await esperar(28);
            }
        }

        autollenar.addEventListener('click', async () => {
            autollenar.disabled = true;
            error.hidden = true;
            // Nombre de quien paga (un nombre y un apellido, lo manda Soirée al crear la orden)
            if (!titular.value.trim()) await escribir(titular, TITULAR);
            await escribir(numero, PRUEBA.numero);
            await escribir(vence, PRUEBA.vence.replace('/', ''));
            await escribir(cvv, PRUEBA.cvv);
            autollenar.disabled = false;
            form.querySelector('.pagar').focus({ preventScroll: true });   // sin bajar la página
        });

        // Al recargar o volver con "Atrás" el navegador restaura los campos sin avisar:
        // se vuelve a pintar la tarjeta y se quita la pantalla de "procesando"
        window.addEventListener('pageshow', (evento) => {
            // Copia guardada en memoria (se volvió con "Atrás" después de pagar o cancelar):
            // se pide de nuevo al servidor, que muestra si la orden ya se procesó o venció
            if (evento.persisted) {
                location.reload();
                return;
            }
            procesando.classList.remove('activo');
            form.querySelector('.pagar').disabled = false;
            pintar();
        });
        [titular, numero, vence].forEach((campo) => campo.addEventListener('change', pintar));
        setTimeout(pintar, 300);   // autocompletado del navegador

        pintar();
    })();
    </script>
</body>
</html>
