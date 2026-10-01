# Project Rotor — Architecture

Documento público de arquitectura conceptual.

## Transactional flow

Inventario
→ Catálogo
→ Carrito
→ Checkout
→ Orden
→ Pasarela sandbox
→ Webhook
→ Confirmación
→ Conciliación

## Application layers

Prototipo E2 (implementado):
HTML / CSS / JavaScript estático y navegable en `web/`, con datos
sintéticos locales. Publicado en GitHub Pages.

Frontend previsto del producto:
Next.js / React / TypeScript. Planificado; todavía no incorporado
(`frontend/` está vacío).

Backend:
Laravel / PHP. Actualmente es un skeleton de Laravel 12, sin lógica
del dominio.

Persistence:
PostgreSQL.

- PostgreSQL es la decisión arquitectónica elegida
  (`docs/decisiones.md`, ADR-E2-001).
- El modelo está documentado en `docs/modelo-datos.md` y
  `docs/architecture/DATA_MODEL.md`.
- El skeleton Laravel todavía no contiene migraciones del dominio.
- La implementación efectiva de la persistencia corresponde a una
  entrega posterior; no hay una base PostgreSQL desplegada.

API contract:
OpenAPI 3.1 en `api/openapi.yaml`, validado y probado con mock (Prism).

Authentication:
Laravel Sanctum. Previsto; no instalado todavía.

Quality:
OpenAPI + Playwright. El contrato está validado; las pruebas E2E con
Playwright están previstas.

## Architectural principles

- API REST como frontera entre frontend y backend.
- Separación entre inventario, comercio y pagos.
- Ninguna credencial dentro del repositorio.
- Datos de desarrollo exclusivamente sintéticos o sanitizados.
- Pagos exclusivamente en sandbox.
- Procesamiento idempotente de eventos externos.
- La confirmación de pago se basa en eventos validados del proveedor,
  no únicamente en el retorno del navegador.
- El inventario solo se descuenta mediante una transición válida
  del flujo de orden.

## Order state machine

DRAFT
→ PENDING_PAYMENT
→ PAID
→ FULFILLED

Alternative states:

FAILED
EXPIRED
CANCELLED

> This document describes the public conceptual architecture of Project Rotor.
> It does not represent private infrastructure, production topology,
> internal hostnames or deployment details.
