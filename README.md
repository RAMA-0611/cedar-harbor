# Proyecto Rotor

Prototipo transaccional de comercio electrónico para inventario industrial.

**Prototipo E2:** https://rama-0611.github.io/cedar-harbor/web/

**Código fuente:** https://github.com/RAMA-0611/cedar-harbor

**Proyecto Rotor** es una iniciativa de software impulsada por el equipo **ByteBlade** para construir un flujo transaccional de comercio electrónico orientado a entornos industriales.

El repositorio contiene el trabajo colaborativo del proyecto. La organización aliada del sector industrial, ubicada en Casanare (Colombia), no se identifica públicamente por confidencialidad.

| Dato académico | Valor |
|---|---|
| Ruta | F — Comercio electrónico y pasarelas de pago |
| Equipo | ByteBlade |
| Grupo | 2 |
| Curso | Tecnologías Web |
| Periodo | 2026-B |

---

## Description

Proyecto Rotor conecta la disponibilidad de inventario con un catálogo web y un ciclo de compra digital, de modo que la intención de compra se traduzca en una orden consistente, un pago sandbox verificable y una conciliación auditable.

El alcance público del proyecto se centra en la capa transaccional: catálogo, carrito, checkout, órdenes, pasarela sandbox, webhooks y estados de orden.

---

## Problem

Una organización del sector industrial administra un volumen importante de materiales, repuestos, componentes, herramientas y equipos.

Parte de dicho inventario tiene valor comercial y puede publicarse mediante un catálogo web. Sin un flujo transaccional completo, la publicación comercial queda limitada a la consulta o a la solicitud de cotización, sin cerrar de forma confiable la cadena:

**inventario → publicación → intención de compra → compra → pago → conciliación**

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

## Project status

Estado al cierre de la Entrega 2:

| Componente | Estado |
|---|---|
| Prototipo HTML/CSS/JS (`web/`) | COMPLETADO para E2 |
| Catálogo (búsqueda, filtros, orden) | COMPLETADO en prototipo |
| Detalle de producto | COMPLETADO en prototipo |
| Carrito y checkout simulado | COMPLETADO en prototipo |
| Estados loading / empty / error | COMPLETADO |
| Contrato OpenAPI 3.1 (`api/openapi.yaml`) | COMPLETADO / VALIDADO |
| Mock del contrato con Prism | VALIDADO |
| Modelo de datos PostgreSQL | DOCUMENTADO |
| Backend Laravel (`backend/`) | SKELETON INICIAL |
| Persistencia del dominio | NO IMPLEMENTADA TODAVÍA |
| Frontend Next.js (`frontend/`) | PLANIFICADO / NO INCORPORADO TODAVÍA |
| Pasarela sandbox real | NO IMPLEMENTADA TODAVÍA |

El prototipo usa 12 productos sintéticos locales (`web/datos/ejemplo.json`), guarda el carrito en el navegador y simula el pago. No se conecta todavía con la API.

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

- **Backend API:** Laravel (PHP) — dominio, persistencia, pagos sandbox, webhooks. Hoy es un skeleton sin lógica del dominio.
- **Frontend:** Next.js / React — catálogo, carrito, checkout y experiencia de usuario. Planificado; la Entrega 2 usa el prototipo estático de `web/`.
- **Base de datos:** PostgreSQL, motor elegido para la arquitectura (ver `docs/decisiones.md`). La persistencia del dominio todavía no está implementada.
- **Autenticación API:** Laravel Sanctum (prevista).

Más detalle en [`docs/ARCHITECTURE.md`](./docs/ARCHITECTURE.md).

---

## Technology stack

### Entrega 2 (implementado o usado)

| Tecnología | Uso |
|---|---|
| HTML5, CSS3, JavaScript | Prototipo navegable y accesible (`web/`) |
| OpenAPI 3.1 | Contrato de la API (`api/openapi.yaml`) |
| Prism | Servidor mock del contrato |
| Redocly CLI, Spectral | Validación del contrato |
| PostgreSQL (diseño) | Modelo de datos documentado; sin base desplegada |
| Laravel 12 (skeleton) | Base del backend, sin migraciones del dominio |
| GitHub Pages | Publicación del prototipo |

### Arquitectura objetivo (planificada)

| Tecnología | Uso |
|---|---|
| Laravel 12 | Framework API / dominio |
| PostgreSQL | Persistencia transaccional |
| Next.js 16 | Aplicación web |
| React 19 | UI |
| TypeScript | Tipado estático |
| Tailwind CSS | Estilos |
| Laravel Sanctum | Autenticación API / sesión |
| Playwright | Pruebas E2E |

Next.js, Sanctum y Playwright todavía no están incorporados al repositorio.

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

## Repository structure

Estructura actual:

