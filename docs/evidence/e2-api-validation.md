# Evidencia E2 — Validación del contrato OpenAPI y mock server

| Campo | Valor |
|---|---|
| Fecha | 2026-10-01 |
| Contrato | `api/openapi.yaml` |
| Versión OpenAPI | `3.1.0` |
| Versión del contrato (`info.version`) | `0.1.0` |
| Issue | #2 |
| Responsable | Harold Steven Alfonso Pérez |

## 1. Alcance del contrato

- Recursos (tags): Catalog, Cart, Checkout, Orders, Payments, Webhooks, Reconciliation.
- Operaciones: 14.
- Schemas en `components.schemas`: 27.
- Forma de error común: `ApiError` con `code`, `message`, `details` (objeto, vacío si no hay detalle) y `trace_id` opcional.
- Códigos HTTP usados según aplique: 200, 201, 204, 400, 401, 403, 404, 409, 422, 500, 502.
- Seguridad declarada: `cookieAuth` (cookie `rotor_session`). El webhook no usa cookie; valida firma (`400 INVALID_WEBHOOK_SIGNATURE`).
- El contrato es agnóstico al motor de persistencia.
- Todos los ejemplos son sintéticos (SKU `ROTOR-*`, UUID de relleno, `trace-synth-*`, referencias `sandbox-ref-*`).

## 2. Validación estática

Las herramientas se ejecutaron con `npx` desde un directorio temporal fuera del repositorio, sin agregar dependencias al proyecto. `<repo>` representa la raíz del clon.

### Redocly CLI 2.57.0 (configuración `recommended` integrada)

```bash
npx --yes @redocly/cli@latest lint <repo>/api/openapi.yaml
```

Resultado: **válido, 0 errores, 1 warning**.

| Regla | Ubicación | Decisión |
|---|---|---|
| `no-server-example.com` | `servers[0].url` = `http://localhost:8000` | Aceptado. El contrato solo describe el entorno local de desarrollo; no se publican dominios reales. |

### Spectral CLI 6.16.3 (ruleset `spectral:oas`)

Ruleset temporal (`.spectral.yaml`, fuera del repositorio):

```yaml
extends: ["spectral:oas"]
```

```bash
npx --yes @stoplight/spectral-cli@latest lint <repo>/api/openapi.yaml --ruleset .spectral.yaml
npx --yes @stoplight/spectral-cli@6.16.3 lint <repo>/api/openapi.yaml --ruleset .spectral.yaml --fail-severity hint
```

Resultado: **0 errores, 0 warnings, 0 hints** (`No results with a severity of 'hint' or higher found!`).

### Revisión estructural complementaria

Sobre el bundle desreferenciado (`npx --yes @redocly/cli@2.57.0 bundle <repo>/api/openapi.yaml --dereferenced --ext json`) se verificó con un script local temporal que cada operación tiene `summary`, `description`, `tags`, ejemplo en `requestBody` cuando aplica, ejemplo en cada respuesta con contenido y `details` en todos los ejemplos de error. Resultado: sin hallazgos.

## 3. Mock server (Prism)

Comando, ejecutado desde la raíz del repositorio (Prism resuelto: 5.16.0):

```bash
npx --yes @stoplight/prism-cli@latest mock api/openapi.yaml -p 4010
```

Puerto: **4010** (libre al momento de la prueba). Servidor detenido al terminar.

### Endpoints probados

| # | Petición | Esperado | Obtenido |
|---|---|---|---|
| 1 | `GET /api/products` | 200, `ProductListResponse` | 200, `data` (incluye `ROTOR-BRG-6205`) y `meta` de paginación |
| 2 | `GET /api/products/11111111-1111-4111-8111-111111111111` | 200, `ProductSummary` | 200, producto `ROTOR-BRG-6205` |
| 3 | `GET /api/products/{productId}` con `Prefer: code=404` | 404, `ApiError` | 404, `RESOURCE_NOT_FOUND`, `details: {}` |
| 4 | `GET /api/products/not-a-uuid` | 400, `ApiError` | 400, `MALFORMED_REQUEST` (Prism: `productid must match format "uuid"`) |
| 5 | `GET /api/cart` con `Cookie: rotor_session=demo` | 200, `Cart` | 200, carrito con 2 unidades, total `107100.00` |
| 6 | `GET /api/cart` sin cookie | 401, `ApiError` | 401, `UNAUTHENTICATED` (Prism: `Invalid security scheme used`) |
| 7 | `POST /api/cart/items` con `quantity: 0` | 422, `ApiError` | 422 (Prism: `quantity must be >= 1`) |

### Limitaciones observadas

- **Prism simula el contrato, no la autenticación.** Solo comprueba que la cookie `rotor_session` esté presente; cualquier valor es aceptado. La validación real de sesión, la autorización (403) y la idempotencia corresponden al backend.
- Prism devuelve ejemplos estáticos: en la prueba 7 el código (422) es correcto, pero el cuerpo es el primer ejemplo 422 declarado (`INSUFFICIENT_STOCK`), no un mensaje construido a partir de la violación.
- Las respuestas que no se derivan de una violación del request (404, 409, 502) se obtienen con la cabecera `Prefer: code=<status>` o `Prefer: example=<nombre>`.

## 4. Confidencialidad

Se buscaron en `api/` y en este documento los términos de la lista de exclusión del proyecto (nombres empresariales, dominios internos, rangos IP privados, rutas de servidor y de estación de trabajo). Resultado: **0 coincidencias**.
