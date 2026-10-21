# MVP-0 — Diagnóstico

**Base:** `b221dd749782d03ede1cfdacf534c77991eb83b6` (`v0.3.0`)  
**Evidencia:** código en `main`, `php artisan route:list`, OpenAPI, PHPUnit, PostgreSQL local, backup/restore reales.

Esta fase **no implementó funcionalidad de producto**.

## Inventario del repositorio

| Ruta | Estado real |
|---|---|
| `/backend` | Productivo E3. Laravel 12.69.2, PHP 8.2.12, Sanctum ^4.3, PHPUnit 11. |
| `/frontend` | Solo `.gitkeep`. MISSING. |
| `/web` | Prototipo E2 histórico: HTML/CSS/JS, `datos/ejemplo.json`, carrito `localStorage`. No consume Laravel. |
| `/api` | `openapi.yaml` contrato vigente (34 operationIds). |
| `/docs` | Arquitectura, modelo de datos, evidencia E3, wireframes. |
| `/tests/e2e` | Solo `.gitkeep`. |
| `/scripts` | Añadidos en MVP-0: backup/restore PostgreSQL. |

Carpetas históricas: `web/` (E2). Código productivo: `backend/` + `api/openapi.yaml`. Prototipo: `web/`. Tests: `backend/tests`. Config: `backend/.env` local (no versionado), `backend/.env.example`.

## Tabla de rutas API (34)

Middleware global API: `statefulApi()` (Sanctum SPA + CSRF). No hay `throttleApi`.

| Ruta | Controller | Middleware | Role | Request | Resource | Service | Tests | Estado |
|---|---|---|---|---|---|---|---|---|
| POST /api/auth/login | AuthController@login | (público) | — | LoginRequest | payload JSON | Auth | Auth/LoginTest | PASS |
| POST /api/auth/logout | AuthController@logout | auth:sanctum | — | — | 204 | Auth | Auth/LogoutTest | PASS |
| GET /api/auth/me | AuthController@me | auth:sanctum | — | — | payload JSON | Auth | Auth/MeTest | PASS |
| GET /api/products | CatalogController@index | público | — | query page/q | ProductSummaryResource | Eloquent | Catalog | PASS |
| GET /api/products/{id} | CatalogController@show | público | — | UUID | ProductSummaryResource | Eloquent | Catalog | PASS |
| /api/admin/categories ×5 | Admin\CategoryController | auth:sanctum | ADMIN | Store/UpdateCategoryRequest | CategoryResource | Eloquent | Admin/CategoryCrudTest | PASS |
| /api/admin/products ×5 | Admin\ProductController | auth:sanctum | ADMIN | Store/UpdateProductRequest | AdminProductResource | Eloquent | Admin/ProductCrudTest | PASS |
| /api/admin/customers ×5 | Admin\CustomerController | auth:sanctum | ADMIN | Store/UpdateCustomerRequest | CustomerResource | Eloquent | Admin/CustomerCrudTest | PASS |
| /api/admin/users ×5 | Admin\UserController | auth:sanctum | ADMIN | Store/UpdateUserRequest | UserResource | Eloquent | Admin/UserCrudTest | PASS |
| GET /api/cart | CartController@show | auth:sanctum | CUSTOMER | — | CartResource | CartService | Cart | PASS |
| POST /api/cart/items | CartController@storeItem | auth:sanctum | CUSTOMER | AddCartItemRequest | CartResource | CartService | Cart | PASS |
| PATCH /api/cart/items/{id} | CartController@updateItem | auth:sanctum | CUSTOMER | UpdateCartItemRequest | CartResource | CartService | Cart | PASS |
| DELETE /api/cart/items/{id} | CartController@destroyItem | auth:sanctum | CUSTOMER | — | 204 | CartService | Cart | PASS |
| POST /api/checkout/validate | CheckoutController | auth:sanctum | CUSTOMER | CheckoutRequest | JSON | CheckoutService | Checkout | PASS |
| POST /api/orders | OrderController@store | auth:sanctum | CUSTOMER | CheckoutRequest | OrderResource | OrderService | Orders | PASS |
| GET /api/orders/{id} | OrderController@show | auth:sanctum | CUSTOMER | UUID | OrderResource | OrderService | Orders | PASS |
| GET /api/orders/{id}/status | OrderController@status | auth:sanctum | CUSTOMER | UUID | JSON | OrderService | Orders | PASS |
| POST /api/orders/{id}/cancel | OrderController@cancel | auth:sanctum | CUSTOMER | UUID | OrderResource | OrderService | Orders | PASS |

Laravel `apiResource` también registra `PUT` además de `PATCH` en admin. OpenAPI documenta PATCH. Comportamiento PUT no está en el contrato; no se cambió en MVP-0.

## Auth / security (código real)

| Control | Estado |
|---|---|
| Sanctum statefulApi | PASS |
| CSRF (SPA cookie) | PASS |
| CORS origins explícitos + credentials | PASS (no wildcard de origins) |
| Session driver database | PASS |
| http_only cookie | PASS (default true) |
| same_site | `lax` (local) |
| session secure | vacío / no forzado (aceptable en HTTP local; gap producción) |
| session domain | `null` |
| password hashed cast | PASS |
| password hidden | PASS |
| role middleware `RequireRole` | PASS |
| 401/403 ApiError | PASS |
| 500 oculta stack al cliente | PASS (`INTERNAL_ERROR` + `trace_id`) |
| APP_DEBUG local | `true` (correcto local; gap si se publica) |
| Mass assignment | fillable en modelos; no `$guarded = []` en dominio auditado |
| SQL raw concatenado | no encontrado en app (solo CHECK en migraciones) |
| Login throttling | **MISSING** |

