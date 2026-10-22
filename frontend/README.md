# Frontend — Proyecto Rotor

Aplicación Next.js del MVP administrativo. El prototipo estático E2 permanece en `web/` y no es esta implementación.

## Requisitos

- Node.js 22.x
- npm
- API Laravel en `http://127.0.0.1:8000`

## Setup

```bash
cd frontend
copy .env.example .env.local
npm ci
npm run dev
```

Abrir `http://127.0.0.1:3000`.

Variables: `NEXT_PUBLIC_API_BASE_URL` (ver `.env.example`). No versionar secretos ni contraseñas.

## Scripts

- `npm run dev` — 127.0.0.1:3000
- `npm run lint`
- `npm run typecheck`
- `npm run build`
- `npm run start`

## Alcance MVP-3

Login Sanctum, shell ADMIN, dashboard con datos reales. CRUD visual de usuarios/productos/pedidos/categorías: pendiente.
