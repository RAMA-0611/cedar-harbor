"use client";

import { StorefrontShell } from "@/components/store/StorefrontShell";
import { useAuth } from "@/hooks/useAuth";
import { getCart, removeCartItem, updateCartItem } from "@/lib/api/cart";
import { formatCop } from "@/lib/productImage";
import type { CartView } from "@/types/store";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useCallback, useEffect, useState } from "react";

export default function CartPage() {
  const { user, loading: authLoading } = useAuth();
  const router = useRouter();
  const [cart, setCart] = useState<CartView | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const load = useCallback(async () => {
    if (!user || user.role !== "CUSTOMER") {
      return;
    }
    setError(null);
    try {
      setCart(await getCart());
    } catch {
      setError("No se pudo cargar el carrito.");
    }
  }, [user]);

  useEffect(() => {
    if (authLoading) {
      return;
    }
    if (!user || user.role !== "CUSTOMER") {
      router.replace("/cliente/login?next=/carrito");
      return;
    }
    const handle = globalThis.setTimeout(() => {
      void load();
    }, 0);
    return () => globalThis.clearTimeout(handle);
  }, [authLoading, load, router, user]);

  if (authLoading || !user || user.role !== "CUSTOMER") {
    return (
      <StorefrontShell>
        <p>Comprobando sesión…</p>
      </StorefrontShell>
    );
  }

  return (
    <StorefrontShell>
      <section className="store-hero">
        <h1>Carrito</h1>
        <p>Reservas reales vía API · expira según política del carrito.</p>
      </section>
      {error ? <div className="store-alert store-alert-error">{error}</div> : null}
      <div className="store-panel">
        {!cart || cart.items.length === 0 ? (
          <p>
            El carrito está vacío. <Link href="/">Volver al catálogo</Link>
          </p>
        ) : (
          <>
            {cart.expires_at ? (
              <p className="store-meta">Reserva vigente hasta {new Date(cart.expires_at).toLocaleString("es-CO")}</p>
            ) : null}
            <table className="store-table">
              <thead>
                <tr>
                  <th>Producto</th>
                  <th>Cantidad</th>
                  <th>Disponible</th>
                  <th>Total línea</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                {cart.items.map((item) => (
                  <tr key={item.id}>
                    <td>
                      <strong>{item.name}</strong>
                      <div className="store-meta">
                        {item.sku} · {formatCop(item.unit_price)}
                      </div>
                    </td>
                    <td>
                      <input
                        aria-label={`Cantidad ${item.name}`}
                        type="number"
                        min={1}
                        value={item.quantity}
                        disabled={busy}
                        style={{ width: "4.5rem" }}
                        onChange={(event) => {
                          const next = Math.max(1, Number(event.target.value) || 1);
                          setBusy(true);
                          void updateCartItem(item.id, next)
                            .then(setCart)
                            .catch(() => setError("No se pudo actualizar la cantidad."))
                            .finally(() => setBusy(false));
                        }}
                      />
                    </td>
                    <td>{item.available_quantity}</td>
                    <td>{formatCop(item.line_total)}</td>
                    <td>
                      <button
                        type="button"
                        className="store-link-btn"
                        style={{ color: "#b42318" }}
                        disabled={busy}
                        onClick={() => {
                          setBusy(true);
                          void removeCartItem(item.id)
                            .then(setCart)
                            .catch(() => setError("No se pudo eliminar el ítem."))
                            .finally(() => setBusy(false));
                        }}
                      >
                        Quitar
                      </button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
            <p>
              <strong>Subtotal:</strong> {formatCop(cart.subtotal)}
            </p>
            <p>
              <strong>Impuestos:</strong> {formatCop(cart.tax_total)}
            </p>
            <p>
              <strong>Total:</strong> {formatCop(cart.total)}
            </p>
            <Link className="store-btn" href="/checkout">
              Ir a checkout
            </Link>
          </>
        )}
      </div>
    </StorefrontShell>
  );
}
