# MVP-0 — Auditoría de base de datos

**Cluster auditado:** PostgreSQL 16.15 local  
**Conexión (sin secretos):** `DB_CONNECTION=pgsql`, `DB_HOST=127.0.0.1`, `DB_DATABASE=rotor`, `DB_USERNAME=rotor`  
**Password:** no se publica.  
**Migraciones:** `php artisan migrate:status` → 15/15 `Ran`.

## Safety gate

| Check | Resultado |
|---|---|
| Host local | PASS (`127.0.0.1`) |
| Database name | PASS (`rotor`) |
| Driver | PASS (`pgsql`) |
| `pg_dump --version` | PostgreSQL 16.15 |
| `pg_restore --version` | PostgreSQL 16.15 |
| `psql --version` | PostgreSQL 16.15 |
| Rol `rotor` superuser | no (`rolsuper=false`) |
| Rol `rotor` CREATEDB | no (`rolcreatedb=false`) |

`CREATE DATABASE` con el rol de aplicación **falla** en este cluster. El restore aislado de MVP-0 se verificó en un PostgreSQL 16 local adicional (Docker, `127.0.0.1:55432`) donde el rol sí pudo crear bases temporales. `rotor` en `:5432` no se sobrescribió en esa prueba.

## Migraciones (15)

| Migración | Estado |
|---|---|
| `0001_01_01_000000_create_users_table` | Ran |
| `0001_01_01_000001_create_cache_table` | Ran |
| `0001_01_01_000002_create_jobs_table` | Ran |
| `2026_10_15_054444_create_personal_access_tokens_table` | Ran |
| `2026_10_15_100000_add_role_to_users_table` | Ran |
| `2026_10_15_100100_create_categories_table` | Ran |
| `2026_10_15_100200_create_products_table` | Ran |
| `2026_10_15_100300_create_customers_table` | Ran |
| `2026_10_15_100400_create_carts_table` | Ran |
| `2026_10_15_100500_create_cart_items_table` | Ran |
| `2026_10_15_100600_create_orders_table` | Ran |
| `2026_10_15_100700_create_order_items_table` | Ran |
| `2026_10_15_100800_create_stock_reservations_table` | Ran |
| `2026_10_15_100900_create_payments_table` | Ran |
| `2026_10_15_101000_create_webhook_events_table` | Ran |

`DB::statement` aparece **solo** en migraciones (CHECK / unique parcial). No hay concatenación SQL en controllers/services auditados.

## Tablas de dominio

### users

| Aspecto | Valor |
|---|---|
| PK | `id` bigint identity |
| UNIQUE | `email` |
| CHECK | `role IN ('ADMIN','CUSTOMER')` |
| NULLABLE | `email_verified_at`, `remember_token` |
| Timestamps | sí (`created_at`, `updated_at`) |
| Money | no |
| Auth | `password` hashed en modelo; `remember_token` hidden |

### customers

| Aspecto | Valor |
|---|---|
| PK | `id` uuid |
| FK | `user_id` → `users.id` **nullOnDelete**, UNIQUE |
| INDEX | `email`; `(document_type, document_number)` |
| NULLABLE | `user_id` |
| Timestamps | `timestampsTz` |

### categories

| Aspecto | Valor |
|---|---|
| PK | `id` uuid |
| UNIQUE | `slug` |
| NULLABLE | `description` |
| Timestamps | `timestampsTz` |
| Flags | `is_active` boolean default true |

### products

| Aspecto | Valor |
|---|---|
| PK | `id` uuid |
| FK | `category_id` → `categories.id` **restrictOnDelete** |
| UNIQUE | `sku` (64) |
| INDEX | `category_id`, `is_active` |
| Money | `price decimal(18,2)`, `tax_rate decimal(8,4)`, `currency char(3)` default COP |
| CHECK | price ≥ 0, tax_rate ≥ 0, stock_quantity ≥ 0 |
| Stock | `stock_quantity` integer |
| Flags | `is_active` |
| Timestamps | `timestampsTz` |

### carts

| Aspecto | Valor |
|---|---|
| PK | `id` uuid |
| FK | `customer_id` → `customers.id` **nullOnDelete** |
| CHECK | `status IN ('ACTIVE','CONVERTED','EXPIRED','ABANDONED')` |
| INDEX | `(customer_id, status)` |
| Timestamps | `timestampsTz` + `expires_at` |

### cart_items

| Aspecto | Valor |
|---|---|
| PK | `id` uuid |
| FK | `cart_id` → `carts.id` **cascadeOnDelete**; `product_id` → `products.id` **restrictOnDelete** |
| UNIQUE | `(cart_id, product_id)` |
| CHECK | `quantity > 0` |
| Timestamps | `timestampsTz` |

### orders

| Aspecto | Valor |
|---|---|
| PK | `id` uuid |
| UNIQUE | `order_number`; `idempotency_key` |
| FK | `customer_id` → `customers.id` **nullOnDelete** |
| CHECK status | `DRAFT, PENDING_PAYMENT, PAID, FULFILLED, FAILED, EXPIRED, CANCELLED` |
| CHECK delivery | `PICKUP` / `COORDINATED_SHIPPING` + address rule |
| Money | `subtotal`, `tax_total`, `total` decimal(18,2) ≥ 0 |
| Snapshots | `customer_name/email/phone/document_*`, delivery |
| INDEX | `(customer_id, created_at)`, `(status, created_at)` |
| Timestamps | `timestampsTz` + paid/fulfilled/cancelled/failed/expired |

