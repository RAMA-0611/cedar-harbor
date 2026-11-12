"use client";

import { StorefrontShell } from "@/components/store/StorefrontShell";
import { useAuth } from "@/hooks/useAuth";
import { completeSimulated, getOrder, getPaymentConfig, paySimulated } from "@/lib/api/checkout";
import { fieldErrorsFromDetails, storeErrorMessage } from "@/lib/api/errors";
import { formatCop } from "@/lib/productImage";
import {
  formatCardNumber,
  formatExpiry,
  sanitizeCardNumber,
  validateCardNumber,
  validateCvc,
  validateExpiry,
  validateName,
  type FieldErrors,
} from "@/lib/validation";
import { ApiClientError } from "@/types/api";
import type { PaymentConfig, SimulatedPaymentResult, StoreOrder } from "@/types/store";
import Link from "next/link";
import { useParams, useRouter } from "next/navigation";
import { FormEvent, useEffect, useState } from "react";

const STAGES = ["Validando los datos de la tarjeta…", "Contactando al banco emisor…", "Esperando la respuesta de la red…"];
const STAGE_MS = 700;

const OUTCOME_TITLES: Record<string, { title: string; tone: "ok" | "error" | "info" }> = {
  APPROVED: { title: "Pago aprobado", tone: "ok" },
  DECLINED: { title: "Pago rechazado", tone: "error" },
  INSUFFICIENT_FUNDS: { title: "Fondos insuficientes", tone: "error" },
  ERROR: { title: "Error al procesar el pago", tone: "error" },
  PENDING: { title: "Pago pendiente de verificación", tone: "info" },
};

const sleep = (ms: number) => new Promise((resolve) => setTimeout(resolve, ms));

