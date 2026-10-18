{{-- Imita la página EXTERNA de una pasarela (como la de PayPal). No usa el diseño de la app a propósito. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pasarela simulada</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
               padding: 24px 16px; background: #eef2f7; font-family: system-ui, -apple-system, "Segoe UI", sans-serif; color: #1c2430; }
        .tarjeta { width: 100%; max-width: 420px; background: #fff; border-radius: 14px; overflow: hidden;
                   box-shadow: 0 16px 40px rgba(20, 40, 80, .12); }
        .cabecera { background: #0f3d6e; color: #fff; padding: 18px 24px; font-weight: 700; display: flex; justify-content: space-between; }
        .cabecera small { font-weight: 400; opacity: .8; }
        .aviso { background: #fff6e0; color: #8a5a00; font-size: 13px; padding: 10px 24px; }
        .cuerpo { padding: 24px; }
        .monto { font-size: 34px; font-weight: 800; margin: 4px 0 20px; }
        dl { display: grid; grid-template-columns: auto 1fr; gap: 8px 16px; font-size: 14px; margin: 0 0 24px; }
        dt { color: #6b7686; } dd { margin: 0; text-align: right; word-break: break-all; }
        button { width: 100%; padding: 13px; font-size: 15px; font-weight: 600; border-radius: 10px; border: 0; cursor: pointer; margin-bottom: 10px; }
        .aprobar { background: #1a7f4b; color: #fff; }
        .rechazar { background: #fff; color: #b42318; border: 1.5px solid #f1c3bf; }
        .cancelar { background: none; color: #6b7686; margin: 0; }
    </style>
</head>
<body>
    <main class="tarjeta">
        <div class="cabecera">Pasarela simulada <small>{{ $orden }}</small></div>
        <div class="aviso">Entorno de pruebas: aquí no se cobra dinero real.</div>

        <div class="cuerpo">
            <div>Pagar a <strong>Eventos Masivos</strong></div>
            <div class="monto">{{ $datos['moneda'] }} {{ number_format($datos['monto'], 2) }}</div>

            <dl>
                <dt>Referencia</dt><dd>{{ $datos['referencia'] }}</dd>
                <dt>Estado</dt><dd>{{ $datos['estado'] }}</dd>
            </dl>

            <form method="POST" action="{{ route('pasarela-simulada.decidir', $orden) }}">
                @csrf
                <button class="aprobar" name="accion" value="aprobar">Aprobar pago</button>
                <button class="rechazar" name="accion" value="rechazar">Rechazar (fondos insuficientes)</button>
                <button class="cancelar" name="accion" value="cancelar">Cancelar y volver a la tienda</button>
            </form>
        </div>
    </main>
</body>
</html>