El servicio de creación escribe `PENDING_PAYMENT` (no usa `DRAFT` en el camino E3 actual).

### order_items

| Aspecto | Valor |
|---|---|
| PK | `id` uuid |
| FK | `order_id` **restrictOnDelete**; `product_id` **restrictOnDelete** |
| Snapshots | `sku`, `product_name`, `unit_price`, `tax_rate`, `tax_amount`, `line_subtotal`, `line_total` |
| CHECK | quantity > 0; money ≥ 0 |
| Money | decimal(18,2) precios; tax_rate decimal(8,4) nullable |

### stock_reservations

| Aspecto | Valor |
|---|---|
| PK | `id` uuid |
| FK | `cart_id` restrict; `cart_item_id` **nullOnDelete**; `order_id` **nullOnDelete**; `product_id` restrict |
| CHECK | `status IN ('ACTIVE','CONSUMED','RELEASED','EXPIRED')`; quantity > 0 |
| INDEX | `(product_id, status, expires_at)`, `order_id`, `cart_id`, `cart_item_id` |

Relación con órdenes: al crear orden se asigna `order_id` a reservas ACTIVE del carrito. Al cancelar `PENDING_PAYMENT`/`DRAFT` se marcan `RELEASED`.

### payments

| Aspecto | Valor |
|---|---|
| PK | `id` uuid |
| FK | `order_id` **restrictOnDelete** |
| CHECK | provider `WOMPI\|MERCADOPAGO`; status `PENDING\|APPROVED\|DECLINED\|EXPIRED\|CANCELLED\|ERROR`; amount ≥ 0 |
| UNIQUE parcial | `(provider, provider_reference)` WHERE reference IS NOT NULL |
| Estado de producto | tabla existe; **no hay** endpoints de pasarela (issue #9) |

### webhook_events

| Aspecto | Valor |
|---|---|
| PK | `id` uuid |
| UNIQUE | `(provider, external_event_id)` |
| FK | `order_id` nullOnDelete; `payment_id` nullOnDelete |
| CHECK | provider; processing_status `RECEIVED\|PROCESSED\|IGNORED_DUPLICATE\|FAILED` |
| Payload | `jsonb` nullable |
| Estado de producto | tabla existe; **no hay** endpoint webhook |

## Tablas framework (presentes)

`cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `migrations`, `password_reset_tokens`, `sessions`, `personal_access_tokens`.

Auth E3 usa sesión cookie (Sanctum SPA), no tokens personales como camino principal.

## Conteos snapshot pre-PHPUnit (rotor)

| TABLE | ORIGINAL_COUNT |
|---|---|
| users | 2 |
| customers | 2 |
| categories | 7 |
| products | 12 |
| carts | 3 |
| cart_items | 3 |
| stock_reservations | 3 |
| orders | 3 |
| order_items | 3 |
| payments | 0 |
| webhook_events | 0 |
| migrations | 15 |

## Restore aislado — matriz MATCH

Restore sobre `rotor_restore_mvp0` (PostgreSQL 16 local aislado, no el cluster de aplicación):

| TABLE | ORIGINAL_COUNT | RESTORED_COUNT | MATCH |
|---|---|---|---|
| users | 2 | 2 | MATCH |
| customers | 2 | 2 | MATCH |
| categories | 7 | 7 | MATCH |
| products | 12 | 12 | MATCH |
| carts | 3 | 3 | MATCH |
| cart_items | 3 | 3 | MATCH |
| stock_reservations | 3 | 3 | MATCH |
| orders | 3 | 3 | MATCH |
| order_items | 3 | 3 | MATCH |
| payments | 0 | 0 | MATCH |
| webhook_events | 0 | 0 | MATCH |
| migrations | 15 | 15 | MATCH |

`rotor_restore_script_test` (creada por `scripts/db-restore.ps1 -CreateTargetIfMissing`) reprodujo los mismos conteos de dominio.

No se compararon hashes de password visualmente.

## Integridad Orders (sin modificar)

- Status CHECK alineado con máquina de estados documentada.
- Delivery CHECK: PICKUP ⇒ address NULL; COORDINATED_SHIPPING ⇒ address no vacía.
- Idempotency unique en `orders.idempotency_key`.
- Snapshots de cliente y de líneas en `order_items`.
- `customer_id` nullOnDelete (borra customer, conserva orden).
- `order_items.product_id` restrict (no borrar producto referenciado).
- Reservas: cart restrict; order nullOnDelete; product restrict.
- Money decimal(18,2) HALF_UP en servicio (`App\Support\Money`).
- Pagos/webhooks: esquema listo, runtime no implementado.

## PHPUnit vs datos

`php artisan test` usa `RefreshDatabase` sobre `rotor` (phpunit.xml no apunta a sqlite). Tras la suite, tablas de dominio quedaron en 0 y `migrations=15`. El dump custom se usó después, con flags destructivos explícitos, para devolver el snapshot auditado. Ver `docs/operations/DATABASE_BACKUP_RESTORE.md`.
