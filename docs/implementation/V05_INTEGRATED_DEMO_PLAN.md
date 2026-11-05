# V0.5 — Plan técnico (auditoría dirigida)

**Base:** `18a5fed` / tag `v0.4.0`  
**Rama:** `feat/v05-integrated-storefront-wompi`  
**Issue paraguas:** #44 · **Sandbox:** #9 (`estado:en-curso`)

## Evidencia de auditoría (una pasada)

| Área | Hallazgo |
|------|----------|
| `routes/api.php` | Auth, catalog GET, admin CRUD, cart/checkout/orders CUSTOMER. **Sin** payment-intent / webhook / image. |
| `Product` / resources | Campos metadata OK; sin `image_path` / `image_url`; `ProductSummary` solo id/sku/name/price/currency/qty/tax. |
| `CatalogController` | Filtra `is_active` + categoría activa; sin eager `category`. |
| Cart/Checkout/Order | Flujo real Sanctum CUSTOMER + reservas + idempotency. Reutilizable. |
| `Payment` / `WebhookEvent` | Tablas existentes; `provider_reference` único parcial; falta merchant vs transaction id. |
| Frontend `/` | Redirige a login/dashboard — sustituir por catálogo público. |
| Admin | `(admin)/*` con `RequireAdmin` — no romper. |
| `web/` | E2 histórico JSON/localStorage — **inmutable**. |
| OpenAPI | `api/openapi.yaml` — ampliar ProductSummary + nuevos paths. |

## Macro-Gate A — Storefront + sync + imágenes

1. `/` = catálogo público (`cache: no-store` + refetch on focus).  
2. Rutas: `/producto/[id]`, `/cliente/login`, `/carrito`, `/checkout`, `/pedido/[id]`, `/pago/resultado`.  
3. Extender `ProductSummary` (category, description, brand, condition, unit, image_url).  
4. Migración `image_path`; `POST/DELETE` admin image; `storage:link`; placeholders SVG.  
5. CUSTOMER login / cart / checkout / order → visible en ADMIN.

## Macro-Gate B — Wompi sandbox

1. `.env.example` placeholders (sin secretos).  
2. Migración payments: `merchant_reference`, `provider_transaction_id`, `last_reconciled_at`.  
3. `POST /api/orders/{id}/payment-intent` (integridad SHA256 en backend).  
4. Web Checkout form → sandbox.  
5. `POST /api/webhooks/payments/wompi` (firma dinámica + idempotencia).  
6. `PaymentSettlementService` único (webhook + reconcile).  
7. Exactly-once stock; DECLINED/ERROR release.  
8. LIVE_SANDBOX = BLOCKED_CREDENTIALS si no hay llaves locales.

## Macro-Gate C

Scripts `demo-start/stop/status`, docs evidencia/explicación, E2E sync, quality gates una vez, 3 commits, 1 PR, no merge/tag.

## Restricciones

- No tocar tags históricos ni `web/`.  
- No secretos en repo/logs.  
- Tests HTTP Wompi mockeados; suite sin internet.
