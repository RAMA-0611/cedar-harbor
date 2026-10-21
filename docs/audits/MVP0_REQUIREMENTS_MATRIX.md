# MVP-0 — Matriz de requisitos

**Repositorio:** `RAMA-0611/cedar-harbor`  
**Base auditada:** `main` = `v0.3.0` = `b221dd749782d03ede1cfdacf534c77991eb83b6`  
**Rama de auditoría:** `chore/mvp0-audit-backup`  
**Fecha de evidencia:** 2026-10-20  

Estados permitidos: `PASS` | `PARTIAL` | `MISSING` | `NOT_VERIFIED`.

El prototipo estático `web/` (E2) **no** cuenta como frontend MVP/E3.  
El harness temporal fuera del repo (`C:\Temp\rotor-e3-admin-demo`) **no** cuenta como producto.

## Matriz

| Requisito | Backend | Frontend | Base de datos | Tests | Estado | Prioridad | Observación |
|---|---|---|---|---|---|---|---|
| LOGIN | PASS | MISSING | PASS | PASS | PARTIAL | P0 | `POST /api/auth/login`, `logout`, `me` con Sanctum cookie + CSRF. No hay UI Next.js. No hay rate limit de login. |
| DASHBOARD | MISSING | MISSING | NOT_VERIFIED | MISSING | MISSING | P0 | No existe ruta, controller ni página de métricas administrativas. |
| USUARIOS | PASS | MISSING | PASS | PASS | PARTIAL | P0 | CRUD admin 5 operaciones, persistencia PostgreSQL, password hashed, self-delete 403. UI admin MISSING. |
| PRODUCTOS | PASS | MISSING | PASS | PASS | PARTIAL | P0 | CRUD admin 5 operaciones + catálogo público. SKU unique, FK category, decimal, `RESOURCE_IN_USE`. UI admin MISSING. |
| PEDIDOS | PARTIAL | MISSING | PASS | PASS | PARTIAL | P0 | Flujo CUSTOMER transaccional (create/detail/status/cancel). No hay listado, PATCH, DELETE ni CRUD admin. |
| CATEGORÍAS | PASS | MISSING | PASS | PASS | PARTIAL | P0 | CRUD admin 5 operaciones, slug unique, 409 si tiene productos. UI admin MISSING. |
| DOCUMENTACIÓN API | PASS | PASS | NOT_VERIFIED | PASS | PASS | P1 | `api/openapi.yaml` 34 operationIds; UI `/docs`. Redocly válido con 16 warnings de examples. |
| PRUEBAS | PASS | MISSING | PASS | PARTIAL | PARTIAL | P1 | PHPUnit 133 passed / 1093 assertions / 0 failures. Playwright E2E: solo `tests/e2e/.gitkeep`. |
| Testing isolation | MISSING | NOT_VERIFIED | MISSING | MISSING | MISSING | P0 | `phpunit.xml` define `APP_ENV=testing` pero no activa `DB_CONNECTION`/`DB_DATABASE`. SQLite está comentado. La suite hereda `.env` → `rotor`. |
| Data loss protection | PASS | NOT_VERIFIED | PARTIAL | NOT_VERIFIED | PARTIAL | P0 | Backup/restore PASS y recuperación empírica OK; tests todavía no están aislados, así que un `php artisan test` puede vaciar `rotor`. |
| BACKUP | PASS | NOT_VERIFIED | PASS | NOT_VERIFIED | PASS | P1 | Dump custom real `pg_dump -F c` de `rotor` local. Script `scripts/db-backup.ps1`. |
| RESTORE | PASS | NOT_VERIFIED | PASS | NOT_VERIFIED | PASS | P1 | Restore verificado en DB aislada; conteos MATCH. Script rechaza `target=rotor` por defecto. |
| WEB | PARTIAL | PARTIAL | NOT_VERIFIED | MISSING | PARTIAL | P1 | E2 estático publicable. API E3 no está lista para publicación (cookies/HTTPS/APP_DEBUG). |
| LOCAL | PASS | PARTIAL | PASS | PASS | PASS | P1 | PostgreSQL local `rotor`, Laravel `:8000`, Swagger `/docs`, prototipo E2 `:8091`. Frontend Next `:3000` no existe. |

