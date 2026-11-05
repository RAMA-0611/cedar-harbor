# V0.5 — Explicación para exposición (lenguaje estudiantil)

## Arquitectura

```text
Browser (tienda o ADMIN)
  → Next.js (:3000)
  → Laravel API (:8000)
  → PostgreSQL (rotor)
```

Misma API y misma base para tienda pública y panel ADMIN: por eso un producto creado en ADMIN aparece en el catálogo.

## Roles

- **ADMIN:** gestiona usuarios, categorías, productos, pedidos; no crea pedidos de cliente.
- **CUSTOMER:** carrito, checkout y pago sandbox.

## Seguridad del login

Sanctum con cookies de sesión, CSRF en mutaciones, regeneración de sesión y control por rol (`role:ADMIN` / `role:CUSTOMER`).

## Carrito y stock

Al agregar al carrito se crea una **reserva ACTIVE**. El stock físico aún no baja; `available_quantity = stock - reservas activas`.

## Pedido

`POST /api/orders` congela precios en ítems (snapshot) y deja el pedido en `PENDING_PAYMENT`.

## Pago Wompi Sandbox

1. Backend crea payment-intent y firma de integridad.
2. El navegador abre Web Checkout sandbox (sin secrets).
3. Wompi notifica por webhook firmado (o se usa reconcile).
4. Solo con evidencia válida → `PAID`, reserva `CONSUMED`, stock −qty **exactamente una vez**.

## Qué es real / qué no

- Real: integración sandbox, API, PostgreSQL, ADMIN↔tienda.
- No: dinero productivo, Wompi producción, JSON/localStorage como fuente de verdad.

## Tres bases

| Base | Uso |
|------|-----|
| `rotor` | desarrollo/demo |
| `rotor_testing` | PHPUnit |
| `rotor_e2e` | Playwright |

## `web/`

Histórico E2 inmutable. La tienda vigente está en `frontend/`.
