"use client";

import { StorefrontShell } from "@/components/store/StorefrontShell";
import { useAuth } from "@/hooks/useAuth";
import { createPaymentIntent, getOrder, getPaymentConfig } from "@/lib/api/checkout";
import { storeErrorMessage } from "@/lib/api/errors";
import { formatCop } from "@/lib/productImage";
import type { PaymentConfig, PaymentIntent, StoreOrder } from "@/types/store";
import { useParams, useRouter } from "next/navigation";
import { useEffect, useRef, useState } from "react";

export default function OrderDetailPage() {
  const params = useParams<{ id: string }>();
  const { user, loading: authLoading } = useAuth();
  const router = useRouter();
  const [order, setOrder] = useState<StoreOrder | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [intent, setIntent] = useState<PaymentIntent | null>(null);
  const [busy, setBusy] = useState(false);
  const [config, setConfig] = useState<PaymentConfig | null>(null);
  const formRef = useRef<HTMLFormElement>(null);

  useEffect(() => {
    if (authLoading) {
      return;
    }
    if (!user || user.role !== "CUSTOMER") {
      router.replace(`/cliente/login?next=/pedido/${params.id}`);
      return;
    }
    void getOrder(params.id)
      .then(setOrder)
      .catch((caught) => setError(storeErrorMessage(caught, "No se pudo cargar el pedido.")));
    void getPaymentConfig()
      .then(setConfig)
      .catch(() => setConfig(null));
  }, [authLoading, params.id, router, user]);

  useEffect(() => {
    if (intent && formRef.current) {
      formRef.current.submit();
    }
  }, [intent]);

  const handlePay = async () => {
    setBusy(true);
    setError(null);
    try {
      const next = await createPaymentIntent(params.id);
      if (next.provider === "SIMULATED") {
        router.push(`/pago/simulado/${params.id}`);
        return;
      }
      setIntent(next);
    } catch (caught) {
      setError(storeErrorMessage(caught, "No se pudo iniciar el pago."));
    } finally {
      setBusy(false);
    }
  };

  return (
    <StorefrontShell>
      <section className="store-hero">
        <h1>Pedido</h1>
        <p>Estado sincronizado con PostgreSQL y visible en ADMIN.</p>
      </section>
      {error ? <div className="store-alert store-alert-error">{error}</div> : null}
      {order ? (
        <div className="store-panel">
          <p>
            <strong>{order.order_number}</strong> · {order.status}
          </p>
          <p className="store-meta">Total {formatCop(order.total)}</p>
          <ul>
            {order.items.map((item) => (
              <li key={item.id}>
                {item.product_name} × {item.quantity} — {formatCop(item.line_total)}
              </li>
            ))}
          </ul>
          {order.status === "PENDING_PAYMENT" ? (
            <button
              type="button"
              className="store-btn store-btn-accent"
              disabled={busy}
              onClick={() => void handlePay()}
            >
              {busy
                ? "Preparando pago…"
                : config?.simulated
                  ? "Pagar (pasarela simulada)"
                  : "Pagar con Wompi Sandbox"}
            </button>
          ) : null}
          {order.status === "FAILED" ? (
            <div className="store-alert store-alert-error">
              El pago no se completó. Las unidades reservadas volvieron al inventario.
            </div>
          ) : null}
          {order.status === "PAID" ? (
            <div className="store-alert store-alert-ok">Pago confirmado. Stock liquidado una sola vez.</div>
          ) : null}
        </div>
      ) : null}

      {intent ? (
        <form ref={formRef} action={intent.checkout_url} method="GET">
          <input type="hidden" name="public-key" value={intent.public_key} />
          <input type="hidden" name="currency" value={intent.currency} />
          <input type="hidden" name="amount-in-cents" value={String(intent.amount_in_cents)} />
          <input type="hidden" name="reference" value={intent.reference} />
          <input type="hidden" name="signature:integrity" value={intent.integrity_signature} />
          {intent.redirect_url ? (
            <input type="hidden" name="redirect-url" value={intent.redirect_url} />
          ) : null}
        </form>
      ) : null}
    </StorefrontShell>
  );
}
