"use client";

import { Modal } from "@/components/admin/crud/Modal";
import { StatusBadge } from "@/components/admin/crud/StatusBadge";
import { getUser } from "@/lib/api/users";
import { mutationErrorMessage } from "@/lib/api/errors";
import { formatDateTime } from "@/lib/format";
import type { AdminUser } from "@/types/api";
import { useEffect, useState } from "react";

type UserViewDialogProps = {
  userId: number;
  onClose: () => void;
};

export function UserViewDialog({ userId, onClose }: UserViewDialogProps) {
  const [user, setUser] = useState<AdminUser | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;
    void getUser(userId)
      .then((record) => {
        if (!cancelled) {
          setUser(record);
          setError(null);
        }
      })
      .catch((caught: unknown) => {
        if (!cancelled) {
          setError(mutationErrorMessage(caught, "No se pudo consultar el usuario."));
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
  }, [userId]);

  return (
    <Modal
      open
      title="Detalle de usuario"
      description="Consulta individual desde GET /api/admin/users/{id}."
      onClose={onClose}
    >
      {loading ? (
        <p className="text-sm text-muted">Cargando usuario…</p>
      ) : error ? (
        <p role="alert" className="text-sm text-danger">
          {error}
        </p>
      ) : user ? (
        <dl className="space-y-3 text-sm">
          <ViewField label="Nombre" value={user.name} />
          <ViewField label="Correo" value={user.email} />
          <div>
            <dt className="text-muted">Rol</dt>
            <dd className="mt-1">
              <StatusBadge
                label={user.role}
                tone={user.role === "ADMIN" ? "accent" : "muted"}
              />
            </dd>
          </div>
          <ViewField label="Creado" value={formatDateTime(user.created_at)} />
          <ViewField label="Actualizado" value={formatDateTime(user.updated_at)} />
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
      <dd className="mt-1 text-foreground">{value}</dd>
    </div>
  );
}
