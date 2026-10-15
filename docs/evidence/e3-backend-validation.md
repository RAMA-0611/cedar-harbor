# E3 — Backend validation evidence

## Main con carrito (base integrada)

| Campo | Valor |
|---|---|
| `MAIN_SHA` | `7cb34f65d9de37eba204a8d30c93ffeffd6898a4` |
| Origen | Merge PR #23 (`feat/e3-cart-reservations`) en `main` |

Valores medidos en la rama `docs/e3-interactive-api` **después** de `git merge origin/main` (incluye 2 tests Docs de #24):

| Check | Result |
|---|---|
| PostgreSQL local `rotor` | PASS |
| `php artisan migrate:fresh --seed` | PASS |
| Auth Sanctum (login/logout/me) | PASS |
| ApiError contract | PASS |
| Admin CRUD ×4 | PASS |
| Public catalog | PASS |
| Cart + reservas | PASS |
| Interactive docs (`/docs`, `/docs/openapi.yaml`) | PASS |
| `PHPUNIT_TESTS` / `ASSERTIONS` | **93 passed / 774 assertions** |
| Docs Feature | **2 passed / 9 assertions** |
| `API_ROUTES` | **29** |
| Redocly CLI 2.57.0 (`REDOCLY`) | **0 errors** (warning `no-server-example-com` accepted) |
| Spectral CLI 6.16.3 (`SPECTRAL`) | **0 errors / 0 warnings / 0 hints** |

> Las cifras 74/584 correspondían a un tip anterior de `main` (CRUD sin carrito) y **no** son la referencia del estado integrado actual.
