# E3 — Demo script (≤ 5 minutes)

Audience: reviewer / instructor.

Prerequisites: PostgreSQL `rotor`, `php artisan migrate:fresh --seed`, `php artisan serve`.

1. Open `http://127.0.0.1:8000/docs` — confirm tags/schemas (incluye **Cart**) from `api/openapi.yaml`.
2. Authenticate as ADMIN (create via factory/admin seed locally; no published demo password).
3. CRUD Category / Product (`POST`/`GET`/`PATCH`/`DELETE` under `/api/admin/...`).
4. Invalid payload → **422 VALIDATION_ERROR**.
5. Authenticate as CUSTOMER (con perfil `Customer`).
6. Public catalog `GET /api/products` → **200**.
7. `GET /api/cart` → carrito ACTIVE (vacío o con ítems previos).
8. `POST /api/cart/items` con un producto activo → reserva `ACTIVE` y `expires_at` futuro.
9. Volver a `GET /api/cart` y mostrar persistencia + totales como strings decimales.
10. Mostrar `available_quantity` / reserva (stock menos reservas ACTIVE vigentes de otros carritos).
11. CUSTOMER contra ruta admin → **403 FORBIDDEN**.
12. Request sin sesión → **401 UNAUTHENTICATED**.

Notes:

- Swagger "Try it out" works for public routes; cookie/CSRF auth for admin/cart is clearer via an authenticated HTTP client.
- **No** demonstrar checkout, order ni payment reales hasta que estén integrados en `main`.
