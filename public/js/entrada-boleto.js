'use strict';

// /b#<código>: se abre al escanear el QR de un boleto con la cámara del teléfono.
//   - personal (admin u operador) → tarjeta para registrar la entrada (verificacion-boleto.js)
//   - dueño del boleto → sus boletos, con ese boleto a la vista
//   - cualquier otra persona o sin sesión → la cartelera (no se revela nada del boleto)

document.addEventListener('DOMContentLoaded', async () => {
    const codigo = decodeURIComponent(location.hash.slice(1));
    // El código no se queda en la barra de direcciones ni en el historial
    history.replaceState(null, '', location.pathname);

    const aTienda = () => location.replace('/');
    const token = localStorage.getItem('auth_token');
    if (!codigo || !token) {
        aTienda();
        return;
    }

    const aviso = document.getElementById('entradaAviso');
    let datos;
    try {
        datos = await VerificacionBoleto.consultar(codigo);
    } catch (error) {
        if (error.status === 401 || error.status === 403) {
            aTienda();
            return;
        }
        aviso.textContent = error.message;
        return;
    }

    if (datos.vista === 'propietario') {
        location.replace(`/mis-compras/${datos.reserva_id}/boletos#boleto-${datos.boleto_id}`);
        return;
    }
    if (datos.vista !== 'personal') {
        aTienda();
        return;
    }

    aviso.hidden = true;
    document.getElementById('entradaResultado').replaceChildren(VerificacionBoleto.tarjeta(datos));
    document.getElementById('entradaPie').hidden = false;
});
