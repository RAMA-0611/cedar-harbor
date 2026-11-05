# Wompi Sandbox — demo local

## Qué es real

- Integración contra **Wompi Sandbox** (sin dinero real).
- Firma de integridad Web Checkout en **backend**.
- Webhook con validación dinámica de `signature.properties`.
- Settlement único (`PaymentSettlementService`) para webhook + reconcile.

## Credenciales

Coloque solo en `backend/.env` local (nunca en git/chat/PR):

```env
WOMPI_ENV=sandbox
WOMPI_BASE_URL=https://sandbox.wompi.co/v1
WOMPI_CHECKOUT_URL=https://checkout.wompi.co/p/
WOMPI_PUBLIC_KEY=pub_test_...
WOMPI_PRIVATE_KEY=prv_test_...
WOMPI_INTEGRITY_SECRET=...
WOMPI_EVENTS_SECRET=...
```

`backend/.env.example` solo tiene placeholders vacíos.

## Túnel HTTPS para webhook

Wompi necesita alcanzar el backend:

1. Levante un túnel temporal (Cloudflare Tunnel o ngrok) hacia `http://127.0.0.1:8000`.
2. Configure en el Dashboard Sandbox la URL:
   `https://<tunnel>/api/webhooks/payments/wompi`
3. **No** versionar URL ni token del túnel.

## Flujo demo

1. CUSTOMER crea pedido → `PENDING_PAYMENT`.
2. `/pedido/{id}` → payment-intent → Web Checkout sandbox.
3. Completar pago APPROVED en sandbox.
4. Webhook o `POST /api/orders/{id}/reconcile` → `PAID` + reserva `CONSUMED` + stock −qty exactamente una vez.

## Si faltan credenciales

`LIVE_WOMPI_SANDBOX=BLOCKED_CREDENTIALS`  
Los tests PHPUnit mockean la frontera y siguen pasando.
