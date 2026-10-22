"use client";

import { ConfirmDialog } from "@/components/admin/crud/ConfirmDialog";
import { DataTable, type DataTableColumn } from "@/components/admin/crud/DataTable";
import { EmptyState } from "@/components/admin/crud/EmptyState";
import { FeedbackMessage } from "@/components/admin/crud/FeedbackMessage";
import { LoadingTable } from "@/components/admin/crud/LoadingTable";
import { PageHeader } from "@/components/admin/crud/PageHeader";
import { StatusBadge } from "@/components/admin/crud/StatusBadge";
import { actionButtonClassName, primaryButtonClassName } from "@/components/admin/crud/fieldStyles";
import { ProductFormDialog } from "@/components/products/ProductFormDialog";
import { ProductViewDialog } from "@/components/products/ProductViewDialog";
import { listCategories } from "@/lib/api/categories";
import { mutationErrorMessage, productDeleteErrorMessage } from "@/lib/api/errors";
import { deleteProduct, listProducts } from "@/lib/api/products";
import { formatCurrencyCOP } from "@/lib/format";
import type { AdminCategory, AdminProduct } from "@/types/api";
import { useCallback, useEffect, useMemo, useState } from "react";

type FormMode = "create" | "edit" | null;

function categoryDisplayName(
  categoryId: string,
  categoriesById: Map<string, AdminCategory>,
): string {
  return categoriesById.get(categoryId)?.name ?? "Categoría no disponible";
}

