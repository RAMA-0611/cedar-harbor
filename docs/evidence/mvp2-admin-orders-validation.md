# MVP-2 — Admin orders validation

## Decisiones

- **CREATE** permanece `POST /api/orders` (CUSTOMER). No hay `POST /api/admin/orders`.
- **ADMIN:** `GET` list/detail, `PATCH` campos de contacto/entrega + `status=CANCELLED`, `DELETE` solo `CANCELLED`.
- Cancelación ADMIN reutiliza `OrderService::applyCancellation` (idempotente si ya `CANCELLED`).
- Sin migración nueva. `order_items.order_id` es `restrictOnDelete`; el delete transaccional borra items y luego la orden.
- `payments.order_id` es `restrictOnDelete`; `webhook_events.order_id` es `nullOnDelete` — ambos bloquean el delete.
- `stock_reservations.order_id` es `nullOnDelete`. Delete exige que no queden `ACTIVE` ni `CONSUMED`.
- ADMIN no puede marcar `PAID` ni `FULFILLED`.

## Demo reproducible (sintético)

1. CUSTOMER crea carrito y `POST /api/checkout/validate`.
2. CUSTOMER `POST /api/orders` con `Idempotency-Key` → `PENDING_PAYMENT`.
3. ADMIN `GET /api/admin/orders` → lista paginada.
4. ADMIN `GET /api/admin/orders/{orderId}` → detalle.
5. ADMIN `PATCH` `customer_phone` → PostgreSQL refleja el cambio.
6. ADMIN `PATCH` `status=CANCELLED` → reservas `ACTIVE` → `RELEASED`.
7. ADMIN `DELETE` → 204; la fila de `orders` desaparece.

## Suite

Valores reales al cierre de implementación (PHPUnit sobre `rotor_testing`):

- Admin Orders dirigidos: 18 passed / 291 assertions / 0 failures
- Full suite: 156 passed / 1403 assertions / 0 failures
- `TEST_DATABASE=rotor_testing`
- `DEVELOPMENT_DB_CHANGED=NO`
