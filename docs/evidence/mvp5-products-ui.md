# MVP-5 — CRUD visual de productos

Base `main`: `23abf09fae76d6497be211bdc29df96ebe5f53a7`. Backend intacto (`BACKEND_CHANGED=NO`).

## Alcance verificado

`/productos` dejó de ser placeholder PARTIAL. Cada operación usa el cliente API (`apiGet` / `apiMutation`) contra Laravel y PostgreSQL `rotor`.

`/pedidos` permanece PARTIAL. No hay pagos, webhook, conciliación ni migraciones.

Playwright no forma parte de este incremento. El CRUD se verificó con smoke E2E manual (navegador + consulta PostgreSQL). No se afirma cobertura automatizada de frontend.

## Productos

| Operación | Evidencia |
|---|---|
| LIST | `GET /api/admin/products` → tabla real (SKU, Producto, Categoría, Precio, Stock, Estado, Acciones). Skeleton mientras carga. |
| CREATE | UI SKU `MVP5-PROD-1792646041`, nombre `Producto Demo MVP5`, categoría Rodamientos, precio `100000.00`, impuesto 19 %, stock 10, activo. 201. PostgreSQL: fila existe. |
| VIEW | `GET /api/admin/products/{id}` en diálogo de solo lectura. Categoría por nombre, precio COP, impuesto `19 %`. |
| UPDATE | PATCH nombre `Producto Demo MVP5 Editado`, categoría Conexión, precio `105000.00`, impuesto 5 %, stock 9, inactivo. PostgreSQL coincidió. |
| DELETE | Confirmación «¿Eliminar el producto …?». 204. Fila desaparece tras refetch. PostgreSQL: registro ausente. |
| Duplicate SKU | crear con `PR-CAB-3X10` → 422; mensaje junto al SKU: «Este SKU ya está registrado.» Sin fila extra (12 productos). |
| RESOURCE_IN_USE | intento de borrar `Cable de potencia 3x10 AWG THHN ROJO` (referenciado). 409. Mensaje: «No se puede eliminar este producto porque está siendo utilizado por información del sistema.» El producto sigue existiendo. |

## Relación de categoría

El resource de producto expone `category_id` (UUID). El frontend carga `GET /api/admin/categories` y construye `Map<category_id, category>` para mostrar el nombre. Si no hay match: «Categoría no disponible». Sin crash.

Selector del formulario: categorías reales de PostgreSQL. Inactivas se etiquetan «Nombre (Inactiva)» y siguen siendo seleccionables.

## Precio, impuesto y stock

- `price` se conserva como string decimal. Display con `formatCurrencyCOP()` (`es-CO`). CREATE persistió `100000.00`; UPDATE persistió `105000.00`.
- Formulario pide **Impuesto (%)**. `19` → payload `0.1900`. Al editar, `0.1900` → campo `19`. UPDATE 5 % → PostgreSQL `0.0500`.
- `stock_quantity` entero ≥ 0. UPDATE 10 → 9 coincidió en PostgreSQL.
- Moneda fija COP (sin selector de una sola opción).

Validación frontend comprobada: nombre vacío, precio negativo, impuesto 101 %, stock decimal `1.5`.

## Persistencia

Flujo comprobado: UI → Laravel API → PostgreSQL `rotor`.

Conteos pre-smoke y post-smoke (cleanup del sintético incluido):

| Tabla | Pre | Post |
|---|---|---|
| users | 2 | 2 |
| categories | 7 | 7 |
| products | 12 | 12 |
| orders | 3 | 3 |

`POST_SMOKE_COUNTS=PRE_SMOKE_COUNTS`. `DEVELOPMENT_DB_CHANGED=NO` salvo el ciclo controlado create/update/delete del SKU sintético `MVP5-PROD-*`, revertido al finalizar.

Datos smoke: únicamente SKU/nombre sintéticos. Sin secretos en este documento.

## UI

- Confirmación modal accesible (`role=dialog`, `aria-modal`, Escape, focus). Botones Cancelar / Eliminar. Sin `window.confirm`.
- Feedback: «Producto creado|actualizado|eliminado correctamente.»
- Loading / empty / error con Reintentar. Sin delete optimista: la fila permanece hasta el 204 y el refetch.
- Si fallan las categorías al abrir el formulario: no se permite guardar; mensaje «No se pudieron cargar las categorías.» y Reintentar.
- Tablas a 390 px: `overflow-x-auto` en el contenedor; `document.scrollWidth` = 390 (no hay overflow de página). Diálogo `max-h` dentro del viewport; campos apilados.
- Viewports: 1366×768, 1024×768, 768×1024, 390×844 — sin overflow global.
- Dashboard al volver a `/dashboard` reconsulta la API (usuarios 2, productos 12, categorías 7, pedidos 3).

## Calidad

| Gate | Resultado |
|---|---|
| `npm run lint` | PASS (tras implementación; gate final en el PR) |
| `npm run typecheck` | PASS |
| `npm run build` | PASS esperado (13 rutas estáticas) |
| Frontend unit tests | no añadidos (Vitest/RTL desproporcionado; Playwright posterior) |
| PHPUnit | 156 passed / 1403 assertions / 0 failures (baseline; gate final en el PR) |
| `TEST_DATABASE` | `rotor_testing` |
| `DEVELOPMENT_DB_CHANGED` | NO |
| `BACKEND_CHANGED` | NO |