export function ProductsModule() {
  const [products, setProducts] = useState<AdminProduct[]>([]);
  const [categories, setCategories] = useState<AdminCategory[]>([]);
  const [loading, setLoading] = useState(true);
  const [listError, setListError] = useState<string | null>(null);
  const [feedback, setFeedback] = useState<{ kind: "success" | "error"; text: string } | null>(
    null,
  );
  const [formMode, setFormMode] = useState<FormMode>(null);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [viewingId, setViewingId] = useState<string | null>(null);
  const [pendingDelete, setPendingDelete] = useState<AdminProduct | null>(null);
  const [deleting, setDeleting] = useState(false);

  const loadProducts = useCallback(async (withLoading = false) => {
    if (withLoading) {
      setLoading(true);
    }
    setListError(null);
    try {
      const [productRows, categoryRows] = await Promise.all([
        listProducts(),
        listCategories().catch(() => [] as AdminCategory[]),
      ]);
      setProducts(productRows);
      setCategories(categoryRows);
    } catch (caught) {
      setProducts([]);
      setListError(mutationErrorMessage(caught, "No se pudieron cargar los productos."));
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    let cancelled = false;
    void Promise.all([listProducts(), listCategories().catch(() => [] as AdminCategory[])])
      .then(([productRows, categoryRows]) => {
        if (!cancelled) {
          setProducts(productRows);
          setCategories(categoryRows);
          setListError(null);
        }
      })
      .catch((caught: unknown) => {
        if (!cancelled) {
          setProducts([]);
          setListError(mutationErrorMessage(caught, "No se pudieron cargar los productos."));
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
  }, []);

  const categoriesById = useMemo(() => {
    const map = new Map<string, AdminCategory>();
    for (const category of categories) {
      map.set(category.id, category);
    }
    return map;
  }, [categories]);

  const closeForm = useCallback(() => {
    setFormMode(null);
    setEditingId(null);
  }, []);

  const closeView = useCallback(() => {
    setViewingId(null);
  }, []);

  const closeDelete = useCallback(() => {
    setPendingDelete(null);
  }, []);

  async function onSaved(message: string) {
    setFormMode(null);
    setEditingId(null);
    setFeedback({ kind: "success", text: message });
    await loadProducts();
  }

  async function confirmDelete() {
    if (!pendingDelete || deleting) {
      return;
    }
    const target = pendingDelete;
    setDeleting(true);
    try {
      await deleteProduct(target.id);
      setPendingDelete(null);
      setFeedback({ kind: "success", text: "Producto eliminado correctamente." });
      await loadProducts();
    } catch (caught) {
      setFeedback({ kind: "error", text: productDeleteErrorMessage(caught) });
    } finally {
      setDeleting(false);
    }
  }

  const columns = useMemo<DataTableColumn<AdminProduct>[]>(
    () => [
      {
        header: "SKU",
        render: (row) => <span className="font-mono text-xs">{row.sku}</span>,
      },
      {
        header: "Producto",
        render: (row) => <span className="font-medium">{row.name}</span>,
      },
      {
        header: "Categoría",
        render: (row) => categoryDisplayName(row.category_id, categoriesById),
      },
      {
        header: "Precio",
        render: (row) => formatCurrencyCOP(row.price),
      },
      {
        header: "Stock",
        render: (row) => String(row.stock_quantity),
      },
      {
        header: "Estado",
        render: (row) => (
          <StatusBadge
            label={row.is_active ? "Activo" : "Inactivo"}
            tone={row.is_active ? "ok" : "muted"}
          />
        ),
      },
      {
        header: "Acciones",
        headerClassName: "text-right",
        cellClassName: "text-right",
        render: (row) => (
          <div className="flex flex-wrap justify-end gap-2">
            <button
              type="button"
              className={actionButtonClassName}
              onClick={() => setViewingId(row.id)}
            >
              Ver
            </button>
            <button
              type="button"
              className={actionButtonClassName}
              onClick={() => {
                setEditingId(row.id);
                setFormMode("edit");
              }}
            >
              Editar
            </button>
            <button
              type="button"
              className={actionButtonClassName}
              aria-label={`Eliminar ${row.name}`}
              onClick={() => setPendingDelete(row)}
            >
              Eliminar
            </button>
          </div>
        ),
      },
    ],
    [categoriesById],
  );

  const createButton = (
    <button
      type="button"
      className={primaryButtonClassName}
      onClick={() => {
        setEditingId(null);
        setFormMode("create");
      }}
    >
      Nuevo producto
    </button>
  );

  return (
    <section className="space-y-5">
      <PageHeader
        title="Productos"
        description="Catálogo administrativo persistido en PostgreSQL. Precio en COP e impuesto se muestran en porcentaje."
        action={createButton}
      />

      {feedback ? <FeedbackMessage kind={feedback.kind}>{feedback.text}</FeedbackMessage> : null}

      {loading ? (
        <LoadingTable columns={7} label="Cargando productos" />
      ) : listError ? (
        <div className="rounded-2xl border border-border bg-surface px-6 py-10 text-center">
          <p role="alert" className="text-sm text-danger">
            No se pudieron cargar los productos.
          </p>
          <button
            type="button"
            className={`${actionButtonClassName} mt-4 min-h-10 px-3 text-sm`}
            onClick={() => {
              void loadProducts(true);
            }}
          >
            Reintentar
          </button>
        </div>
      ) : products.length === 0 ? (
        <EmptyState
          message="No hay productos registrados."
          action={
            <button
              type="button"
              className={primaryButtonClassName}
              onClick={() => {
                setEditingId(null);
                setFormMode("create");
              }}
            >
              Crear producto
            </button>
          }
        />
      ) : (
        <DataTable
          caption="Productos administrativos"
          columns={columns}
          rows={products}
          rowKey={(row) => row.id}
        />
      )}

      {formMode ? (
        <ProductFormDialog
          mode={formMode}
          productId={editingId}
          onClose={closeForm}
          onSaved={(message) => {
            void onSaved(message);
          }}
        />
      ) : null}

      {viewingId !== null ? (
        <ProductViewDialog productId={viewingId} onClose={closeView} />
      ) : null}

      <ConfirmDialog
        open={pendingDelete !== null}
        title="Eliminar producto"
        description={
          pendingDelete
            ? `¿Eliminar el producto ${pendingDelete.name}?`
            : "¿Eliminar este producto?"
        }
        busy={deleting}
        onClose={deleting ? () => undefined : closeDelete}
        onConfirm={() => {
          void confirmDelete();
        }}
      />
    </section>
  );
}
