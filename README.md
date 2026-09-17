# Proyecto Rotor

**Proyecto Rotor** es una iniciativa de software impulsada por el equipo **ByteBlade** para construir un flujo transaccional de comercio electrónico orientado a entornos industriales.

El repositorio contiene el trabajo colaborativo del proyecto. La organización aliada del sector industrial, ubicada en Casanare (Colombia), no se identifica públicamente por confidencialidad.

---

## Description

Proyecto Rotor conecta la disponibilidad de inventario con un catálogo web y un ciclo de compra digital, de modo que la intención de compra se traduzca en una orden consistente, un pago sandbox verificable y una conciliación auditable.

El alcance público del proyecto se centra en la capa transaccional: catálogo, carrito, checkout, órdenes, pasarela sandbox, webhooks y estados de orden.

---

## Problem

Una organización del sector industrial administra un volumen importante de materiales, repuestos, componentes, herramientas y equipos.

Parte de dicho inventario tiene valor comercial y puede publicarse mediante un catálogo web. Sin un flujo transaccional completo, la publicación comercial queda limitada a la consulta o a la solicitud de cotización, sin cerrar de forma confiable la cadena:

**inventario → intención de compra → pago → confirmación → conciliación**

Proyecto Rotor aborda esa brecha.

---

## Objective

Desarrollar un flujo transaccional de comercio electrónico que conecte:

```text
Inventario
  → Catálogo
  → Carrito
  → Checkout
  → Orden
  → Pasarela de pago (sandbox)
  → Webhook
  → Confirmación
  → Conciliación
```

El sistema debe mantener consistencia entre stock, orden y estado del pago, con validaciones, reservas temporales e idempotencia en los puntos críticos.

---

## Transactional flow

Máquina de estados prevista para las órdenes:

```text
DRAFT
  → PENDING_PAYMENT
  → PAID
  → FULFILLED

Ramas alternativas:
  FAILED
  EXPIRED
  CANCELLED
```

Principios del flujo:

1. El catálogo refleja disponibilidad validable.
2. El carrito persiste la intención de compra.
3. El checkout crea la orden de forma transaccional.
4. Los precios relevantes quedan fijados (snapshot) al momento de comprar.
5. El pago opera **exclusivamente en sandbox**.
6. Los webhooks llegan firmados y se procesan de forma idempotente.
7. La conciliación cierra el ciclo entre pasarela, orden y stock.

---

## Conceptual architecture

Vista conceptual (no representa topología de servidores reales):

```text
┌─────────────┐     ┌──────────────┐     ┌────────────┐
│  Inventario │────▶│   Catálogo   │────▶│   Carrito  │
└─────────────┘     └──────────────┘     └─────┬──────┘
                                               │
                                               ▼
┌─────────────┐     ┌──────────────┐     ┌────────────┐
│ Conciliación│◀────│   Webhook    │◀────│  Checkout  │
└─────────────┘     └──────────────┘     └─────┬──────┘
                                               │
                                               ▼
                                        ┌────────────┐
                                        │   Orden    │
                                        └─────┬──────┘
                                              │
                                              ▼
                                   ┌────────────────────┐
                                   │ Pasarela (sandbox) │
                                   └────────────────────┘
```

- **Backend API:** Laravel (PHP) — dominio, persistencia, pagos sandbox, webhooks.
- **Frontend:** Next.js / React — catálogo, carrito, checkout y experiencia de usuario.
- **Base de datos:** MySQL.
- **Autenticación API:** Laravel Sanctum.

---

## Planned features

Incluye (alcance previsto del repositorio):

- Catálogo de productos
- Carrito persistente
- Validación de disponibilidad
- Reserva temporal de inventario
- Checkout
- Creación transaccional de órdenes
- Snapshot de precios al comprar
- Integración con pasarela **exclusivamente en sandbox**
- Webhooks firmados
- Procesamiento idempotente
- Máquina de estados de órdenes
- Conciliación
- Pruebas automatizadas E2E
- Documentación OpenAPI

### Out of scope

Este repositorio **no** incluye:

- Pagos reales
- Credenciales reales
- Datos productivos
- Facturación electrónica
- Integración con ERP de terceros
- Datos internos de la empresa aliada
- Infraestructura privada
- Logística de transportadoras
- Información que permita identificar a la organización

---

## Technology stack

### Backend

