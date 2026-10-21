# MVP-0 — Plan de implementación (no ejecutar aún)

**Partida estable:** `v0.3.0` / `b221dd749782d03ede1cfdacf534c77991eb83b6`  
**Issue de auditoría:** #28  

Regla: cada fase es un PR pequeño. No mezclar contrato Orders admin con UI. No reescribir `OrderService` transaccional para “simplificar CRUD”.

**Bloqueo:** no iniciar Orders admin, frontend, nuevas migraciones ni nuevas suites hasta completar MVP-1 (aislamiento de DB de pruebas).

## MVP-1 — Aislar base de datos de pruebas

- **Owner sugerido:** backend / ops
- **Dependencias:** dump MVP-0 verificado; `v0.3.0` intacto
- **Riesgo:** alto si se omite: `RefreshDatabase` sigue destruyendo `rotor`
- **Alcance:** PostgreSQL `rotor_testing` separado; PHPUnit apunta explícitamente a esa DB; safety guard contra suite destructiva sobre `rotor`. No Orders admin, no frontend, no nuevas migraciones de producto.
- **Tests:** suite completa verde **solo** en `rotor_testing`; verificar que conteos de `rotor` no cambian
- **DONE:**
  1. Existe PostgreSQL DB separada `rotor_testing`.
  2. PHPUnit usa explícitamente `DB_CONNECTION=pgsql` y `DB_DATABASE=rotor_testing`.
  3. Safety guard impide ejecutar la suite destructiva contra `rotor`.
  4. `php artisan test` no modifica conteos de tablas de `rotor`.
  5. Suite completa verde en `rotor_testing`.
  6. Backup de `rotor` ya no es necesario para ejecutar tests normales.
  7. README / documentación de tests actualizados.

## MVP-2 — Contrato Orders admin (sin UI)

- **Owner sugerido:** backend (Harold / API)
- **Dependencias:** MVP-1; v0.3.0 intacto; OpenAPI actual 34 ops
- **Riesgo:** alto si se rompe ownership CUSTOMER o idempotencia
- **Alcance:** definir `GET /api/admin/orders`, detail admin, transiciones de estado permitidas. No pagos.
- **Tests:** Feature admin orders 401/403 + list + show; no regresiones `CreateOrderTest` (sobre `rotor_testing`)
- **DONE:** OpenAPI + rutas + tests verdes; customer flow sigue igual

## MVP-3 — Next.js foundation + Auth + layout

- **Owner sugerido:** frontend
- **Dependencias:** MVP-1; LOGIN backend PASS
- **Riesgo:** medio (cookies CSRF/Sanctum cross-origin `:3000` → `:8000`)
- **Alcance:** app en `frontend/`, layout admin, login/logout/me, guards ADMIN
- **Tests:** smoke login session; PHPUnit intacto
- **DONE:** usuario ADMIN inicia sesión y ve shell vacío; CUSTOMER no entra al admin

## MVP-4 — Dashboard

- **Owner sugerido:** frontend + endpoint mínimo backend si hace falta
- **Dependencias:** MVP-3
- **Riesgo:** bajo/medio (no inventar métricas sin query)
- **Alcance:** conteos reales (users, products, orders by status, stock bajo) o empty states honestos
- **Tests:** Feature del endpoint si se crea; UI smoke
- **DONE:** dashboard lee datos reales PostgreSQL, no JSON local

## MVP-5 — Users UI

- **Owner sugerido:** frontend
- **Dependencias:** MVP-3; backend Users PASS
- **Riesgo:** bajo
- **Alcance:** list/create/edit/delete; nunca mostrar password; bloquear self-delete en UI además del 403
- **Tests:** PHPUnit existente; Playwright mínimo create+list
- **DONE:** CRUD Users visible contra API real

## MVP-6 — Products UI

- **Owner sugerido:** frontend
- **Dependencias:** MVP-3; Categories existen
- **Riesgo:** medio (decimales, stock, 409 RESOURCE_IN_USE)
- **Alcance:** 5 operaciones; precio/impuesto como string decimal; FK category
- **Tests:** ProductCrudTest + UI
- **DONE:** alta/edición persiste en PostgreSQL; delete en uso muestra conflicto

## MVP-7 — Categories UI

- **Owner sugerido:** frontend
- **Dependencias:** MVP-3
- **Riesgo:** bajo
- **Alcance:** slug unique; 409 si tiene productos
- **Tests:** CategoryCrudTest + UI
- **DONE:** CRUD categorías operativo en admin

## MVP-8 — Orders UI

- **Owner sugerido:** frontend + backend MVP-2
- **Dependencias:** MVP-2 obligatorio
- **Riesgo:** alto
- **Alcance:** listado/detalle admin; **no** fingir UPDATE/DELETE si el contrato no los tiene; cancel/estado solo según reglas
- **Tests:** admin + customer regression
- **DONE:** profesor puede ver pedidos reales; se documenta que no es CRUD anémico

## MVP-9 — Docs + Tests pages (rúbrica)

- **Owner sugerido:** docs/frontend
- **Dependencias:** `/docs` Laravel ya PASS
- **Riesgo:** bajo
- **Alcance:** página(s) del producto que enlacen OpenAPI, evidencia de pruebas, no duplicar YAML
- **Tests:** DocsSmokeTest sigue verde
- **DONE:** rúbrica DOCUMENTACIÓN API y PRUEBAS visibles en el entregable UI

## MVP-10 — Backup/restore en operación de equipo

- **Owner sugerido:** ops (Raúl / scripts)
- **Dependencias:** scripts MVP-0
- **Riesgo:** medio (CREATEDB denegado hoy al rol `rotor`)
- **Alcance:** superuser crea DBs temporales **o** `GRANT CREATEDB`; práctica de restore aislado; nunca dump en git
- **Tests:** ejecutar `db-backup.ps1` + restore a DB temporal en máquina de demo
- **DONE:** runbook usado por el equipo; restore aislado MATCH

## MVP-11 — Playwright + responsive + release

- **Owner sugerido:** QA + frontend
- **Dependencias:** MVP-3..8
- **Riesgo:** medio
- **Alcance:** E2E login+CRUD+orders; viewport desktop/móvil; tag de release **nuevo** (no mover `v0.3.0`)
- **Tests:** Playwright en `tests/e2e`; PHPUnit 133+ sigue
- **DONE:** evidencia E2E; README actualizado; `v0.3.0` sin cambios

## Fuera de este plan (backlog existente)

Issue **#9**: payment-intent, webhook, conciliación, transición a `PAID`. No mezclar con UI CRUD del profesor.

## Orden recomendado

`MVP-1` (aislamiento de tests) es **primero** y bloquea el resto.  
`MVP-2` (Orders admin contract) no empieza sin `MVP-1`.  
`MVP-3` (Next.js) no empieza sin `MVP-1`.  
`MVP-8` (Orders UI) no empieza sin `MVP-2`.  
`MVP-4..7` pueden serializarse Users → Categories → Products para tener FK real en UI.  
No implementar estas fases en el PR de MVP-0.
