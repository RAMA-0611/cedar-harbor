"use client";

import { Modal } from "@/components/admin/crud/Modal";
import { StatusBadge } from "@/components/admin/crud/StatusBadge";
import { listCategories } from "@/lib/api/categories";
import { mutationErrorMessage } from "@/lib/api/errors";
import { getProduct } from "@/lib/api/products";
import { formatCurrencyCOP, formatDateTime, formatTaxPercent } from "@/lib/format";
import type { AdminCategory, AdminProduct } from "@/types/api";
import { useEffect, useState } from "react";

type ProductViewDialogProps = {
  productId: string;
  onClose: () => void;
};

export function ProductViewDialog({ productId, onClose }: ProductViewDialogProps) {
  const [product, setProduct] = useState<AdminProduct | null>(null);
  const [categories, setCategories] = useState<AdminCategory[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;
    void Promise.all([getProduct(productId), listCategories().catch(() => [] as AdminCategory[])])
      .then(([record, categoryRows]) => {
        if (!cancelled) {
          setProduct(record);
          setCategories(categoryRows);
          setError(null);
        }
      })
      .catch((caught: unknown) => {
        if (!cancelled) {
          setError(mutationErrorMessage(caught, "No se pudo consultar el producto."));
        }
      })
      .finally(() => {
        if (!cancelled) {
          setLoading(false);
        }
      });

    return () => {
      cancelled = true;
    };
  }, [productId]);

  const category = product
    ? categories.find((row) => row.id === product.category_id)
    : undefined;
  const categoryName = category?.name ?? "Categoría no disponible";

  return (
    <Modal
      open
      title="Detalle de producto"
      description="Consulta individual desde GET /api/admin/products/{id}."
      onClose={onClose}
    >
      {loading ? (
        <p className="text-sm text-muted">Cargando producto…</p>
      ) : error ? (
        <p role="alert" className="text-sm text-danger">
          {error}
        </p>
      ) : product ? (
        <dl className="space-y-3 text-sm">
          <ViewField label="SKU" value={product.sku} />
          <ViewField label="Nombre" value={product.name} />
          <ViewField
            label="Categoría"
            value={category && !category.is_active ? `${categoryName} (Inactiva)` : categoryName}
          />
          <ViewField
            label="Descripción"
            value={product.description?.trim() ? product.description : "—"}
          />
          <ViewField label="Marca" value={product.brand?.trim() ? product.brand : "—"} />
          <ViewField
            label="Condición"
            value={product.condition?.trim() ? product.condition : "—"}
          />
          <ViewField label="Unidad" value={product.unit?.trim() ? product.unit : "—"} />
          <ViewField label="Precio" value={formatCurrencyCOP(product.price)} />
          <ViewField label="Moneda" value={product.currency} />
          <ViewField label="Impuesto" value={formatTaxPercent(product.tax_rate)} />
          <ViewField label="Stock" value={String(product.stock_quantity)} />
          <div>
            <dt className="text-muted">Estado</dt>
            <dd className="mt-1">
              <StatusBadge
                label={product.is_active ? "Activo" : "Inactivo"}
                tone={product.is_active ? "ok" : "muted"}
              />
            </dd>
          </div>
          <ViewField label="Creado" value={formatDateTime(product.created_at)} />
          <ViewField label="Actualizado" value={formatDateTime(product.updated_at)} />
        </dl>
      ) : null}
      <div className="mt-6 flex justify-end">
        <button
          type="button"
          onClick={onClose}
          className="inline-flex min-h-11 items-center justify-center rounded-lg border border-border px-4 text-sm font-medium text-foreground hover:bg-surface-2"
        >
          Cerrar
        </button>
      </div>
    </Modal>
  );
}

function ViewField({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <dt className="text-muted">{label}</dt>
      <dd className="mt-1 break-all text-foreground">{value}</dd>
    </div>
  );
}
