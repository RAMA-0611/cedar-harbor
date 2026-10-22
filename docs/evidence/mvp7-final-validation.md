# MVP-7 — Validación final del MVP integrado

## Base

| Dato | Valor |
|---|---|
| Repo | `RAMA-0611/cedar-harbor` |
| Branch | `test/mvp7-e2e-final-validation` |
| Base `main` | `e3f22d725de9fd9acceb60d3aa19a94aa135bee7` |
| `v0.3.0` | `b221dd749782d03ede1cfdacf534c77991eb83b6` (intacto) |
| Issue | #42 |
| VERSION_CANDIDATE | `v0.4.0` |
| TAG_CREATED | NO |

## Stack verificado

| Componente | Versión |
|---|---|
| Node | 22.23.2 |
| npm | 10.9.8 |
| Next.js | 16.4.2 |
| React | 19.3.0 |
| Playwright | 1.64.1 |
| Chromium | Playwright v1248 (Chrome for Testing 156.0.8078.4) |
| PHP | 8.2.12 |
| Laravel | 12.69.2 |
| PostgreSQL | 16.15 local (`127.0.0.1:5432`) |

## Aislamiento de bases

| Base | Uso | Reset destructivo |
|---|---|---|
| `rotor` | desarrollo / demo manual | NO en tests |
| `rotor_testing` | PHPUnit | `RefreshDatabase` + `TestingDatabaseGuard` |
| `rotor_e2e` | Playwright | `php artisan e2e:reset` solo si `APP_ENV=e2e` y `current_database()=rotor_e2e` |

Rol `rotor`: `rolcreatedb=false`, `rolsuper=false`. Owner de `rotor_e2e`: `rotor`.

Guard negativo (CLI sobre `.env` local / PHPUnit sobre `rotor_testing`): `Refusing E2E reset outside rotor_e2e.`

Guard positivo: `E2E_GUARD=PASS` `CURRENT_DATABASE=rotor_e2e`.

Conteos de `rotor` PRE/POST de los resets E2E: users=2, customers=2, categories=7, products=12, carts=3, cart_items=3, stock_reservations=3, orders=3, order_items=3. `DEVELOPMENT_DB_CHANGED=NO`.

## Calidad

| Gate | Resultado |
|---|---|
| `npm run lint` | PASS |
| `npm run typecheck` | PASS |
| `npm run build` | PASS |
| PHPUnit | 162 tests, 1416 assertions, 0 failures (`rotor_testing`) |
| Playwright run 1 | 11 passed, 0 failed, 0 skipped |
| Playwright run 2 | 11 passed, 0 failed, 0 skipped |
| API implementada | 38 (sin contar `PUT` de `apiResource`) |
| OpenAPI operations | 38 |
| PARITY | PASS |
| Redocly | 0 errors, 1 warning (`no-server-example.com` localhost) |
| Spectral | 0 errors |
| Swagger `GET /docs` | 200 |

PHPUnit sube desde el baseline 156/1403 por 6 pruebas del guard E2E (5 unit + 1 feature). Sin cambios de reglas de negocio.

## Playwright

- `workers=1`, `fullyParallel=false`
- webServer Laravel `127.0.0.1:8001` + Next.js `127.0.0.1:3001`
- Seed: `CatalogSeeder` + `E2eSeeder` (ADMIN/CUSTOMER desde `.env.e2e` local, gitignored)
- Specs: auth, dashboard, users, categories, products, orders (CUSTOMER CREATE vía API + ciclo ADMIN), responsive
- CREATE de pedidos: `POST /api/cart/items` + `POST /api/orders` con `Idempotency-Key`; el panel no ofrece «Nuevo pedido»

## Backup

| Campo | Valor |
|---|---|
| Path | `C:\Temp\rotor-backups\rotor_mvp0_20261022_085214.dump` |
| Size | 56166 bytes |
| SHA256 | `E97FD49215B0C4EDD8D5BE93873A12B24EDF281D17C5D7A440018FF3A05F239C` |
| Database | `rotor` |
| Host | `127.0.0.1` |

No se versionó el dump. No se restauró encima de `rotor`.

## Confidencialidad

Scan del diff y archivos nuevos: sin BEO / Bobinados / Electro Oriente / SIG-BEO / World Office / beoriente / Tailscale / IPs privadas / passwords / dumps. `127.0.0.1` y `example.test` permitidos.

Git no trackea `backend/.env`, `backend/.env.testing`, `backend/.env.e2e`, `frontend/.env.local`, `playwright-report`, `test-results`.

## Lógica de negocio

`BACKEND_BUSINESS_LOGIC_CHANGED=NO`. Sin cambios en controllers/services de dominio, `routes/api.php`, migraciones ni `api/openapi.yaml`. `web/` sin cambios.

## Pendiente

Issue **#9**: pasarela sandbox, webhook, conciliación. No cerrado. No se afirma `PAID` por pasarela.
