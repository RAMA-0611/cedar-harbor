# E3 — Demo script (≤ 5 minutes)

Audience: reviewer / instructor.

## Preparación previa (fuera del cronómetro)

Antes de iniciar:

1. PostgreSQL local `rotor` configurado en `backend/.env` (sin secretos en el repo).
2. `php artisan migrate:fresh --seed`.
3. Existe **1 ADMIN**, **1 CUSTOMER** con perfil `Customer` vinculado, y productos del seed/factories locales.
4. `php artisan serve` → `http://127.0.0.1:8000`.

No hay passwords demo publicados en el repositorio. Crear usuarios con factories/admin API o seeders locales **no versionados**.

## Secuencia (≤ 5 min)

1. Abrir `http://127.0.0.1:8000/docs` — tags **Auth**, **Catalog**, **Admin**, **Cart**, **Checkout**, **Orders**. Confirmar que **no** aparecen payment-intent / webhook / reconcile como operaciones ejecutables.
2. Mencionar PostgreSQL: migraciones reales + suite verde (ver `e3-backend-validation.md`).
3. Login **CUSTOMER** (cliente HTTP con cookies + CSRF Sanctum; Swagger Try it out es limitado para sesión).
4. `GET /api/products` → catálogo 200.
5. `POST /api/cart/items` → reserva `ACTIVE`.
6. `GET /api/cart` → persistencia y totales como strings decimales.
7. `POST /api/checkout/validate` → `valid: true`.
8. `POST /api/orders` con header `Idempotency-Key` **fija** → **201** `PENDING_PAYMENT`.
9. Repetir el **mismo** POST + la **misma** key → misma `order.id` (sin segunda orden).
10. `GET /api/orders/{id}/status` → `PENDING_PAYMENT`.
11. Error de aislamiento: CUSTOMER contra ruta admin → **403**, o payload inválido → **422**.
12. Cierre: sandbox / webhook / conciliación son el siguiente hito (**issue #9**), no parte de la demo E3.

## Notas

- No crear una segunda orden con otra `Idempotency-Key` durante la demo.
- No demostrar `PAID`, payment-intent ni webhooks.
- Try it out público: `GET /api/products` desde Swagger → 200.