```text
.
├── README.md
├── LICENSE
├── .gitignore
├── .env.example            # Configuración de referencia, sin secretos
├── api/
│   └── openapi.yaml        # Contrato OpenAPI 3.1
├── backend/                # Skeleton Laravel 12
├── frontend/               # Reservado para la aplicación Next.js (vacío)
├── docs/
│   ├── ARCHITECTURE.md
│   ├── architecture/
│   │   └── DATA_MODEL.md   # Modelo técnico completo
│   ├── modelo-datos.md     # Modelo de datos de la Entrega 2
│   ├── modelo-datos.mmd    # Fuente Mermaid del diagrama
│   ├── modelo-datos.png    # Diagrama entidad-relación
│   ├── decisiones.md       # Decisiones de arquitectura (ADR-E2)
│   ├── evidence/           # Evidencias de validación
│   └── wireframes/         # Catálogo, detalle y checkout
├── tests/                  # Reservado para pruebas E2E
└── web/                    # Prototipo de la Entrega 2
    ├── index.html          # Catálogo
    ├── detalle.html        # Detalle de producto
    ├── checkout.html       # Carrito y checkout
    ├── css/
    ├── js/
    └── datos/              # 12 productos sintéticos
```

---

## Local setup

Para probar la Entrega 2 **no se necesita** el backend Laravel ni una base de datos.

Versiones con las que se verificaron estos pasos: Python 3.12.10, Node.js 22.23.2 y npm 10.9.8 (Git 2.51 en Windows). El skeleton del backend declara PHP `^8.2` y Laravel `^12.0`; en el equipo de verificación estaban PHP 8.2.12 y Composer 2.8.12, pero no se requieren para el prototipo.

### Prototipo

Requiere Python 3.

```bash
git clone https://github.com/RAMA-0611/cedar-harbor.git
cd cedar-harbor/web
python -m http.server 8091 --bind 127.0.0.1
```

En Windows, `cd cedar-harbor\web`.

Abrir http://127.0.0.1:8091/.

Estados de demostración (también enlazados en el pie de página):

| Estado | URL |
|---|---|
| Carga | http://127.0.0.1:8091/?state=loading |
| Sin resultados | http://127.0.0.1:8091/?state=empty |
| Error | http://127.0.0.1:8091/?state=error |

El parámetro `?state=` funciona también en `detalle.html` y `checkout.html`.

### Mock de la API

Requiere Node.js y npm. Desde la raíz del repositorio:

```bash
npx --yes @stoplight/prism-cli@5.16.0 mock api/openapi.yaml -p 4010
```

Ejemplo: http://127.0.0.1:4010/api/products. Los endpoints protegidos exigen la cookie de sesión `rotor_session` (el mock valida su presencia, no su valor).

> No uses credenciales, dumps ni secretos de entornos reales.

---

## API contract and validation

- Contrato: [`api/openapi.yaml`](./api/openapi.yaml) — OpenAPI 3.1.0, 7 grupos, 14 operaciones y 27 schemas.
- Evidencia: [`docs/evidence/e2-api-validation.md`](./docs/evidence/e2-api-validation.md) y captura [`docs/evidence/openapi-validation.png`](./docs/evidence/openapi-validation.png).

| Herramienta | Versión | Resultado |
|---|---|---|
| Redocly CLI | 2.57.0 | Válido, 0 errores |
| Spectral CLI | 6.16.3 | 0 errores, 0 warnings, 0 hints |
| Prism | 5.16.0 | Mock operativo |

Evidencias de accesibilidad del prototipo: [`docs/evidence/e2-lighthouse.md`](./docs/evidence/e2-lighthouse.md).

---

## Data model

- Modelo de la Entrega 2: [`docs/modelo-datos.md`](./docs/modelo-datos.md) y diagrama [`docs/modelo-datos.png`](./docs/modelo-datos.png).
- Decisiones: [`docs/decisiones.md`](./docs/decisiones.md).

PostgreSQL es el motor elegido para la arquitectura. El modelo está documentado (entidades, claves, restricciones, índices y cardinalidades), pero la persistencia del dominio todavía **no** está implementada: el skeleton Laravel no contiene migraciones del dominio y no hay una base de datos desplegada.

---

## Environment variables

[`.env.example`](./.env.example) contiene una configuración de referencia con placeholders (PostgreSQL local y pasarela sandbox sin claves). `backend/.env.example` es la plantilla propia del skeleton Laravel.

Usa **únicamente placeholders**. Nunca commits con valores reales. Cualquier archivo `.env` real debe permanecer fuera del control de versiones.

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

## Team — ByteBlade

| Persona | Rol | GitHub |
|---|---|---|
| Sebastián David Tojuelo Perilla | Líder técnico | [@SFrost156](https://github.com/SFrost156) |
| Harold Steven Alfonso Pérez | Backend y datos | [@Harold-Alfonso](https://github.com/Harold-Alfonso) |
| Johan David Rodríguez Pérez | Frontend y experiencia | [@johanrodriguezes-ui](https://github.com/johanrodriguezes-ui) |
| Raúl Alejandro Mogollón Acosta | DevOps y calidad | [@RAMA-0611](https://github.com/RAMA-0611) |

---

## Uso de inteligencia artificial

Durante el desarrollo se utilizaron herramientas de inteligencia artificial como apoyo para revisión técnica, análisis, documentación, validación y asistencia de programación. Las decisiones arquitectónicas, las pruebas, las revisiones de pull requests y la aceptación final fueron verificadas por los integrantes del equipo. No se delegó a la IA la responsabilidad académica del contenido entregado.

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

See [LICENSE](./LICENSE) for the complete terms. **All Rights Reserved.**
