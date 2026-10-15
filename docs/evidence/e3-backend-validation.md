# E3 — Backend validation evidence (cierre académico)

## Base

| Campo | Valor |
|---|---|
| `BASE_MAIN_SHA` | `1fb6e65571b50f6a560a6cab400678f50164d5ea` |
| Rama | `docs/e3-finalize` |
| Origen | Main integrado con cart + checkout + orders `PENDING_PAYMENT` (PR #25) |

## Mediciones en esta rama (valores reales)

| Check | Result |
|---|---|
| PostgreSQL local `rotor` | **PASS** |
| `php artisan migrate:fresh --seed` | **PASS** |
| Auth Sanctum | **PASS** |
| ApiError | **PASS** |
| Admin CRUD ×4 | **PASS** |
| Catalog | **PASS** |
| Cart | **PASS** |
| Reservations | **PASS** |
| Checkout | **PASS** |
| Order `PENDING_PAYMENT` | **PASS** |
| Idempotency | **PASS** |
| Rollback transactional | **PASS** |
| Interactive docs (`/docs`) | **PASS** |
| `PHPUNIT_TESTS` / `ASSERTIONS` | **133 passed / 1093 assertions** |
| Docs Feature | **2 passed / 9 assertions** |
| `API_ROUTES` | **34** |
| `OPENAPI_OPERATIONS` | **34** (Auth 3 + Catalog 2 + Admin 20 + Cart 4 + Checkout 1 + Orders 4) |
| Redocly CLI 2.57.0 | **0 errors** (warning localhost accepted) |
| Spectral CLI 6.16.3 | **0 errors / 0 warnings / 0 hints** |
| Payment sandbox / webhook / reconcile | **NOT IMPLEMENTED** — issue #9 (not an E3 failure) |

## Contrato activo vs histórico

- Contrato E3 activo: **34** operaciones documentadas = **34** rutas API.
- Endpoints `#9` (payment-intent, webhook, reconcile) **retirados** del OpenAPI activo.
- Tag `v0.2.0` conserva el contrato histórico E2 (14 operations).

## Evidencia histórica (no vigente)

| Hito | MAIN / suite | Rutas |
|---|---|---|
| Post-cart (#23), pre-docs-cart merge | `7cb34f6` · 91/765 | 29 |
| Docs branch mid-E3 | 93/774 | 29 |
| Pre-cierre E3 (orders en main) | `1fb6e65` · 133/1093 | 34 |

Estas filas son snapshots anteriores; la referencia vigente es la tabla de mediciones de esta rama.
