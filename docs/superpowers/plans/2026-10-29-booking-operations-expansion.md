# Booking Operations Expansion Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Implementar configuración avanzada de reservas, PWA y mejoras administrativas solicitadas.

**Architecture:** Evolucionar el esquema preservando datos existentes. Centralizar la validación operativa en Laravel y consumir el nuevo contrato desde el componente Vue existente.

**Tech Stack:** Laravel, Eloquent, Vue 3, Vite, Service Worker, Web App Manifest.

**Spec:** `docs/superpowers/specs/2026-10-29-booking-operations-expansion-design.md`

## Tasks

1. Escribir pruebas de horizonte, bloques, rangos y eliminación segura.
2. Migrar `barbers`, `work_schedules` y `time_offs` preservando filas actuales.
3. Actualizar disponibilidad y endpoints administrativos.
4. Actualizar Horarios, Servicios y Clientes en Vue.
5. Añadir manifest, iconos, service worker e instalación desde la cabecera.
6. Ejecutar pruebas, build y revisión responsive.

