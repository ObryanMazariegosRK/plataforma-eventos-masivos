#!/usr/bin/env bash
set -e
LAYERS=("Domain" "Application" "Infrastructure" "Http")
MODULES=("Catalogo" "Recintos" "Auth" "ColaVirtual" "Inventario" "Reservas" "Pagos" "Pasarelas" "Reembolsos" "Boletos" "Notificaciones" "Transacciones" "Admin")
for l in "${LAYERS[@]}"; do
  for m in "${MODULES[@]}"; do
    mkdir -p "app/$l/$m"
    touch "app/$l/$m/.gitkeep"
  done
done
# Dominio compartido (Evento, Localidad, Asiento, Transaccion, Usuario)
mkdir -p "app/Domain/Shared"
touch "app/Domain/Shared/.gitkeep"
echo "OK (estructura por capas)"