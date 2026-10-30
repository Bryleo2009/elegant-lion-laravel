# Booking Operations Expansion

## Intent

Ampliar la operación diaria sin perder historial ni seguridad: horizonte por barbero, jornadas partidas, ausencias por rango, eliminación segura de servicios e instalación de la reserva pública como PWA.

## Decisiones

- `booking_days_ahead` representa la cantidad de días calendario seleccionables incluyendo hoy; valor inicial: 3.
- Un día puede tener de cero a cuatro bloques horarios, sin solapamientos.
- Los bloqueos usan fecha inicial y final inclusivas; una sola fecha sigue siendo válida.
- No se permite bloquear un rango con citas confirmadas.
- Los servicios sin citas se eliminan; los usados conservan historial y deben desactivarse.
- La PWA solo ofrece caché offline para la reserva pública y recursos estáticos. API y administrador permanecen network-only.
- Clientes se presenta como directorio responsive con información y acciones claramente separadas.

