# Matriz final de requisitos — MVP integrado

Base `main`: `e3f22d725de9fd9acceb60d3aa19a94aa135bee7`. Validación E2E en PostgreSQL `rotor_e2e` (Playwright). PHPUnit en `rotor_testing`. Desarrollo/demo en `rotor`.

Alcance académico del MVP: **completo**. Roadmap transaccional (pasarela sandbox, webhook, conciliación) permanece en issue **#9**.

## Requisitos académicos

| Requisito | Estado | Implementación | Prueba/Evidencia | Cómo demostrarlo en clase |
|---|---|---|---|---|
| Login | PASS | Sanctum SPA (`POST /api/auth/login`), formulario `#email` / `#password`, redirección ADMIN → `/dashboard`, CUSTOMER → `/acceso-denegado`. | `frontend/e2e/auth.spec.ts`; PHPUnit `LoginTest` / `MeTest`. | Abrir `/login`, fallar con credencial inválida, entrar como ADMIN. |
| Dashboard | PASS | Totales reales desde API (`users`, `products`, `categories`, `orders.meta.total`). Navegación `ADMIN_NAV`. | `frontend/e2e/dashboard.spec.ts`. | Mostrar las cuatro tarjetas numéricas y recorrer el menú. |
| Usuarios CRUD | PASS | UI `/usuarios` → `/api/admin/users`. Autoprotección: no eliminar la cuenta en sesión. | `frontend/e2e/users.spec.ts`; PHPUnit `UserCrudTest`. | Crear, ver, editar y eliminar un usuario sintético. Intentar eliminar la cuenta propia (botón deshabilitado). |
| Productos CRUD | PASS | UI `/productos` → `/api/admin/products`. Precio COP, impuesto %, stock entero. | `frontend/e2e/products.spec.ts`; PHPUnit `ProductCrudTest`. | Crear un SKU sintético, editarlo y eliminarlo. |
| Pedidos CRUD | PASS | CREATE: `POST /api/orders` (CUSTOMER, carrito → checkout). ADMIN: list/show/update/cancel/delete. **No existe** `POST /api/admin/orders`. | `frontend/e2e/orders.spec.ts`; PHPUnit `CreateOrderTest` + `AdminOrder*Test`. | Mostrar el aviso de creación en `/pedidos`, listar un pedido, ver detalle, editar contacto, cancelar y eliminar solo si está `CANCELLED`. |
| Categorías CRUD | PASS | UI `/categorias` → `/api/admin/categories`. `RESOURCE_IN_USE` si hay productos. | `frontend/e2e/categories.spec.ts`; PHPUnit `CategoryCrudTest`. | Crear una categoría vacía, intentar borrar «Rodamientos» (en uso), borrar la vacía. |
| Validaciones | PASS | Validación de formularios en UI + `VALIDATION_ERROR` 422 en API. | Specs E2E de login/CRUD; PHPUnit Feature de cada recurso. | Login 401; slug/SKU duplicados; DELETE de categoría con productos. |
| API Swagger/Postman | PASS | Contrato `api/openapi.yaml` (38 operations). UI `GET /docs`. | Redocly 0 errors / 1 warning localhost; Spectral 0; `/docs` = 200. | Abrir `http://127.0.0.1:8000/docs` y recorrer Auth, Catalog, Admin, Cart, Orders. |
| Pruebas del sistema | PASS | PHPUnit aislado + Playwright aislado. | PHPUnit 162/1416/0; Playwright 11/11/0 en dos corridas. | Mencionar `php artisan test` (`rotor_testing`) y `npm run test:e2e` (`rotor_e2e`). |

## Requisitos transversales

| Requisito | Estado | Implementación | Prueba/Evidencia | Cómo demostrarlo en clase |
|---|---|---|---|---|
| Autenticación/seguridad | PASS | Sanctum cookies, CSRF, `role:ADMIN` / `role:CUSTOMER`, ApiError 401/403. | Auth E2E; `RoleAuthorizationTest`; `ApiErrorTest`. | Sesión CUSTOMER no entra al panel; guest redirige a `/login`. |
| Persistencia DB | PASS | PostgreSQL 16, migraciones de dominio, seed de catálogo. | Conteos en `rotor`; suite sobre `rotor_testing`; reset solo en `rotor_e2e` con guard. | `SELECT current_database();` en cada entorno. |
| Backup/restore | PASS | `scripts/db-backup.ps1` (pg_dump custom, fuera del repo). Restore documentado históricamente. | Dump `C:\Temp\rotor-backups\` con SHA256. [`docs/operations/DATABASE_BACKUP_RESTORE.md`](../operations/DATABASE_BACKUP_RESTORE.md). | Mostrar el archivo `.dump` local; no restaurar encima de `rotor` en clase. |
| Aplicación web/local | PASS | Next.js `127.0.0.1:3000` + Laravel `127.0.0.1:8000`. E2E usa 3001/8001. | `npm run dev` / `php artisan serve`; Playwright webServer. | Recorrer el panel en el navegador local. |

## Pedidos — contrato dividido

| Operación | Actor | Endpoint / UI |
|---|---|---|
| CREATE | CUSTOMER | Carrito → checkout → `POST /api/orders` (`Idempotency-Key`). Éxito: `PENDING_PAYMENT`. |
| LIST | ADMIN | `/pedidos` → `GET /api/admin/orders` |
| SHOW | ADMIN | Diálogo detalle → `GET /api/admin/orders/{orderId}` |
| UPDATE | ADMIN | Diálogo edición (contacto/entrega) → `PATCH /api/admin/orders/{orderId}` |
| CANCEL | ADMIN | `PATCH` con `{ "status": "CANCELLED" }` |
| DELETE | ADMIN | Solo `CANCELLED` sin dependencias bloqueantes → `DELETE /api/admin/orders/{orderId}` |

No hay `POST /api/admin/orders`. El panel muestra explícitamente que la creación ocurre en el flujo transaccional del cliente.

## Fuera de este MVP (issue #9)

| Tema | Estado |
|---|---|
| Pasarela sandbox | PENDIENTE |
| Webhook firmado | PENDIENTE |
| Conciliación | PENDIENTE |
| Transición a `PAID` / `FULFILLED` por pago real sandbox | PENDIENTE |
