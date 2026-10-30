# Booking Experience Redesign

## Intent

Convertir la reserva pública de Elegant Lion en una experiencia profesional, clara y confiable. La identidad seguirá siendo sobria, masculina y editorial, pero la interfaz debe priorizar la tarea de reservar sobre el logotipo y la decoración.

## Objetivos

- Reducir la escala del hero y dar protagonismo al formulario.
- Mantener un flujo de tres pasos con progreso, contexto y navegación hacia atrás.
- Mostrar servicio, profesional, fecha, hora y precio en un resumen persistente.
- Añadir validación inline de nombre y celular, estados de foco, error, carga y vacío.
- Mantener la validación final y la disponibilidad en Laravel.
- Funcionar correctamente en escritorio, tablet y móvil, con áreas táctiles de al menos 44 px.

## No objetivos

- No rediseñar el back office en este cambio.
- No cambiar las reglas de negocio, precios, horarios o esquema de base de datos.
- No agregar dependencias visuales ni animaciones decorativas pesadas.

## Enfoques considerados

1. Mantener el split 50/50 y reducir tipografías: cambio rápido, pero conserva el problema de que la marca compite con la reserva.
2. Portal editorial con panel de reserva dominante y bloque de marca secundario: elegido porque conserva la personalidad y mejora jerarquía, lectura y conversión.
3. Formulario blanco minimalista sin hero: muy claro, pero elimina demasiado carácter de marca.

## Decisiones de diseño

- Fondo carbón, superficies marfil y acento latón con contraste AA en texto esencial.
- Sans serif para controles y lectura; serif únicamente para titulares breves.
- Tarjetas de selección completas con estados hover, focus y seleccionado.
- Resumen visible en escritorio y compacto dentro del flujo en móvil.
- Errores junto al campo, con `aria-invalid`, `aria-describedby` y anuncio global solo para fallos del servidor.
- La selección inicial de servicio y barbero se conserva para reducir fricción; el usuario siempre puede cambiarla.

