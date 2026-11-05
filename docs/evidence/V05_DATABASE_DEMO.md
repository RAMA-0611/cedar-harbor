# V0.5 — Consultas READ-ONLY para pgAdmin

Usar base **`rotor`**. No ejecutar UPDATE/DELETE en la demo.

## Productos

```sql
SELECT sku, name, price, stock_quantity, is_active, image_path, updated_at
FROM products
ORDER BY updated_at DESC
LIMIT 20;
```

## Pedidos

```sql
SELECT order_number, status, total, currency, paid_at, failed_at, created_at
FROM orders
ORDER BY created_at DESC
LIMIT 20;
```

## Payments

```sql
SELECT provider, status, amount, merchant_reference, provider_transaction_id, paid_at, failed_at, last_reconciled_at
FROM payments
ORDER BY created_at DESC
LIMIT 20;
```

## Reservations

```sql
SELECT status, quantity, expires_at, released_at, consumed_at, order_id, product_id
FROM stock_reservations
ORDER BY created_at DESC
LIMIT 20;
```

## Webhook events

```sql
SELECT event_type, signature_verified, processing_status, processing_result, received_at, processed_at
FROM webhook_events
ORDER BY received_at DESC
LIMIT 20;
```

Objetivo: mostrar UI → Laravel → PostgreSQL sin tocar producción ni secretos.
