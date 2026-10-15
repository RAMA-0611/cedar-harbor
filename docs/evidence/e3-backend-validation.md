# E3 — Backend validation evidence

Base SHA: `02c1609d7a05d906cf328b39bebe4b7c136cb5a1`

Values below correspond to that main tip (CRUD + Auth + ApiError + Catalog). They do **not** invent later PR results.

| Check | Result |
|---|---|
| PostgreSQL local `rotor` | PASS |
| `php artisan migrate:fresh --seed` | PASS |
| Auth Sanctum (login/logout/me) | PASS |
| ApiError contract | PASS |
| Admin CRUD ×4 | PASS |
| Public catalog | PASS |
| PHPUnit | **74 passed / 584 assertions** |
| Redocly CLI 2.57.0 | **0 errors** (localhost warning accepted) |
| Spectral CLI 6.16.3 | **0 errors / 0 warnings / 0 hints** |
| `php artisan route:list --path=api` | **25** routes |

Interactive docs delivery (this branch) adds `GET /docs` and `GET /docs/openapi.yaml` without changing business endpoints.
