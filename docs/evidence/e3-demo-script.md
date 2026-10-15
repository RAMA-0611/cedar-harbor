# E3 — Demo script (≤ 5 minutes)

Audience: reviewer / instructor.

Prerequisites: PostgreSQL `rotor`, `php artisan migrate:fresh --seed`, `php artisan serve`.

1. Open `http://127.0.0.1:8000/docs` — confirm tags/schemas load from `api/openapi.yaml`.
2. Show migrations/seed against PostgreSQL (tables `products`, `categories`, `users`).
3. Authenticate as ADMIN (create via admin seed/factory locally; no published demo password).
4. Create a Category (`POST /api/admin/categories`).
5. Create a Product under that category (`POST /api/admin/products`).
6. List/patch the product (`GET`/`PATCH /api/admin/products/{id}`).
7. Delete a disposable demo resource (`DELETE`) — show `204` or `409 RESOURCE_IN_USE` when referenced.
8. Authenticate as CUSTOMER → admin route returns **403 FORBIDDEN**.
9. Without session → **401 UNAUTHENTICATED**.
10. Invalid payload → **422 VALIDATION_ERROR**.
11. Public catalog `GET /api/products` shows the created active product.

Notes:

- Cart endpoints are tracked in #6; do not demo cart until merged to main.
- Swagger "Try it out" works for public routes; cookie/CSRF auth for admin is clearer via an authenticated HTTP client.