## Detalle por fila

### LOGIN

- Backend: `AuthController` + `LoginRequest` (`email` required, `password` required). Sesión regenerada. `me` no expone password.
- Middleware: login público; `logout`/`me` = `auth:sanctum`.
- Tests: `tests/Feature/Auth/*` + `AuthApiErrorIntegrationTest`.
- Gap: UI de login MISSING. Sin `throttleApi` / RateLimiter de login.

### DASHBOARD

- No hay endpoint `/api/admin/dashboard` ni equivalente.
- No hay agregaciones de pedidos/usuarios/stock para UI.
- Estado: MISSING.

### USUARIOS

- `GET/POST/GET/PATCH/DELETE /api/admin/users` vía `apiResource`.
- Form Requests: unique email, role `ADMIN|CUSTOMER`, password min 8.
- `UserResource` no incluye `password` ni `remember_token`.
- `User.password` cast `hashed`; `$hidden` incluye password y remember_token.
- Self-delete: `AuthorizationException` → 403 `FORBIDDEN`.
- Frontend: MISSING.

### PRODUCTOS

- CRUD admin completo + catálogo `GET /api/products` (público, activos).
- Validaciones: `category_id` uuid exists, SKU unique, price/tax numeric min 0, stock integer min 0, `is_active` boolean.
- Precisión: `decimal:2` / `decimal:4` expuesta como string en resource.
- Destroy: 409 `RESOURCE_IN_USE` si hay cart_items, stock_reservations u order_items.
- Frontend visual integrado: MISSING.

### PEDIDOS

Capacidades actuales (código + OpenAPI + tests):

| Capacidad | ¿Existe? |
|---|---|
| CREATE | sí (`POST /api/orders`, CUSTOMER, idempotencia) |
| READ list | no |
| READ detail | sí (`GET /api/orders/{orderId}`, ownership) |
| UPDATE | no (no PATCH admin ni customer) |
| DELETE | no |
| ADMIN management | no |
| CUSTOMER ownership | sí |

El flujo actual es transaccional y orientado al CUSTOMER hasta `PENDING_PAYMENT`. **No es un CRUD administrativo completo.**

Gaps frente a rúbrica de pedidos admin: listado, filtros, cambio de estado por ADMIN, detalle admin, eventual cancelación/gestión admin.

### CATEGORÍAS

- CRUD admin 5 operaciones.
- Slug unique + regex kebab-case.
- Destroy: 409 `RESOURCE_IN_USE` si hay products (`restrictOnDelete` en FK).
- Frontend: MISSING.

### DOCUMENTACIÓN API

- Fuente única: `api/openapi.yaml`.
- Paridad verificada: 34 rutas en `backend/routes/api.php` ↔ 34 `operationId`.
- `php artisan route:list --path=api` muestra 35 porque también coincide `docs/openapi.yaml` (substring `api`). Esa ruta extra es web, no API.
- No hay operationIds de payment-intent / webhook / reconcile implementados.
- Redocly CLI `1.34.21`: válido, 16 warnings (`no-invalid-media-type-examples` en examples Order). No se modificó YAML en MVP-0.

### PRUEBAS

- PHPUnit ejecutado sobre PostgreSQL `rotor` local: **133 passed, 1093 assertions, 0 failures**.
- Dominios cubiertos: Auth, Admin, Catalog, Cart, Checkout, Orders, Docs, Api, Persistence.
- E2E Playwright: MISSING (`tests/e2e/.gitkeep`).
- Tests de frontend Next.js: MISSING.
- Isolation: MISSING (ver fila Testing isolation). No volver a ejecutar la suite contra `rotor` hasta MVP-1.

### Testing isolation

- `backend/phpunit.xml`: `APP_ENV=testing` definido.
- `DB_CONNECTION` / `DB_DATABASE` de testing **no** activos; SQLite comentado.
- Sin DB `rotor_testing`. Laravel hereda `backend/.env` → `rotor`.
- `RefreshDatabase` vació datos de dominio de `rotor` durante MVP-0.
- Estado: **MISSING**. Prioridad: **P0**.

