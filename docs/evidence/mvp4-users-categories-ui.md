# MVP-4 — CRUD visual de usuarios y categorías

Base `main`: `a451f845f7d5860459e39fc3ad41708223cb7f71`. Backend intacto (`BACKEND_CHANGED=NO`).

## Alcance verificado

`/usuarios` y `/categorias` dejaron de ser placeholders PARTIAL. Cada operación usa el cliente API (`apiGet` / `apiMutation`) contra Laravel y PostgreSQL `rotor`.

`/productos` y `/pedidos` permanecen PARTIAL. No hay pagos, webhook, conciliación ni migraciones.

Playwright no forma parte de este incremento. El CRUD se verificó con smoke E2E manual (navegador + consulta PostgreSQL). No se afirma cobertura automatizada de frontend.

## Usuarios

| Operación | Evidencia |
|---|---|
| LIST | `GET /api/admin/users` → tabla real (Nombre, Correo, Rol, Creado, Acciones). Skeleton mientras carga. |
| CREATE | UI `Usuario Demo MVP4` / `usuario.mvp4.1761112847@example.test` / CUSTOMER → 201. PostgreSQL `users` id=3. |
| VIEW | `GET /api/admin/users/{id}` en diálogo de solo lectura. Sin password. |
| UPDATE | PATCH name → `Usuario Demo MVP4 Editado`. PostgreSQL coincidió. |
| DELETE | Confirmación «¿Eliminar al usuario …?». 204. Fila desaparece tras refetch. PostgreSQL: registro ausente. |
| Duplicate email | 422 `VALIDATION_ERROR`; mensaje junto al correo. |
| Password &lt; 8 | validación frontend; no se envía. |
| Self-delete | botón Eliminar deshabilitado en la fila del ADMIN autenticado. `DELETE` propio vía API → 403 `FORBIDDEN`. Cuenta admin intacta. |

Password nunca aparece en responses ni en la UI de consulta.

## Categorías

| Operación | Evidencia |
|---|---|
| LIST | `GET /api/admin/categories` → Nombre, Slug, Estado (Activa/Inactiva), Descripción, Acciones. |
| CREATE | `Categoría Demo MVP4` / `categoria-demo-mvp4-1761112847` / activa. PostgreSQL: existe, `is_active=true`. |
| VIEW | `GET /api/admin/categories/{id}` con los mismos datos. |
| UPDATE | name `Categoría Demo MVP4 Editada`, `is_active=false`. PostgreSQL coincidió. |
| DELETE | 204 cuando no hay productos. PostgreSQL: registro ausente. |
| RESOURCE_IN_USE | intento de borrar `proteccion-electrica` (3 productos). 409. Mensaje: «No se puede eliminar esta categoría porque está siendo utilizada por productos.» La categoría sigue existiendo. |
| Duplicate slug | crear con `rodamientos` → 422; error junto al slug. Sin registro duplicado. |

## Persistencia

Flujo comprobado: UI → Laravel API → PostgreSQL `rotor`.

Tras el smoke y la limpieza:

| Tabla | Conteo |
|---|---|
| users | 2 |
| categories | 7 |
| products | 12 |
| orders | 3 |

`DEVELOPMENT_DB_CHANGED=NO` salvo el ciclo controlado create/update/delete de registros sintéticos `@example.test`, revertido al finalizar.

Datos smoke: únicamente `example.test` / slugs sintéticos. Sin contraseñas en este documento.

## UI

- Confirmación modal accesible (`role=dialog`, `aria-modal`, Escape, focus). Botones Cancelar / Eliminar. Sin `window.confirm`.
- Feedback: «Usuario/Categoría creado|actualizado|eliminado correctamente.»
- Loading / empty / error con Reintentar. Sin delete optimista: la fila permanece hasta el 204 y el refetch.
- Tablas a 390 px: `overflow-x-auto` en el contenedor; `document.scrollWidth` = 390 (no hay overflow de página).
- Dashboard al volver a `/dashboard` reconsulta la API (usuarios 2, productos 12, categorías 7, pedidos 3).

## Calidad

| Gate | Resultado |
|---|---|
| `npm run lint` | PASS |
| `npm run typecheck` | PASS |
| `npm run build` | PASS (13 rutas estáticas) |
| Frontend unit tests | no añadidos (Vitest/RTL desproporcionado; Playwright posterior) |
| PHPUnit | 156 passed / 1403 assertions / 0 failures |
| `TEST_DATABASE` | `rotor_testing` |
| `DEVELOPMENT_DB_CHANGED` | NO |
| `BACKEND_CHANGED` | NO |