export default function SimulatedPaymentPage() {
  const params = useParams<{ id: string }>();
  const { user, loading: authLoading } = useAuth();
  const router = useRouter();
  const [order, setOrder] = useState<StoreOrder | null>(null);
  const [config, setConfig] = useState<PaymentConfig | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});
  const [stage, setStage] = useState<number | null>(null);
  const [result, setResult] = useState<SimulatedPaymentResult | null>(null);
  const [card, setCard] = useState({ card_holder: "", card_number: "", expiry: "", cvc: "" });

  useEffect(() => {
    if (authLoading) return;
    if (!user || user.role !== "CUSTOMER") {
      router.replace(`/cliente/login?next=/pago/simulado/${params.id}`);
      return;
    }
    void Promise.all([getOrder(params.id), getPaymentConfig()])
      .then(([loadedOrder, loadedConfig]) => {
        setOrder(loadedOrder);
        setConfig(loadedConfig);
      })
      .catch((caught) => setError(storeErrorMessage(caught, "No se pudo cargar el pedido.")));
  }, [authLoading, params.id, router, user]);

  const busy = stage !== null;
  const payable = order?.status === "PENDING_PAYMENT" && !result;

  const runStages = async () => {
    for (let i = 0; i < STAGES.length; i += 1) {
      setStage(i);
      await sleep(STAGE_MS);
    }
  };

  const handleSubmit = async (event: FormEvent) => {
    event.preventDefault();
    const errors: FieldErrors = {};
    const holder = validateName(card.card_holder);
    if (holder) errors.card_holder = holder;
    const number = validateCardNumber(card.card_number);
    if (number) errors.card_number = number;
    const expiry = validateExpiry(card.expiry);
    if (expiry) errors.expiry = expiry;
    const cvc = validateCvc(card.cvc);
    if (cvc) errors.cvc = cvc;
    setFieldErrors(errors);
    if (Object.keys(errors).length > 0) {
      setError("Revisa los datos de la tarjeta.");
      return;
    }
    setError(null);
    try {
      await runStages();
      const outcome = await paySimulated(params.id, { ...card, card_holder: card.card_holder.trim() });
      setResult(outcome);
      setOrder(await getOrder(params.id));
    } catch (caught) {
      if (caught instanceof ApiClientError && caught.error.status === 422) {
        setFieldErrors(fieldErrorsFromDetails(caught.error.details));
      }
      setError(storeErrorMessage(caught, "No se pudo procesar el pago."));
    } finally {
      setStage(null);
    }
  };

  const handleComplete = async (decision: "APPROVED" | "DECLINED") => {
    setError(null);
    try {
      await runStages();
      setResult(await completeSimulated(params.id, decision));
      setOrder(await getOrder(params.id));
    } catch (caught) {
      setError(storeErrorMessage(caught, "No se pudo completar la verificación."));
    } finally {
      setStage(null);
    }
  };

  const fieldError = (key: string) =>
    fieldErrors[key] ? (
      <p className="store-field-error" role="alert">
        {fieldErrors[key]}
      </p>
    ) : null;

  const outcomeInfo = result ? OUTCOME_TITLES[result.outcome] : null;

  return (
    <StorefrontShell>
      <section className="store-hero">
        <h1>Pago con tarjeta (simulado)</h1>
        <p>Práctica de pago completa: no se cobra dinero real. El resultado depende de la tarjeta de prueba.</p>
      </section>
      {error ? <div className="store-alert store-alert-error" role="alert">{error}</div> : null}
      {config && !config.simulated ? (
        <div className="store-alert store-alert-error">
          La pasarela simulada no está activa (PAYMENT_PROVIDER distinto de «simulated»).
        </div>
      ) : null}
      <div className="store-detail">
        <div className="store-panel">
          {result && outcomeInfo ? (
            <div aria-live="polite">
              <div className={`store-alert store-alert-${outcomeInfo.tone === "info" ? "info" : outcomeInfo.tone}`}>
                <strong>{outcomeInfo.title}.</strong> {result.message}
              </div>
              <ul className="store-meta">
                <li>Estado del pedido: <strong>{result.order_status}</strong></li>
                <li>Estado del pago: <strong>{result.payment_status}</strong></li>
                {result.transaction_id ? <li>Transacción: {result.transaction_id}</li> : null}
                {result.card_last4 ? <li>Tarjeta terminada en {result.card_last4}</li> : null}
              </ul>
              {result.outcome === "PENDING" ? (
                <div style={{ display: "flex", gap: "0.75rem", flexWrap: "wrap" }}>
                  <button type="button" className="store-btn" disabled={busy} onClick={() => void handleComplete("APPROVED")}>
                    Simular: el banco aprueba
                  </button>
                  <button type="button" className="store-btn store-btn-secondary" disabled={busy} onClick={() => void handleComplete("DECLINED")}>
                    Simular: el banco rechaza
                  </button>
                </div>
              ) : null}
              {result.outcome === "APPROVED" ? (
                <Link className="store-btn" href={`/pedido/${result.order_id}`}>
                  Ver mi pedido
                </Link>
              ) : null}
              {result.can_retry || (result.outcome !== "APPROVED" && result.outcome !== "PENDING") ? (
                <div>
                  <p className="store-meta">
                    El pedido quedó {result.order_status} y las unidades reservadas volvieron al inventario. Puedes
                    armar un nuevo pedido con otra tarjeta de prueba.
                  </p>
                  <Link className="store-btn" href="/">
                    Volver al catálogo
                  </Link>
                </div>
              ) : null}
              {busy ? <p className="store-meta" aria-live="polite">{STAGES[stage ?? 0]}</p> : null}
            </div>
          ) : payable ? (
            <form noValidate onSubmit={(event) => void handleSubmit(event)} aria-busy={busy}>
              <h2>Datos de la tarjeta</h2>
              <div className="store-field">
                <label htmlFor="card_holder">Titular de la tarjeta</label>
                <input
                  id="card_holder"
                  autoComplete="cc-name"
                  maxLength={100}
                  disabled={busy}
                  value={card.card_holder}
                  aria-invalid={fieldErrors.card_holder ? true : undefined}
                  onChange={(event) => setCard((prev) => ({ ...prev, card_holder: event.target.value }))}
                />
                {fieldError("card_holder")}
              </div>
              <div className="store-field">
                <label htmlFor="card_number">Número de tarjeta</label>
                <input
                  id="card_number"
                  inputMode="numeric"
                  autoComplete="cc-number"
                  placeholder="4242 4242 4242 4242"
                  disabled={busy}
                  value={formatCardNumber(card.card_number)}
                  aria-invalid={fieldErrors.card_number ? true : undefined}
                  onChange={(event) =>
                    setCard((prev) => ({ ...prev, card_number: sanitizeCardNumber(event.target.value) }))
                  }
                />
                {fieldError("card_number")}
              </div>
              <div style={{ display: "grid", gridTemplateColumns: "1fr 1fr", gap: "0.75rem" }}>
                <div className="store-field">
                  <label htmlFor="expiry">Vence (MM/AA)</label>
                  <input
                    id="expiry"
                    inputMode="numeric"
                    autoComplete="cc-exp"
                    placeholder="12/30"
                    maxLength={5}
                    disabled={busy}
                    value={card.expiry}
                    aria-invalid={fieldErrors.expiry ? true : undefined}
                    onChange={(event) => setCard((prev) => ({ ...prev, expiry: formatExpiry(event.target.value) }))}
                  />
                  {fieldError("expiry")}
                </div>
                <div className="store-field">
                  <label htmlFor="cvc">CVC</label>
                  <input
                    id="cvc"
                    inputMode="numeric"
                    autoComplete="cc-csc"
                    placeholder="123"
                    maxLength={4}
                    disabled={busy}
                    value={card.cvc}
                    aria-invalid={fieldErrors.cvc ? true : undefined}
                    onChange={(event) =>
                      setCard((prev) => ({ ...prev, cvc: event.target.value.replace(/\D/g, "").slice(0, 4) }))
                    }
                  />
                  {fieldError("cvc")}
                </div>
              </div>
              <button type="submit" className="store-btn store-btn-accent" disabled={busy || !order}>
                {busy ? STAGES[stage ?? 0] : order ? `Pagar ${formatCop(order.total)}` : "Cargando…"}
              </button>
            </form>
          ) : order ? (
            <div className="store-alert store-alert-info">
              Este pedido está en estado <strong>{order.status}</strong> y no admite un nuevo pago.{" "}
              <Link href={`/pedido/${order.id}`}>Ver pedido</Link>
            </div>
          ) : (
            <p>Cargando…</p>
          )}
        </div>
        <aside className="store-panel">
          <h2>Tarjetas de prueba</h2>
          <p className="store-meta">Usa cualquier fecha futura y CVC de 3 dígitos. Haz clic para rellenar el número.</p>
          <ul className="store-test-cards">
            {(config?.test_cards ?? []).map((testCard) => (
              <li key={testCard.number}>
                <button
                  type="button"
                  className="store-test-card"
                  disabled={busy || !payable}
                  onClick={() =>
                    setCard((prev) => ({
                      card_holder: prev.card_holder || "Cliente Demo",
                      card_number: testCard.number,
                      expiry: prev.expiry || "12/40",
                      cvc: prev.cvc || "123",
                    }))
                  }
                >
                  <span className="store-test-card-number">{formatCardNumber(testCard.number)}</span>
                  <span className="store-test-card-label">{testCard.label}</span>
                </button>
              </li>
            ))}
          </ul>
          {order ? (
            <>
              <h3>Resumen</h3>
              <ul>
                {order.items.map((item) => (
                  <li key={item.id}>
                    {item.product_name} × {item.quantity}
                  </li>
                ))}
              </ul>
              <p>
                <strong>Total:</strong> {formatCop(order.total)}
              </p>
            </>
          ) : null}
        </aside>
      </div>
    </StorefrontShell>
  );
}