### Data loss protection

- Backup custom y restore aislado: PASS.
- Recuperación destructiva confirmada de `rotor` tras PHPUnit: PASS.
- Mientras los tests apunten a `rotor`, la protección es **PARTIAL**: el dump mitiga, no previene, la pérdida.

### BACKUP

- Herramientas: `pg_dump` / `pg_restore` / `psql` **16.15**.
- Safety gate: `DB_CONNECTION=pgsql`, `DB_HOST=127.0.0.1`, `DB_DATABASE=rotor`.
- Dump custom fuera del repo. SHA256 y tamaño registrados en el informe de cierre / evidencia de operación.
- Script reutilizable: `scripts/db-backup.ps1`.

### RESTORE

- `pg_restore --list`: 132 TOC entries.
- Restore **no** se hizo encima de `rotor` para la prueba aislada.
- El rol `rotor` en el cluster local `127.0.0.1:5432` tiene `rolcreatedb=false`; `CREATE DATABASE` fue denegado.
- Restore aislado verificado en PostgreSQL 16 local (contenedor Docker `127.0.0.1:55432`), bases `rotor_restore_mvp0` y `rotor_restore_script_test`.
- Conteos MATCH en users, categories, products, customers, carts, cart_items, orders, order_items, stock_reservations, payments, webhook_events, migrations.
- Tras PHPUnit (`RefreshDatabase` dejó tablas de dominio en 0), se recuperó `rotor` con `-AllowDestructiveRestore -ConfirmDestructive -Clean`. Conteos post-recovery MATCH con el dump.

### WEB

- Listo: prototipo E2 en GitHub Pages (`web/`).
- No listo: SPA E3, CORS/cookies HTTPS, `APP_DEBUG=false` de producción, frontend `:3000`.
- No se desplegó nada en esta fase.

### LOCAL

Pasos reales actuales:

1. PostgreSQL 16 local, base `rotor`, usuario `rotor`.
2. `cd backend && composer install && php artisan serve` → `http://127.0.0.1:8000`.
3. Swagger: `http://127.0.0.1:8000/docs`.
4. Prototipo E2: `python -m http.server 8091 --bind 127.0.0.1` desde `web/`.
5. Frontend Next.js puerto 3000: no hay app que levantar.

## Paridad ruta ↔ operationId

| Ruta API | operationId |
|---|---|
| POST /api/auth/login | login |
| POST /api/auth/logout | logout |
| GET /api/auth/me | me |
| GET /api/products | listProducts |
| GET /api/products/{productId} | getProduct |
| GET/POST /api/admin/categories | adminListCategories / adminCreateCategory |
| GET/PATCH/DELETE /api/admin/categories/{category} | adminGetCategory / adminUpdateCategory / adminDeleteCategory |
| GET/POST /api/admin/products | adminListProducts / adminCreateProduct |
| GET/PATCH/DELETE /api/admin/products/{product} | adminGetProduct / adminUpdateProduct / adminDeleteProduct |
| GET/POST /api/admin/customers | adminListCustomers / adminCreateCustomer |
| GET/PATCH/DELETE /api/admin/customers/{customer} | adminGetCustomer / adminUpdateCustomer / adminDeleteCustomer |
| GET/POST /api/admin/users | adminListUsers / adminCreateUser |
| GET/PATCH/DELETE /api/admin/users/{user} | adminGetUser / adminUpdateUser / adminDeleteUser |
| GET /api/cart | getCart |
| POST /api/cart/items | addCartItem |
| PATCH /api/cart/items/{cartItemId} | updateCartItem |
| DELETE /api/cart/items/{cartItemId} | removeCartItem |
| POST /api/checkout/validate | validateCheckout |
| POST /api/orders | createOrder |
| GET /api/orders/{orderId} | getOrder |
| GET /api/orders/{orderId}/status | getOrderStatus |
| POST /api/orders/{orderId}/cancel | cancelOrder |

Ningún endpoint futuro de pagos aparece como implementado en rutas ni en OpenAPI actual.
