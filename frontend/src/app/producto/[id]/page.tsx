"use client";

import { QuantityInput } from "@/components/store/QuantityInput";
import { StorefrontShell } from "@/components/store/StorefrontShell";
import { useAuth } from "@/hooks/useAuth";
import { addCartItem } from "@/lib/api/cart";
import { getCatalogProduct } from "@/lib/api/catalog";
import { storeErrorMessage } from "@/lib/api/errors";
import { formatCop, handleImageError, resolveProductImage } from "@/lib/productImage";
import type { CatalogProduct } from "@/types/catalog";
import Link from "next/link";
import { useParams, useRouter } from "next/navigation";
import { useCallback, useEffect, useState } from "react";

export default function ProductDetailPage() {
  const params = useParams<{ id: string }>();
  const router = useRouter();
  const { user } = useAuth();
  const [product, setProduct] = useState<CatalogProduct | null>(null);
  const [quantity, setQuantity] = useState(1);
  const [error, setError] = useState<string | null>(null);
  const [message, setMessage] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const load = useCallback(async () => {
    setError(null);
    try {
      const row = await getCatalogProduct(params.id);
      setProduct(row);
    } catch {
      setProduct(null);
      setError("Producto no disponible o inactivo.");
    }
  }, [params.id]);

  useEffect(() => {
    const handle = globalThis.setTimeout(() => {
      void load();
    }, 0);
    return () => globalThis.clearTimeout(handle);
  }, [load]);

  useEffect(() => {
    const onFocus = () => {
      void load();
    };
    window.addEventListener("focus", onFocus);
    return () => window.removeEventListener("focus", onFocus);
  }, [load]);

  const handleAdd = async () => {
    setMessage(null);
    setError(null);
    if (!user || user.role !== "CUSTOMER") {
      router.push(`/cliente/login?next=/producto/${params.id}`);
      return;
    }
    if (!product || product.available_quantity < 1) {
      setError("Este producto no tiene unidades disponibles.");
      return;
    }
    const qty = Math.min(Math.max(1, quantity), product.available_quantity);
    setBusy(true);
    try {
      await addCartItem(params.id, qty);
      setMessage("Producto agregado al carrito.");
    } catch (caught) {
      setError(storeErrorMessage(caught, "No se pudo agregar al carrito."));
    } finally {
      setBusy(false);
    }
  };

  return (
    <StorefrontShell>
      {!product && error ? <div className="store-alert store-alert-error">{error}</div> : null}
      {product ? (
        <div className="store-detail">
          {/* eslint-disable-next-line @next/next/no-img-element */}
          <img
            src={resolveProductImage(product)}
            alt={product.name}
            onError={handleImageError}
            style={{ width: "100%", borderRadius: "0.75rem", border: "1px solid #d5dbe1" }}
          />
          <div className="store-panel">
            <p className="store-meta">{product.category?.name ?? "Sin categoría"}</p>
            <h1>{product.name}</h1>
            <p className="store-meta">SKU {product.sku}</p>
            <p className="store-price" style={{ fontSize: "1.4rem" }}>
              {formatCop(product.unit_price)}
            </p>
            <p className="store-meta">
              Disponible: {product.available_quantity}
              {product.unit ? ` ${product.unit}` : ""}
            </p>
            {product.brand ? <p className="store-meta">Marca: {product.brand}</p> : null}
            {product.condition ? <p className="store-meta">Condición: {product.condition}</p> : null}
            {product.description ? <p>{product.description}</p> : null}

            <div className="store-field" style={{ maxWidth: "8rem" }}>
              <label htmlFor="qty">Cantidad</label>
              <QuantityInput
                id="qty"
                label="Cantidad"
                value={quantity}
                max={Math.max(1, product.available_quantity)}
                onCommit={setQuantity}
              />
            </div>

            {message ? <div className="store-alert store-alert-ok">{message}</div> : null}
            {error ? <div className="store-alert store-alert-error">{error}</div> : null}

            <div style={{ display: "flex", gap: "0.75rem", flexWrap: "wrap" }}>
              <button type="button" className="store-btn" disabled={busy} onClick={() => void handleAdd()}>
                {user?.role === "CUSTOMER" ? "Agregar al carrito" : "Inicie sesión para comprar"}
              </button>
              <Link className="store-btn store-btn-secondary" href="/carrito">
                Ir al carrito
              </Link>
            </div>
          </div>
        </div>
      ) : null}
    </StorefrontShell>
  );
}
