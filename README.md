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

### Estado actual — Entrega 3

| Componente | Estado |
|---|---|
| PostgreSQL | IMPLEMENTADO |
| Migraciones de dominio | IMPLEMENTADAS |
| Laravel API | FUNCIONAL |
| Sanctum | IMPLEMENTADO |
| ApiError | IMPLEMENTADO |
| Admin CRUD ×4 | IMPLEMENTADO |
| Catálogo público | IMPLEMENTADO |
| Carrito persistente | IMPLEMENTADO |
| Reservas de stock | IMPLEMENTADAS |
| Checkout | IMPLEMENTADO |
| Orden | IMPLEMENTADA hasta `PENDING_PAYMENT` |
| Idempotencia | IMPLEMENTADA |
| Documentación interactiva (`/docs`) | IMPLEMENTADA |
| Frontend Next.js | IMPLEMENTADO base |
| Login Sanctum | IMPLEMENTADO |
| Dashboard admin | IMPLEMENTADO |
| Usuarios UI CRUD | IMPLEMENTADO |
| Categorías UI CRUD | IMPLEMENTADO |
| Productos UI CRUD | PENDIENTE |
| Pedidos UI CRUD | PENDIENTE |
| Pasarela sandbox | PENDIENTE / issue #9 |
| Webhook | PENDIENTE / issue #9 |
| Conciliación | PENDIENTE / issue #9 |

API en `main`: **38** rutas (`Auth` 3 + `Catalog` 2 + `Admin CRUD` 20 + `Admin Orders` 4 + `Cart` 4 + `Checkout` 1 + `Orders` 4).

### Snapshot histórico — cierre Entrega 2

| Componente | Estado (E2) |
|---|---|
| Prototipo HTML/CSS/JS (`web/`) | COMPLETADO para E2 |
| Catálogo (búsqueda, filtros, orden) | COMPLETADO en prototipo |
| Detalle de producto | COMPLETADO en prototipo |
| Carrito y checkout simulado | COMPLETADO en prototipo |
| Estados loading / empty / error | COMPLETADO |
| Contrato OpenAPI 3.1 (`api/openapi.yaml`) | COMPLETADO / VALIDADO |
| Mock del contrato con Prism | VALIDADO |
| Modelo de datos PostgreSQL | DOCUMENTADO (diseño) |
| Backend Laravel / persistencia / Sanctum | aún no entregados en E2 (ver estado E3 arriba) |
| Frontend Next.js (`frontend/`) | PLANIFICADO en E2; IMPLEMENTADO base en E3 |
| Pasarela sandbox real | NO IMPLEMENTADA |

