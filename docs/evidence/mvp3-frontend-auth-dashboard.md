# MVP-3 — Frontend base, autenticación y dashboard

## Versiones

| Componente | Valor |
|---|---|
| Node | v22.23.2 |
| npm | 10.9.8 |
| Next.js | 16.4.2 |
| React / react-dom | 19.3.0 |
| TypeScript | ^5 |
| Tailwind CSS | ^4 |
| Base SHA | `fd0a422858053e068a90cd24a4e985ccecca3b34` |

## Setup local

Terminal 1: PostgreSQL 16 con base `rotor`.

Terminal 2:

```bash
cd backend
php artisan serve --host=127.0.0.1 --port=8000
```

Terminal 3:

```bash
cd frontend
copy .env.example .env.local
npm ci
npm run dev
```

Abrir `http://127.0.0.1:3000`. No documentar contraseñas.

`NEXT_PUBLIC_API_BASE_URL` se lee desde `frontend/.env.local` (no versionado) con ejemplo en `frontend/.env.example`.

## Rutas frontend

| Ruta | Comportamiento |
|---|---|
| `/` | guest → `/login`; ADMIN → `/dashboard`; CUSTOMER → `/acceso-denegado` |
| `/login` | formulario Sanctum |
| `/dashboard` | totales reales ADMIN |
| `/usuarios` `/productos` `/pedidos` `/categorias` | shell + módulo PARTIAL (sin CRUD visual) |
| `/documentacion` | enlaces a `/docs` y OpenAPI YAML del backend |
| `/pruebas` | texto PHPUnit + ping no destructivo `GET /api/products` |
| `/acceso-denegado` | CUSTOMER autenticado sin panel admin |

Ninguna de esas rutas devolvió 404 en smoke.

## Auth flow

1. `GET /sanctum/csrf-cookie` con `credentials: include`.
2. Cookie `XSRF-TOKEN` leída en memoria, `decodeURIComponent`, enviada como `X-XSRF-TOKEN` en mutaciones.
3. `POST /api/auth/login`, `POST /api/auth/logout`, `GET /api/auth/me`.
4. Usuario solo en estado React (`AuthProvider`). Sin localStorage/sessionStorage para auth.
5. Al montar: `/api/auth/me`. 200 = sesión; 401 = guest (sin error crítico).

## Role flow

- ADMIN → `/dashboard`
- CUSTOMER autenticado → `/acceso-denegado` (no se convierte en 401)
- guest en rutas admin → `/login`
- Mientras se resuelve `/me`, skeleton. No hay flash de contenido admin.

## Dashboard sources

Consultas reales como ADMIN:

- `GET /api/admin/users` → `data.length`
- `GET /api/admin/products` → `data.length`
- `GET /api/admin/categories` → `data.length`
- `GET /api/admin/orders?page=1&per_page=1` → `meta.total`

Smoke local coincidió con PostgreSQL `rotor`: usuarios 2, productos 12, categorías 7, pedidos 3.

Si una tarjeta falla, las demás siguen y hay reintento.

## CORS / Sanctum

Sin cambios de backend. Ya estaban:

- `CORS_ALLOWED_ORIGINS` con `http://127.0.0.1:3000` y `http://localhost:3000`
- `supports_credentials=true`
- paths `api/*` y `sanctum/csrf-cookie`
- `SANCTUM_STATEFUL_DOMAINS` incluye `127.0.0.1:3000` y `localhost:3000`

## Smoke

| Caso | Resultado |
|---|---|
| guest `/` | `/login` |
| login inválido | alerta de credenciales, sin stack |
| login ADMIN | `/dashboard` con totales reales |
| session recovery | reload de ruta protegida vuelve a `/me` y mantiene ADMIN |
| CUSTOMER | `/acceso-denegado`; `GET /api/admin/users` → 403 `FORBIDDEN` |
| logout | `/login`; `/dashboard` vuelve a `/login` |
| Swagger | `GET http://127.0.0.1:8000/docs` 200 |
| localStorage/sessionStorage | sin claves de auth |
| cookies JS visibles | solo nombre `XSRF-TOKEN` (valor no capturado); sesión HttpOnly |

## Calidad

| Gate | Resultado |
|---|---|
| `npm run lint` | PASS |
| `npm run typecheck` | PASS |
| `npm run build` | PASS (13 rutas estáticas) |
| Frontend unit tests | no añadidos (Vitest/RTL se consideró desproporcionado para MVP-3; obligatorio cubierto por lint/typecheck/build + smoke) |
| PHPUnit | 156 passed / 1403 assertions / 0 failures |
| `TEST_DATABASE` | `rotor_testing` |
| `DEVELOPMENT_DB_CHANGED` | NO |

## Seguridad

- No token/sesión/password/XSRF en localStorage.
- `credentials: include` + CSRF real.
- CORS sin wildcard con credentials.
- Rol ADMIN comprobado en UI; backend sigue siendo autoridad.
- Password `type=password`.
- Sin credenciales en el frontend versionado.
- Sin stack traces en `error.tsx`.
- Enlaces externos Swagger: `noopener noreferrer`.
- Sin `dangerouslySetInnerHTML`.

## Limitaciones explícitas (PARTIAL)

- CRUD visual de usuarios, productos, pedidos y categorías: no implementado.
- Dashboard no desglosa PENDING_PAYMENT/CANCELLED (cuatro totales bastan).
- Sin Playwright / Vitest en esta fase.
- `web/` permanece como prototipo histórico E2; no es el frontend MVP.
