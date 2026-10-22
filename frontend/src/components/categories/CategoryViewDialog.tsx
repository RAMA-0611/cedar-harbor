"use client";

import { Modal } from "@/components/admin/crud/Modal";
import { StatusBadge } from "@/components/admin/crud/StatusBadge";
import { getCategory } from "@/lib/api/categories";
import { mutationErrorMessage } from "@/lib/api/errors";
import { formatDateTime } from "@/lib/format";
import type { AdminCategory } from "@/types/api";
import { useEffect, useState } from "react";

type CategoryViewDialogProps = {
  categoryId: string;
  onClose: () => void;
};

export function CategoryViewDialog({ categoryId, onClose }: CategoryViewDialogProps) {
  const [category, setCategory] = useState<AdminCategory | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;
    void getCategory(categoryId)
      .then((record) => {
        if (!cancelled) {
          setCategory(record);
          setError(null);
        }
      })
      .catch((caught: unknown) => {
        if (!cancelled) {
          setError(mutationErrorMessage(caught, "No se pudo consultar la categoría."));
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
  }, [categoryId]);

  return (
    <Modal
      open
      title="Detalle de categoría"
      description="Consulta individual desde GET /api/admin/categories/{id}."
      onClose={onClose}
    >
      {loading ? (
        <p className="text-sm text-muted">Cargando categoría…</p>
      ) : error ? (
        <p role="alert" className="text-sm text-danger">
          {error}
        </p>
      ) : category ? (
        <dl className="space-y-3 text-sm">
          <ViewField label="Nombre" value={category.name} />
          <ViewField label="Slug" value={category.slug} />
          <ViewField
            label="Descripción"
            value={category.description?.trim() ? category.description : "—"}
          />
          <div>
            <dt className="text-muted">Estado</dt>
            <dd className="mt-1">
              <StatusBadge
                label={category.is_active ? "Activa" : "Inactiva"}
                tone={category.is_active ? "ok" : "muted"}
              />
            </dd>
          </div>
          <ViewField label="Creado" value={formatDateTime(category.created_at)} />
          <ViewField label="Actualizado" value={formatDateTime(category.updated_at)} />
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
