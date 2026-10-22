"use client";

import { ConfirmDialog } from "@/components/admin/crud/ConfirmDialog";
import { DataTable, type DataTableColumn } from "@/components/admin/crud/DataTable";
import { EmptyState } from "@/components/admin/crud/EmptyState";
import { FeedbackMessage } from "@/components/admin/crud/FeedbackMessage";
import { LoadingTable } from "@/components/admin/crud/LoadingTable";
import { PageHeader } from "@/components/admin/crud/PageHeader";
import { StatusBadge } from "@/components/admin/crud/StatusBadge";
import { actionButtonClassName, primaryButtonClassName } from "@/components/admin/crud/fieldStyles";
import { CategoryFormDialog } from "@/components/categories/CategoryFormDialog";
import { CategoryViewDialog } from "@/components/categories/CategoryViewDialog";
import { categoryDeleteErrorMessage, mutationErrorMessage } from "@/lib/api/errors";
import { deleteCategory, listCategories } from "@/lib/api/categories";
import type { AdminCategory } from "@/types/api";
import { useCallback, useEffect, useMemo, useState } from "react";

type FormMode = "create" | "edit" | null;

export function CategoriesModule() {
  const [categories, setCategories] = useState<AdminCategory[]>([]);
  const [loading, setLoading] = useState(true);
  const [listError, setListError] = useState<string | null>(null);
  const [feedback, setFeedback] = useState<{ kind: "success" | "error"; text: string } | null>(
    null,
  );
  const [formMode, setFormMode] = useState<FormMode>(null);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [viewingId, setViewingId] = useState<string | null>(null);
  const [pendingDelete, setPendingDelete] = useState<AdminCategory | null>(null);
  const [deleting, setDeleting] = useState(false);

  const loadCategories = useCallback(async (withLoading = false) => {
    if (withLoading) {
      setLoading(true);
    }
    setListError(null);
    try {
      const data = await listCategories();
      setCategories(data);
    } catch (caught) {
      setCategories([]);
      setListError(mutationErrorMessage(caught, "No se pudieron cargar las categorías."));
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    let cancelled = false;
    void listCategories()
      .then((data) => {
        if (!cancelled) {
          setCategories(data);
          setListError(null);
        }
      })
      .catch((caught: unknown) => {
        if (!cancelled) {
          setCategories([]);
          setListError(mutationErrorMessage(caught, "No se pudieron cargar las categorías."));
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
    await loadCategories();
  }

  async function confirmDelete() {
    if (!pendingDelete || deleting) {
      return;
    }
    const target = pendingDelete;
    setDeleting(true);
    try {
      await deleteCategory(target.id);
      setPendingDelete(null);
      setFeedback({ kind: "success", text: "Categoría eliminada correctamente." });
      await loadCategories();
    } catch (caught) {
      setFeedback({ kind: "error", text: categoryDeleteErrorMessage(caught) });
    } finally {
      setDeleting(false);
    }
  }

  const columns = useMemo<DataTableColumn<AdminCategory>[]>(
    () => [
      {
        header: "Nombre",
        render: (row) => <span className="font-medium">{row.name}</span>,
      },
      {
        header: "Slug",
        cellClassName: "font-mono text-xs",
        render: (row) => row.slug,
      },
      {
        header: "Estado",
        render: (row) => (
          <StatusBadge
            label={row.is_active ? "Activa" : "Inactiva"}
            tone={row.is_active ? "ok" : "muted"}
          />
        ),
      },
      {
        header: "Descripción",
        render: (row) => (
          <span className="line-clamp-2 max-w-xs text-muted">
            {row.description?.trim() ? row.description : "—"}
          </span>
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
              onClick={() => setPendingDelete(row)}
            >
              Eliminar
            </button>
          </div>
        ),
      },
    ],
    [],
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
      Nueva categoría
    </button>
  );

  return (
    <section className="space-y-5">
      <PageHeader
        title="Categorías"
        description="Clasificación del catálogo utilizada por productos y filtros. Persistida en PostgreSQL."
        action={createButton}
      />

      {feedback ? <FeedbackMessage kind={feedback.kind}>{feedback.text}</FeedbackMessage> : null}

      {loading ? (
        <LoadingTable columns={5} label="Cargando categorías" />
      ) : listError ? (
        <div className="rounded-2xl border border-border bg-surface px-6 py-10 text-center">
          <p role="alert" className="text-sm text-danger">
            No se pudieron cargar las categorías.
          </p>
          <button
            type="button"
            className={`${actionButtonClassName} mt-4 min-h-10 px-3 text-sm`}
            onClick={() => {
              void loadCategories(true);
            }}
          >
            Reintentar
          </button>
        </div>
      ) : categories.length === 0 ? (
        <EmptyState
          message="No hay categorías registradas."
          action={
            <button
              type="button"
              className={primaryButtonClassName}
              onClick={() => {
                setEditingId(null);
                setFormMode("create");
              }}
            >
              Crear categoría
            </button>
          }
        />
      ) : (
        <DataTable
          caption="Categorías del catálogo"
          columns={columns}
          rows={categories}
          rowKey={(row) => row.id}
        />
      )}

      {formMode ? (
        <CategoryFormDialog
          mode={formMode}
          categoryId={editingId}
          onClose={closeForm}
          onSaved={(message) => {
            void onSaved(message);
          }}
        />
      ) : null}

      {viewingId ? <CategoryViewDialog categoryId={viewingId} onClose={closeView} /> : null}

      <ConfirmDialog
        open={pendingDelete !== null}
        title="Eliminar categoría"
        description={
          pendingDelete
            ? `¿Eliminar la categoría ${pendingDelete.name}?`
            : "¿Eliminar esta categoría?"
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
