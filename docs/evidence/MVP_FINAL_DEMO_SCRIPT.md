# Guion de demostración — MVP integrado (10–15 min)

Entorno de presentación: PostgreSQL **`rotor`** (desarrollo/demo). No usar `rotor_e2e` ni `rotor_testing` delante del profesor.

No leer ni proyectar passwords. Las cuentas de demo se crean al momento o se reutilizan si ya existen en `rotor`.

## Preparación (antes de clase)

1. PostgreSQL 16 en `127.0.0.1:5432`. Base `rotor` con migraciones y `CatalogSeeder`.
2. API: `cd backend && php artisan serve --host=127.0.0.1 --port=8000`.
3. Frontend: `cd frontend && npm run dev` → `http://127.0.0.1:3000`.
4. Tener un ADMIN conocido en `rotor` (creado por UI o tinker local; no versionar la clave).
5. Opcional: un CUSTOMER con perfil para mostrar CREATE de pedido por API (Postman/Swagger o `curl` local). Si no hay tiempo, explicar el contrato y mostrar un pedido ya existente.
6. Backup reciente fuera del repo (`scripts/db-backup.ps1`) por si hace falta evidenciar operación, no para restaurar en vivo.

Limpieza posterior: eliminar solo registros sintéticos creados en la demo (`example.test`). No vaciar `rotor`.

## Recorrido

1. **Login (1 min).** Abrir `/login`. Mostrar error con credencial inválida («Correo electrónico o contraseña incorrectos.»). Entrar como ADMIN. Queda `/dashboard`.
2. **Dashboard (1 min).** Cuatro tarjetas con enteros reales. Mencionar que no hay mocks. Recorrer el menú lateral.
3. **Usuarios (2 min).** `/usuarios`. Crear un `CUSTOMER` sintético, verlo, editar el nombre, intentar eliminar la cuenta propia (deshabilitado), eliminar el sintético.
4. **Categorías (1–2 min).** Crear una categoría vacía. Intentar eliminar «Rodamientos» → mensaje de recurso en uso. Eliminar la categoría vacía.
5. **Productos (2 min).** Crear un SKU sintético (categoría Motores, precio COP, IVA 19 %, stock). Editar el nombre. Eliminar el sintético. Dejar el catálogo seed intacto.
6. **Pedidos listado (1 min).** `/pedidos`. Leer el aviso: la creación no ocurre en el panel. Buscar / filtrar si hay pedidos.
7. **CREATE CUSTOMER (1–2 min).** Explicar (y, si está preparado, ejecutar) `POST /api/cart/items` + `POST /api/orders` con `Idempotency-Key` como CUSTOMER. Resultado: `PENDING_PAYMENT`. Recargar `/pedidos` y señalar el `order_number` (`RTR-…`).
8. **Editar pedido (1 min).** Cambiar teléfono o modo de entrega. Totales e ítems no se editan aquí.
9. **Cancelar / eliminar (1 min).** Solo sobre el pedido sintético de la demo: cancelar → `CANCELLED`; entonces eliminar. No tocar pedidos históricos de `rotor`.
10. **PostgreSQL (30 s).** `SELECT current_database();` debe ser `rotor`. Mostrar que el pedido sintético ya no está (o está cancelado, según el paso 9).
11. **Swagger (1 min).** `http://127.0.0.1:8000/docs`. 38 operaciones. Señalar `POST /api/orders` vs ausencia de `POST /api/admin/orders`.
12. **Pruebas (30 s).** PHPUnit: `cd backend && php artisan test` usa `rotor_testing`. Playwright: `cd frontend && npm run test:e2e` usa `rotor_e2e` con guard que aborta si la base no es `rotor_e2e`.
13. **Cierre (30 s).** MVP académico cubierto. Pagos sandbox / webhook / conciliación = issue **#9**, no afirmados como hechos.

## Lo que no se hace en la demo

- `migrate:fresh` sobre `rotor`.
- Conceder `CREATEDB` al rol `rotor`.
- Mostrar `.env`, `.env.e2e` o passwords.
- Afirmar «ecommerce completo» o transición a `PAID` por pasarela.
