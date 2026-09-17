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

Frontend:
Next.js / React / TypeScript

Backend:
Laravel / PHP

Persistence:
MySQL

Authentication:
Laravel Sanctum

Quality:
OpenAPI + Playwright

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
