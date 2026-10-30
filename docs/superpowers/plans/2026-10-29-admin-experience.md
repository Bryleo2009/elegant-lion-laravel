# Admin Experience Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Mejorar navegación, legibilidad móvil y priorización temporal de la agenda administrativa.

**Architecture:** Extraer la clasificación y orden de citas a un módulo JavaScript puro. Mantener el componente Vue y aplicar un tema claro limitado a `.admin-page`, sin afectar la reserva pública.

**Tech Stack:** Vue 3, JavaScript, CSS, Node test runner.

**Spec:** `docs/superpowers/specs/2026-10-29-admin-experience-design.md`

## Tasks

1. Crear pruebas para estados `upcoming`, `past` y `terminal`, y para el orden visual.
2. Implementar el clasificador puro e integrarlo como propiedad computada.
3. Rediseñar navegación, contenido, métricas, formularios y tarjetas con tema claro responsive.
4. Verificar pruebas, build, agenda en escritorio y agenda en móvil.