Gap de throttling: no hay `RateLimiter` ni `throttleApi` en `bootstrap/app.php`. El único throttle en `config/auth.php` es del password broker (60s), no de login. Clasificado **P1** (bruteforce local/demo; P0 si el login se publica a internet).

## Validaciones

| Recurso | Form Request | Reglas clave | Frontend |
|---|---|---|---|
| Users | Store/UpdateUserRequest | unique email, role enum, password min 8 | MISSING |
| Products | Store/UpdateProductRequest | uuid exists category, unique sku, decimal, stock min 0 | MISSING |
| Categories | Store/UpdateCategoryRequest | unique slug regex, is_active | MISSING |
| Customers | Store/UpdateCustomerRequest | unique user_id, email, documentos | MISSING |
| Cart | Add/UpdateCartItemRequest | uuid product_id, quantity min 1 | MISSING (E2 no valida contra API) |
| Checkout/Orders | CheckoutRequest | customer array cerrado, delivery enum, address condicional, extra fields 422 | MISSING |
| Orders extra | OrderService | UUID, ownership, Idempotency-Key, state cancel | MISSING |

## Frontend

`frontend/` contiene únicamente `.gitkeep` → **MISSING**.

`web/` = **E2 HISTORICAL PROTOTYPE**:

- `index.html` catálogo con filtros/búsqueda.
- `detalle.html` ficha.
- `checkout.html` carrito/checkout simulado.
- Datos: `web/datos/ejemplo.json` (fetch local).
- Carrito: `localStorage` (`web/js/app.js`).
- No llama a `/api/*`.

Reutilizable como **referencia UX** (layout catálogo, estados `?state=loading|empty|error`, copy industrial). No copiar como frontend E3.

**DEMO HARNESS — OUTSIDE REPOSITORY:** `C:\Temp\rotor-e3-admin-demo` demostró persistencia API → PostgreSQL. No es el frontend final y no se copió al repo en MVP-0.

## P0

Confirmados desde el proyecto real:

1. Frontend integrado Next.js inexistente (`frontend/.gitkeep`).
2. Dashboard administrativo inexistente.
3. UI CRUD Users inexistente.
4. UI CRUD Products inexistente.
5. UI CRUD Categories inexistente.
6. UI CRUD Orders inexistente.
7. Orders no es CRUD administrativo: no hay list, update, delete ni rutas `/api/admin/orders`.
8. Login UI inexistente (bloquea cualquier pantalla admin).

## P1

1. Backup/restore ahora existen como scripts + doc; falta integrar en runbook de clase/CI y conceder `CREATEDB` o procedimiento superuser en el cluster `:5432`.
2. Rate limiting de login MISSING.
3. Validación frontend MISSING.
4. Responsive del producto final MISSING (E2 tiene viewport meta; no hay SPA).
5. E2E Playwright MISSING.
6. Redocly: 16 warnings de examples vs schema `if/else` de delivery. Contrato válido; no se tocó YAML.
7. Publicación web E3: `APP_DEBUG`, `SESSION_SECURE_COOKIE`, HTTPS, separación de entornos.
8. Pagos sandbox / webhooks / conciliación: tablas sí, runtime no (issue #9). Fuera de la rúbrica UI del profesor; sigue siendo gap del objetivo transaccional original.

## P2

1. UX polish, loading skeletons, filtros avanzados admin.
2. Reporting / dashboard widgets.
3. Observability más allá de `trace_id` + log stack.
4. PUT vs PATCH en `apiResource` no documentado.
5. Íconos/copy del prototipo E2 como referencia visual.

## Matriz de reutilización

| Componente actual | Decisión |
|---|---|
| Laravel backend | REUTILIZAR |
| PostgreSQL + migraciones | REUTILIZAR |
| Sanctum SPA cookies | REUTILIZAR |
| ApiError / ApiException / trace_id | REUTILIZAR |
| Admin CRUD Users/Products/Categories/Customers | REUTILIZAR |
| Catalog público | REUTILIZAR |
| CartService + reservas | REUTILIZAR |
| CheckoutService | REUTILIZAR |
| OrderService transaccional + idempotencia | REUTILIZAR / EXTENDER CUIDADOSAMENTE (no reemplazar por CRUD anémico) |
| OpenAPI 34 ops | REUTILIZAR; ADAPTAR cuando exista contrato admin orders |
| PHPUnit Feature | REUTILIZAR |
| `web/` E2 | REFERENCIA UX — NO TOCAR como producto E3 |
| `frontend/.gitkeep` | COMPLETAR (Next.js nuevo) |
| payments / webhook_events schema | REUTILIZAR más adelante (#9) |
| Demo harness fuera del repo | NO TOCAR / no importar |
| Tag `v0.3.0` | NO TOCAR |

## Funcionamiento web (sin desplegar)

Listo: Pages del prototipo E2.

Falta para publicar E3: frontend, CORS de dominio real, `FRONTEND_URL`/`APP_URL` HTTPS, cookies `Secure` + `SameSite` acordes, `APP_DEBUG=false`, no usar wildcard origins, rate limit login.