| Tecnología | Uso |
|------------|-----|
| PHP 8.2+ | Runtime |
| Laravel 12 | Framework API / dominio |
| MySQL | Persistencia |
| Laravel Sanctum | Autenticación API / sesión |

### Frontend

| Tecnología | Uso |
|------------|-----|
| Next.js 16 | Aplicación web |
| React 19 | UI |
| TypeScript | Tipado estático |
| Tailwind CSS | Estilos |
| Zustand | Estado cliente |
| Zod | Validación de esquemas |

### Quality

| Herramienta | Uso |
|-------------|-----|
| OpenAPI | Contrato de API |
| Playwright | Pruebas E2E |
| GitHub Pull Requests | Revisión colaborativa |
| Pruebas automatizadas | Regresión y aceptación |

---

## Repository structure

Estructura prevista (se irá materializando conforme avance el proyecto):

```text
.
├── README.md
├── LICENSE
├── backend/          # API Laravel
├── frontend/         # Aplicación Next.js
├── docs/             # Documentación técnica y OpenAPI
└── tests/            # Suites E2E / integración (según se definan)
```

Mientras el repositorio esté en fase inicial, pueden existir solo los archivos de presentación y licencia.

---

## Local setup

Los pasos concretos de instalación se documentarán cuando el código base esté presente en el repositorio.

Orientación general prevista:

1. Clonar este repositorio.
2. Configurar backend (PHP/Composer, Laravel, MySQL).
3. Configurar frontend (Node.js, npm, Next.js).
4. Completar variables de entorno con valores **locales / sandbox**.
5. Ejecutar migraciones y seeds **sintéticos** (nunca datos productivos).
6. Arrancar API y frontend en desarrollo.
7. Ejecutar la suite de pruebas automatizadas.

> No uses credenciales, dumps ni secretos de entornos reales.

---

## Environment variables

Usa **únicamente placeholders**. Nunca commits con valores reales.

```env
APP_URL=
API_URL=
DB_HOST=
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=
SANCTUM_STATEFUL_DOMAINS=
PAYMENT_PROVIDER=
PAYMENT_PUBLIC_KEY=
PAYMENT_SECRET_KEY=
PAYMENT_WEBHOOK_SECRET=
```

Cualquier archivo `.env` real debe permanecer fuera del control de versiones.

---

## Security and confidentiality

This public repository must never contain:

- production credentials;
- real environment files;
- API secrets;
- private certificates;
- production database dumps;
- personal information;
- internal addresses;
- infrastructure topology;
- private company documentation.

Only synthetic or sanitized test data may be committed.

Additionally (project policy):

- Payments integrations in this repository are **sandbox-only**.
- Do not publish internal hostnames, private IPs, or deployment topology.
- Do not include identifiable organization names, customer records, or employee data.

---

## Project status

| Aspecto | Estado |
|---------|--------|
| Identidad pública y licencia | En curso (este repositorio) |
| Código de aplicación | Pendiente de incorporación en este repositorio |
| Flujo transaccional completo | Previsto |
| Pasarela | Solo sandbox (previsto) |
| OpenAPI + E2E | Previsto |

El estado se actualizará a medida que se fusionen pull requests.

---

## Team — ByteBlade

| Persona | Rol |
|---------|-----|
| Sebastián David Tojuelo Perilla | Líder técnico |
| Harold Steven Alfonso Pérez | Backend y datos |
| Johan David Rodríguez Pérez | Frontend y experiencia |
| Raúl Alejandro Mogollón Acosta | DevOps y calidad |

---

## Contributing

1. Trabaja mediante **pull requests** hacia la rama principal acordada.
2. Describe el cambio, el riesgo y cómo se probó.
3. No incluyas secretos, dumps ni datos personales.
4. Prefiere datos sintéticos en fixtures y demos.
5. Mantén alineados OpenAPI, pruebas y comportamiento real.
6. Respeta el alcance: sin pagos reales ni integraciones ERP de terceros en este repositorio.

---

## License and usage

This repository is publicly accessible for transparency, educational review and collaboration. Public availability does not constitute an open-source license.

Unless expressly authorized in writing, reproduction, modification, distribution, commercial use, deployment or incorporation of this software into another product is not permitted.

See [LICENSE](./LICENSE) for the complete terms.

---

## License

See the [LICENSE](./LICENSE) file. **All Rights Reserved.**