El prototipo E2 usa 12 productos sintéticos locales (`web/datos/ejemplo.json`) y simula el pago en el navegador. `web/` permanece como prototipo histórico; `frontend/` es la aplicación MVP integrada (Next.js + Sanctum). La API Laravel de Entrega 3 es el camino vigente hasta orden `PENDING_PAYMENT` (pagos sandbox en #9).

---

## Entrega 3 — MVP backend

Documentación interactiva (fuente única `api/openapi.yaml`):

- UI: [http://127.0.0.1:8000/docs](http://127.0.0.1:8000/docs)
- YAML servido: [http://127.0.0.1:8000/docs/openapi.yaml](http://127.0.0.1:8000/docs/openapi.yaml)

### Requisitos

- PHP 8.2+
- Composer
- PostgreSQL 16
- Node.js solo donde aplique (herramientas OpenAPI opcionales vía `npx`)

### Setup backend

```bash
cd backend
composer install
copy .env.example .env
php artisan key:generate
```

Configurar PostgreSQL local `rotor` en `.env` (no versionar passwords):

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_DATABASE=rotor
DB_USERNAME=rotor
DB_PASSWORD=
```

```bash
php artisan migrate:fresh --seed
php artisan serve
```

Abrir `http://127.0.0.1:8000/docs`.

### Setup frontend (MVP)

`web/` es el prototipo E2. El frontend vigente está en `frontend/`.

```bash
cd frontend
copy .env.example .env.local
npm ci
npm run dev
```

Abrir `http://127.0.0.1:3000`. Requiere la API en `http://127.0.0.1:8000`. No versionar contraseñas.

Evidencia: [`docs/evidence/mvp3-frontend-auth-dashboard.md`](./docs/evidence/mvp3-frontend-auth-dashboard.md).

### Testing

PHPUnit usa PostgreSQL `rotor_testing`, **nunca** `rotor`.

```powershell
# crear rotor_testing como superuser local (ver docs/operations/TEST_DATABASE.md)
cd backend
copy .env.testing.example .env.testing
php artisan test
```

La suite aborta con `Refusing to run tests against non-testing database.` si el entorno apunta a `rotor` u otra base. Detalle: [`docs/operations/TEST_DATABASE.md`](./docs/operations/TEST_DATABASE.md).

### Endpoints E3 disponibles en main

**Auth**

- `POST /api/auth/login`
- `POST /api/auth/logout`
- `GET /api/auth/me`

**Catalog**

- `GET /api/products`
- `GET /api/products/{id}`

**Admin CRUD** (`auth:sanctum` + `role:ADMIN`)

- `/api/admin/categories`
- `/api/admin/products`
- `/api/admin/customers`
- `/api/admin/users`
- `GET/PATCH/DELETE /api/admin/orders` (sin `POST` admin; CREATE es `POST /api/orders`)

**Cart** (`auth:sanctum` + rol `CUSTOMER` con perfil)

- `GET /api/cart`
- `POST /api/cart/items`
- `PATCH /api/cart/items/{cartItemId}`
- `DELETE /api/cart/items/{cartItemId}`

**Checkout** (`auth:sanctum` + `CUSTOMER`)

- `POST /api/checkout/validate`

**Orders** (`auth:sanctum` + `CUSTOMER`)

- `POST /api/orders` (requiere `Idempotency-Key`; éxito → `PENDING_PAYMENT`)
- `GET /api/orders/{orderId}`
- `GET /api/orders/{orderId}/status`
- `POST /api/orders/{orderId}/cancel`

**Total API:** 38 rutas / 38 operaciones OpenAPI.

No se afirma transición a `PAID` ni pasarela sandbox en E3 (issue #9).

### Credenciales

No hay password demo fijado en seed.

Ejemplos sintéticos de identidad (no garantizan existencia en seed):

- `admin.demo@example.test`
- `customer.demo@example.test`

Crear usuarios reales con factories/admin API o seeders locales no versionados.

### Evidencia

- [`docs/evidence/e3-backend-validation.md`](./docs/evidence/e3-backend-validation.md)
- [`docs/evidence/e3-demo-script.md`](./docs/evidence/e3-demo-script.md)

### MVP audit

Auditoría técnica de `v0.3.0` (sin cambios funcionales): [`docs/audits/MVP0_REQUIREMENTS_MATRIX.md`](./docs/audits/MVP0_REQUIREMENTS_MATRIX.md).

Backup/restore PostgreSQL local: [`docs/operations/DATABASE_BACKUP_RESTORE.md`](./docs/operations/DATABASE_BACKUP_RESTORE.md).


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

- **Backend API:** Laravel 12 (PHP) — PostgreSQL, Sanctum, ApiError, catálogo, CRUD admin, carrito/reservas, checkout, órdenes hasta `PENDING_PAYMENT` e idempotencia. Pagos sandbox / webhooks / conciliación pendientes (issue #9).
- **Frontend:** Next.js 16 / React 19 en `frontend/` (login Sanctum, shell ADMIN, dashboard real). `web/` es el prototipo histórico E2.
- **Base de datos:** PostgreSQL local `rotor` con migraciones de dominio aplicadas (ver `docs/decisiones.md`).
- **Autenticación API:** Laravel Sanctum (sesión SPA / cookie).

Más detalle en [`docs/ARCHITECTURE.md`](./docs/ARCHITECTURE.md).

---

## Technology stack

### Entrega 2 (histórico)

| Tecnología | Uso |
|---|---|
| HTML5, CSS3, JavaScript | Prototipo navegable (`web/`) |
| OpenAPI 3.1 | Contrato de la API (`api/openapi.yaml`) |
| Prism | Servidor mock del contrato |
| Redocly CLI, Spectral | Validación del contrato |
| GitHub Pages | Publicación del prototipo |

### Entrega 3 (implementado actualmente)

| Tecnología | Uso |
|---|---|
| Laravel 12 | Framework API / dominio |
| PostgreSQL | Persistencia transaccional |
| Laravel Sanctum | Autenticación API / sesión |
| Swagger UI | Documentación interactiva en `/docs` |
| BCMath | Cálculos monetarios exactos (HALF_UP) |
| PHPUnit | Suite Feature del backend |
| Next.js 16 / React 19 | Aplicación administrativa en `frontend/` |
| TypeScript / Tailwind CSS | Tipado y estilos del frontend |

### Pendiente (siguientes gates)

| Tecnología | Uso |
|---|---|
| Pasarela sandbox / webhooks / conciliación | Issue #9 |
| CRUD visual admin | Productos, pedidos |
| Playwright | Pruebas E2E |

Playwright todavía no está incorporado al repositorio.

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
├── backend/                # Laravel 12 API (Auth, Catalog, Admin CRUD, Cart)
├── frontend/               # Aplicación Next.js MVP (auth + dashboard)
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
└── web/                    # Prototipo histórico de la Entrega 2
    ├── index.html          # Catálogo
    ├── detalle.html        # Detalle de producto
    ├── checkout.html       # Carrito y checkout
    ├── css/
    ├── js/
    └── datos/              # 12 productos sintéticos
```

---

## Local setup

### Aplicación MVP (frontend + API)

```bash
# Terminal 2 — API
cd backend
php artisan serve --host=127.0.0.1 --port=8000

# Terminal 3 — Next.js
cd frontend
npm ci
npm run dev
```

PostgreSQL debe estar disponible (terminal 1). Abrir http://127.0.0.1:3000.

### Prototipo E2

Para probar la Entrega 2 **no se necesita** el backend Laravel ni una base de datos.

Versiones con las que se verificaron estos pasos: Python 3.12.10, Node.js 22.23.2 y npm 10.9.8 (Git 2.51 en Windows). El backend declara PHP `^8.2` y Laravel `^12.0`; en el equipo de verificación estaban PHP 8.2.12 y Composer 2.8.12. El prototipo E2 no requiere PHP ni PostgreSQL.

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

### Contrato actual — Entrega 3

- Fuente única: [`api/openapi.yaml`](./api/openapi.yaml)
- **38** operaciones implementadas (paridad con URIs `api/`)
- Tags activos: Auth, Catalog, Admin*, Cart, Checkout, Orders
- Endpoints de payment-intent / webhook / reconcile **no** forman parte del contrato activo E3 (reservados a #9; históricos en tag `v0.2.0`)

### Contrato histórico — Entrega 2

- Tag `v0.2.0`: contrato E2 con **14** operaciones (prototipo + mock Prism)
- Evidencia E2: [`docs/evidence/e2-api-validation.md`](./docs/evidence/e2-api-validation.md) y [`docs/evidence/openapi-validation.png`](./docs/evidence/openapi-validation.png)

| Herramienta | Versión | Uso |
|---|---|---|
| Redocly CLI | 2.57.0 | Lint del contrato actual |
| Spectral CLI | 6.16.3 | Lint del contrato actual |
| Prism | 5.16.0 | Mock histórico E2 |

Evidencias de accesibilidad del prototipo E2: [`docs/evidence/e2-lighthouse.md`](./docs/evidence/e2-lighthouse.md).

---

## Data model

- Modelo de la Entrega 2: [`docs/modelo-datos.md`](./docs/modelo-datos.md) y diagrama [`docs/modelo-datos.png`](./docs/modelo-datos.png).
- Decisiones: [`docs/decisiones.md`](./docs/decisiones.md).

PostgreSQL es el motor de persistencia. El modelo está documentado (entidades, claves, restricciones, índices y cardinalidades) y las migraciones de dominio están aplicadas en el backend Laravel de Entrega 3. Checkout, órdenes y pagos sandbox siguen pendientes.

---

## Environment variables

[`.env.example`](./.env.example) contiene una configuración de referencia con placeholders (PostgreSQL local y pasarela sandbox sin claves). `backend/.env.example` es la plantilla del backend Laravel. `frontend/.env.example` solo declara `NEXT_PUBLIC_API_BASE_URL`.

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
