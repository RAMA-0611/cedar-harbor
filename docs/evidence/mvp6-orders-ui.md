# MVP-6 — Interfaz administrativa de pedidos

Base `main`: `fa5ad04db541ad785c080e2ee88ec0a6deb3cc6a`. Backend intacto (`BACKEND_CHANGED=NO`).

## Contrato CRUD dividido

| Operación | Quién | Endpoint |
|---|---|---|
| CREATE | CUSTOMER | `POST /api/orders` (carrito → checkout → pedido) |
| READ list | ADMIN | `GET /api/admin/orders` |
| READ detail | ADMIN | `GET /api/admin/orders/{orderId}` |
| UPDATE | ADMIN | `PATCH /api/admin/orders/{orderId}` |
| CANCEL | ADMIN | `PATCH` con `{ "status": "CANCELLED" }` |
| DELETE | ADMIN | `DELETE /api/admin/orders/{orderId}` |

No se añadió `POST /api/admin/orders`. `/pedidos` no muestra «Nuevo pedido».

Playwright no forma parte de este incremento. El flujo se verificó con smoke E2E (navegador + PostgreSQL `rotor`).

## CREATE CUSTOMER

Se creó un usuario/perfil sintético `cliente.mvp6.<timestamp>@example.test`, login CUSTOMER, `POST /api/cart/items` (qty 1), `POST /api/checkout/validate` (`valid=true`), `POST /api/orders` con `Idempotency-Key`.

Resultado: **201**, `PENDING_PAYMENT`, `order_items=1`, cart `CONVERTED`, reservation `ACTIVE` con `order_id`, stock del producto **sin cambio** (35).

## Admin UI

| Capacidad | Evidencia |
|---|---|
| LIST | Pedido sintético visible por `order_number`. Sin UUID como identificador principal. |
| SEARCH | `order_number` y `Cliente Demo MVP6` → pedido correcto. |
| FILTER | `PENDING_PAYMENT` → pedido sintético. |
| Pagination | Controles usan `meta.page/per_page/total`. Dataset pequeño; hasPrevious/hasNext según meta. |
| VIEW | `GET` detail real. Ítems snapshot (`PR-CAB-3X10`, precios de línea). Totales backend, no recalculados. |
| UPDATE | Teléfono sintético y `PICKUP` → `COORDINATED_SHIPPING` + dirección. PostgreSQL coincidió. |
| Delivery PICKUP | Vuelta a Recogida: `delivery_address=NULL`. |
| Protected fields | `PATCH { "total": "1.00" }` → 422 `VALIDATION_ERROR`. Total original intacto. |
| DELETE guard | UI deshabilitada en `PENDING_PAYMENT`. API DELETE → 409 `INVALID_ORDER_STATE`. |
| CANCEL | Confirmación. `CANCELLED`, `cancelled_at` set, reservation `ACTIVE` → `RELEASED`, stock 35, ítems/totales sin cambio. |
| DELETE | Tras cancelar, 204. Order y `order_items` ausentes. Stock 35. |

## RESOURCE_IN_USE (frontend)

`orderDeleteErrorMessage()` mapea `details.reason`:

- `payments`
- `webhook_events`
- `stock_reservations`

e `INVALID_ORDER_STATE`. No se crearon pagos/webhooks en `rotor`. La protección backend sigue cubierta por PHPUnit.

## Cleanup

Se eliminaron únicamente IDs sintéticos MVP6 (reservation, cart_item, cart, customer, user) tras verificar marcadores `example.test`. Pedidos originales intactos.

## Conteos

PRE y POST:

| Tabla | Pre | Post |
|---|---|---|
| users | 2 | 2 |
| customers | 2 | 2 |
| carts | 3 | 3 |
| cart_items | 3 | 3 |
| stock_reservations | 3 | 3 |
| orders | 3 | 3 |
| order_items | 3 | 3 |
| payments | 0 | 0 |
| webhook_events | 0 | 0 |
| products | 12 | 12 |

Stock producto smoke: 35 = 35. Dashboard pedidos: 3.

`DEVELOPMENT_DB_CHANGED=NO` salvo el ciclo controlado, revertido.

## Calidad

| Gate | Resultado |
|---|---|
| lint / typecheck / build | PASS |
| PHPUnit | 156 / 1403 / 0 |
| `TEST_DATABASE` | `rotor_testing` |
| API routes | 38 |
| OpenAPI operations | 38 |
| Parity | PASS |
| Swagger `/docs` | 200 (suite DocsSmokeTest) |
| `BACKEND_CHANGED` | NO |
