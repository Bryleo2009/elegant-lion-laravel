# Booking Experience Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rediseñar la reserva pública y añadir validaciones inline accesibles sin cambiar las reglas de negocio.

**Architecture:** Mantener el componente Vue existente y extraer únicamente la validación de contacto a un módulo puro comprobable. Reemplazar el markup público y sus estilos, preservando el panel administrativo y el contrato API actual.

**Tech Stack:** Laravel 13, Vue 3, CSS, Lucide, Node test runner.

**Spec:** `docs/superpowers/specs/2026-10-29-booking-experience-design.md`

## Global Constraints

- Sin dependencias nuevas.
- Sin cambios de base de datos o reglas de disponibilidad.
- Panel administrativo fuera de alcance.
- Estados accesibles y layout responsive.

## Review Focus

- Nombre vacío, demasiado corto o con caracteres no alfabéticos debe mostrar un mensaje útil.
- Celular con espacios debe normalizarse; uno que no sea peruano de nueve dígitos debe rechazarse.
- Días sin horarios deben permitir cambiar de fecha sin bloquear el flujo.
- Peticiones lentas o fallidas no deben dejar un horario anterior seleccionado.
- La interfaz debe seguir siendo utilizable a 360 px y con teclado.

---

### Task 1: Validación de datos del cliente

**Files:**
- Create: `resources/js/bookingValidation.js`
- Create: `tests/js/bookingValidation.test.js`
- Modify: `package.json`

**Interfaces:**
- Produces: `normalizePhone(value)`, `validateBookingContact({ name, phone })`.

- [ ] Escribir pruebas fallidas para nombre y celular.
- [ ] Ejecutar `npm test` y verificar el fallo por módulo ausente.
- [ ] Implementar las funciones mínimas.
- [ ] Ejecutar `npm test` y verificar que pasan.

### Task 2: Flujo público y estados accesibles

**Files:**
- Modify: `resources/js/App.vue`

**Interfaces:**
- Consumes: `normalizePhone` y `validateBookingContact`.

- [ ] Integrar estados touched/submitted y errores derivados.
- [ ] Reemplazar el markup público por el flujo y resumen definidos en el spec.
- [ ] Mantener los endpoints y payload existentes.

### Task 3: Sistema visual responsive

**Files:**
- Modify: `resources/css/app.css`

**Interfaces:**
- Consume las clases públicas de `App.vue` sin modificar las clases administrativas.

- [ ] Implementar tokens, superficies y jerarquía del portal público.
- [ ] Añadir estados hover/focus/selected/error/loading y breakpoints.
- [ ] Ejecutar build y comprobar visualmente escritorio y móvil.

### Task 4: Verificación integral

**Files:**
- Verify: `tests/Feature/BookingTest.php`

- [ ] Ejecutar `npm test`.
- [ ] Ejecutar `npm run build`.
- [ ] Ejecutar `php artisan test`.
- [ ] Revisar la página renderizada y corregir regresiones visuales.

