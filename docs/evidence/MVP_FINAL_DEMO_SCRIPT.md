# Guion de demostración — V0.5 demo integrada

Entorno: PostgreSQL **`rotor`**. No usar `rotor_e2e` / `rotor_testing` delante del profesor.

## Preparación

1. `scripts/demo-start.ps1` (o Laravel `:8000` + Next `:3000` manual).
2. `php artisan storage:link` en backend (imágenes).
3. Credenciales Wompi sandbox solo en `.env` local si se demostrará pago live.
4. Túnel HTTPS → `/api/webhooks/payments/wompi` si se demostrará webhook.

## Recorrido (20–25 min)

1. Página pública `/` — catálogo real (`GET /api/products`).
2. Abrir un producto `/producto/{id}`.
3. ADMIN `/login` → `/productos`.
4. Crear/editar producto (precio, stock, activo) + imagen opcional.
5. Volver a `/` (Actualizar / foco) y mostrar sync.
6. Desactivar producto → desaparece del catálogo.
7. CUSTOMER `/cliente/login`.
8. Agregar al carrito real → `/carrito`.
9. `/checkout` → pedido `PENDING_PAYMENT`.
10. ADMIN `/pedidos` ve el pedido.
11. CUSTOMER `/pedido/{id}` → Pagar con Wompi Sandbox (si hay llaves).
12. Retorno `/pago/resultado` (UI no marca PAID sola).
13. Webhook/reconcile → `PAID`, reserva consumida, stock −qty una vez.
14. Mostrar stock en ADMIN y tienda.
15. pgAdmin con queries de `docs/evidence/V05_DATABASE_DEMO.md`.
16. Swagger `/docs`.
17. `/pruebas` + mencionar PHPUnit/Playwright.
18. Mostrar tiempos de `scripts/demo-start.ps1`.

## No hacer

- `migrate:fresh` sobre `rotor`.
- Dinero real / Wompi producción.
- Modificar `web/` E2.
- Mostrar secrets.
