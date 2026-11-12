"use client";

import { StorefrontShell } from "@/components/store/StorefrontShell";
import { useAuth } from "@/hooks/useAuth";
import { getCart } from "@/lib/api/cart";
import { createOrder, validateCheckout } from "@/lib/api/checkout";
import { storeErrorMessage } from "@/lib/api/errors";
import { formatCop } from "@/lib/productImage";
import { ContactFields } from "@/components/store/ContactFields";
import { validateContact, validateEmail, type FieldErrors } from "@/lib/validation";
import type { CartView, CheckoutPayload } from "@/types/store";
import { useRouter } from "next/navigation";
import { FormEvent, useEffect, useState } from "react";

function newIdempotencyKey(): string {
  if (typeof crypto !== "undefined" && "randomUUID" in crypto) {
    return crypto.randomUUID();
  }
  return `idem-${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

export default function CheckoutPage() {
  const { user, loading: authLoading } = useAuth();
  const router = useRouter();
  const [cart, setCart] = useState<CartView | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});
  const [form, setForm] = useState({
    name: "",
    email: "",
    phone: "",
    document_type: "CC",
    document_number: "",
    mode: "PICKUP" as "PICKUP" | "COORDINATED_SHIPPING",
    address: "",
  });

  useEffect(() => {
    if (authLoading) {
      return;
    }
    if (!user || user.role !== "CUSTOMER") {
      router.replace("/cliente/login?next=/checkout");
      return;
    }
    void getCart()
      .then((next) => {
        setCart(next);
        setForm((prev) => ({ ...prev, email: user.email, name: user.name }));
      })
      .catch((caught) => setError(storeErrorMessage(caught, "No se pudo cargar el carrito para checkout.")));
  }, [authLoading, router, user]);

  const handleSubmit = async (event: FormEvent) => {
    event.preventDefault();
    if (!user || user.role !== "CUSTOMER") {
      return;
    }
    const errors: FieldErrors = validateContact(form);
    const emailError = validateEmail(form.email);
    if (emailError) errors.email = emailError;
    if (form.mode === "COORDINATED_SHIPPING" && form.address.trim().length < 5) {
      errors.address = "Escribe la dirección de entrega (mínimo 5 caracteres).";
    }
    setFieldErrors(errors);
    if (Object.keys(errors).length > 0) {
      setError("Revisa los campos marcados antes de continuar.");
      return;
    }
    setBusy(true);
    setError(null);
    const payload: CheckoutPayload = {
      customer: {
        name: form.name.trim(),
        email: form.email.trim(),
        phone: form.phone.trim(),
        document_type: form.document_type.trim(),
        document_number: form.document_number.trim(),
      },
      delivery: {
        mode: form.mode,
        address: form.mode === "PICKUP" ? null : form.address.trim(),
      },
    };
    try {
      await validateCheckout(payload);
      const order = await createOrder(payload, newIdempotencyKey());
      try {
        globalThis.sessionStorage?.setItem("rotor.lastOrderId", order.id);
      } catch {
        // ignore storage failures
      }
      router.replace(`/pedido/${order.id}`);
    } catch (caught) {
      setError(storeErrorMessage(caught, "Checkout inválido o no se pudo crear el pedido."));
    } finally {
      setBusy(false);
    }
  };

  return (
    <StorefrontShell>
      <section className="store-hero">
        <h1>Checkout</h1>
        <p>Validación real y creación de pedido PENDING_PAYMENT.</p>
      </section>
      {error ? <div className="store-alert store-alert-error">{error}</div> : null}
      <div className="store-detail">
        <form className="store-panel" noValidate onSubmit={(event) => void handleSubmit(event)}>
          <ContactFields
            values={form}
            errors={fieldErrors}
            onChange={(next) => setForm((prev) => ({ ...prev, ...next }))}
          />
          <div className="store-field">
            <label htmlFor="email">Correo</label>
            <input
              id="email"
              type="email"
              required
              maxLength={254}
              value={form.email}
              aria-invalid={fieldErrors.email ? true : undefined}
              onChange={(event) => setForm((prev) => ({ ...prev, email: event.target.value }))}
            />
            {fieldErrors.email ? <p className="store-field-error" role="alert">{fieldErrors.email}</p> : null}
          </div>
          <div className="store-field">
            <label htmlFor="mode">Entrega</label>
            <select
              id="mode"
              value={form.mode}
              onChange={(event) =>
                setForm((prev) => ({
                  ...prev,
                  mode: event.target.value as "PICKUP" | "COORDINATED_SHIPPING",
                }))
              }
            >
              <option value="PICKUP">Recogida</option>
              <option value="COORDINATED_SHIPPING">Envío coordinado</option>
            </select>
          </div>
          {form.mode === "COORDINATED_SHIPPING" ? (
            <div className="store-field">
              <label htmlFor="address">Dirección</label>
              <textarea
                id="address"
                required
                rows={3}
                value={form.address}
                maxLength={255}
                aria-invalid={fieldErrors.address ? true : undefined}
                onChange={(event) => setForm((prev) => ({ ...prev, address: event.target.value }))}
              />
              {fieldErrors.address ? <p className="store-field-error" role="alert">{fieldErrors.address}</p> : null}
            </div>
          ) : null}
          <button type="submit" className="store-btn" disabled={busy || !cart || cart.items.length === 0}>
            {busy ? "Creando pedido…" : "Crear pedido"}
          </button>
        </form>
        <aside className="store-panel">
          <h2>Resumen</h2>
          {!cart || cart.items.length === 0 ? (
            <p className="store-meta">No hay ítems en el carrito.</p>
          ) : (
            <>
              <ul>
                {cart.items.map((item) => (
                  <li key={item.id}>
                    {item.name} × {item.quantity} — {formatCop(item.line_total)}
                  </li>
                ))}
              </ul>
              <p>
                <strong>Total:</strong> {formatCop(cart.total)}
              </p>
            </>
          )}
        </aside>
      </div>
    </StorefrontShell>
  );
}
