"use client";

import { ConfirmDialog } from "@/components/admin/crud/ConfirmDialog";
import { DataTable, type DataTableColumn } from "@/components/admin/crud/DataTable";
import { EmptyState } from "@/components/admin/crud/EmptyState";
import { FeedbackMessage } from "@/components/admin/crud/FeedbackMessage";
import { LoadingTable } from "@/components/admin/crud/LoadingTable";
import { PageHeader } from "@/components/admin/crud/PageHeader";
import { StatusBadge } from "@/components/admin/crud/StatusBadge";
import { actionButtonClassName, primaryButtonClassName } from "@/components/admin/crud/fieldStyles";
import { UserFormDialog } from "@/components/users/UserFormDialog";
import { UserViewDialog } from "@/components/users/UserViewDialog";
import { useAuth } from "@/hooks/useAuth";
import { mutationErrorMessage, userDeleteErrorMessage } from "@/lib/api/errors";
import { deleteUser, listUsers } from "@/lib/api/users";
import { formatDateTime } from "@/lib/format";
import type { AdminUser } from "@/types/api";
import { useCallback, useEffect, useMemo, useState } from "react";

type FormMode = "create" | "edit" | null;

export function UsersModule() {
  const { user: sessionUser } = useAuth();
  const [users, setUsers] = useState<AdminUser[]>([]);
  const [loading, setLoading] = useState(true);
  const [listError, setListError] = useState<string | null>(null);
  const [feedback, setFeedback] = useState<{ kind: "success" | "error"; text: string } | null>(
    null,
  );
  const [formMode, setFormMode] = useState<FormMode>(null);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [viewingId, setViewingId] = useState<number | null>(null);
  const [pendingDelete, setPendingDelete] = useState<AdminUser | null>(null);
  const [deleting, setDeleting] = useState(false);

  const loadUsers = useCallback(async (withLoading = false) => {
    if (withLoading) {
      setLoading(true);
    }
    setListError(null);
    try {
      const data = await listUsers();
      setUsers(data);
    } catch (caught) {
      setUsers([]);
      setListError(mutationErrorMessage(caught, "No se pudieron cargar los usuarios."));
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    let cancelled = false;
    void listUsers()
      .then((data) => {
        if (!cancelled) {
          setUsers(data);
          setListError(null);
        }
      })
      .catch((caught: unknown) => {
        if (!cancelled) {
          setUsers([]);
          setListError(mutationErrorMessage(caught, "No se pudieron cargar los usuarios."));
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
    await loadUsers();
  }

  async function confirmDelete() {
    if (!pendingDelete || deleting) {
      return;
    }
    const target = pendingDelete;
    const isSelf = sessionUser?.id === target.id;
    setDeleting(true);
    try {
      await deleteUser(target.id);
      setPendingDelete(null);
      setFeedback({ kind: "success", text: "Usuario eliminado correctamente." });
      await loadUsers();
    } catch (caught) {
      setFeedback({ kind: "error", text: userDeleteErrorMessage(caught, isSelf) });
    } finally {
      setDeleting(false);
    }
  }

  const columns = useMemo<DataTableColumn<AdminUser>[]>(
    () => [
      {
        header: "Nombre",
        render: (row) => <span className="font-medium">{row.name}</span>,
      },
      {
        header: "Correo",
        render: (row) => row.email,
      },
      {
        header: "Rol",
        render: (row) => (
          <StatusBadge label={row.role} tone={row.role === "ADMIN" ? "accent" : "muted"} />
        ),
      },
      {
        header: "Creado",
        render: (row) => formatDateTime(row.created_at),
      },
      {
        header: "Acciones",
        headerClassName: "text-right",
        cellClassName: "text-right",
        render: (row) => {
          const isSelf = sessionUser?.id === row.id;
          return (
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
                disabled={isSelf}
                title={isSelf ? "No puede eliminar su propia cuenta." : undefined}
                aria-label={
                  isSelf
                    ? "Eliminar. No puede eliminar su propia cuenta."
                    : `Eliminar a ${row.name}`
                }
                onClick={() => {
                  if (!isSelf) {
                    setPendingDelete(row);
                  }
                }}
              >
                Eliminar
              </button>
            </div>
          );
        },
      },
    ],
    [sessionUser?.id],
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
      Nuevo usuario
    </button>
  );

  return (
    <section className="space-y-5">
      <PageHeader
        title="Usuarios"
        description="Cuentas del sistema y control de roles ADMIN/CUSTOMER. Los cambios se guardan en PostgreSQL a través de la API."
        action={createButton}
      />

      {feedback ? <FeedbackMessage kind={feedback.kind}>{feedback.text}</FeedbackMessage> : null}

      {loading ? (
        <LoadingTable columns={5} label="Cargando usuarios" />
      ) : listError ? (
        <div className="rounded-2xl border border-border bg-surface px-6 py-10 text-center">
          <p role="alert" className="text-sm text-danger">
            No se pudieron cargar los usuarios.
          </p>
          <button
            type="button"
            className={`${actionButtonClassName} mt-4 min-h-10 px-3 text-sm`}
            onClick={() => {
              void loadUsers(true);
            }}
          >
            Reintentar
          </button>
        </div>
      ) : users.length === 0 ? (
        <EmptyState
          message="No hay usuarios registrados."
          action={
            <button
              type="button"
              className={primaryButtonClassName}
              onClick={() => {
                setEditingId(null);
                setFormMode("create");
              }}
            >
              Crear usuario
            </button>
          }
        />
      ) : (
        <DataTable
          caption="Usuarios administrativos"
          columns={columns}
          rows={users}
          rowKey={(row) => String(row.id)}
        />
      )}

      {sessionUser && users.some((row) => row.id === sessionUser.id) ? (
        <p className="text-xs text-muted">No puede eliminar su propia cuenta.</p>
      ) : null}

      {formMode ? (
        <UserFormDialog
          mode={formMode}
          userId={editingId}
          onClose={closeForm}
          onSaved={(message) => {
            void onSaved(message);
          }}
        />
      ) : null}

      {viewingId !== null ? <UserViewDialog userId={viewingId} onClose={closeView} /> : null}

      <ConfirmDialog
        open={pendingDelete !== null}
        title="Eliminar usuario"
        description={
          pendingDelete
            ? `¿Eliminar al usuario ${pendingDelete.name}?`
            : "¿Eliminar este usuario?"
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
