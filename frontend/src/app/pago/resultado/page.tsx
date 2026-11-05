"use client";

import { StorefrontShell } from "@/components/store/StorefrontShell";
import { useAuth } from "@/hooks/useAuth";
import { reconcileOrder } from "@/lib/api/checkout";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { Suspense, useEffect, useState } from "react";

function PaymentResultInner() {
  const search = useSearchParams();
  const router = useRouter();
  const { user, loading: authLoading } = useAuth();
  const [message, setMessage] = useState("Confirmando pago con Wompi…");
  const [orderId, setOrderId] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (authLoading) {
      return;
    }
    if (!user || user.role !== "CUSTOMER") {
      router.replace("/cliente/login?next=/pago/resultado");
      return;
    }

    const id = search.get("id") ?? search.get("transaction_id") ?? "";
    const reference = search.get("reference") ?? "";

    async function run() {
      try {
        // Never trust query string alone: backend reconcile/webhook is authoritative.
        if (id) {
          // Without order id in return URL, attempt localStorage hint set before checkout redirect.
          const hinted = globalThis.sessionStorage?.getItem("rotor.lastOrderId");
          if (hinted) {
            const result = await reconcileOrder(hinted, id);
            setOrderId(result.order.id);
            setMessage(`Estado: ${result.order.status} (${result.settlement.reason})`);
            return;
          }
        }
        if (reference) {
          setMessage(
            "Retorno recibido. Si el webhook está configurado, el pedido se actualizará automáticamente. También puede abrir el pedido y reconciliar.",
          );
          return;
        }
        setMessage("Retorno recibido. Esperando confirmación server-to-server.");
      } catch {
        setError("No se pudo reconciliar todavía. El webhook puede completar el asiento.");
      }
    }

    void run();
  }, [authLoading, router, search, user]);

  return (
    <div className="store-panel">
      <h1>Resultado de pago</h1>
      <p className="store-meta">
        La UI no marca PAID por sí sola. La autoridad es webhook/reconcile en Laravel.
      </p>
      {error ? <div className="store-alert store-alert-error">{error}</div> : null}
      <div className="store-alert store-alert-info">{message}</div>
      {orderId ? (
        <Link className="store-btn" href={`/pedido/${orderId}`}>
          Ver pedido
        </Link>
      ) : (
        <Link className="store-btn store-btn-secondary" href="/">
          Volver al catálogo
        </Link>
      )}
    </div>
  );
}

export default function PaymentResultPage() {
  return (
    <StorefrontShell>
      <Suspense fallback={<p>Confirmando…</p>}>
        <PaymentResultInner />
      </Suspense>
    </StorefrontShell>
  );
}
