"use client";

import { Modal } from "@/components/admin/crud/Modal";
import type { FormEvent, ReactNode } from "react";

type FormDialogProps = {
  open: boolean;
  title: string;
  description?: string;
  busy?: boolean;
  submitDisabled?: boolean;
  submitLabel: string;
  busyLabel?: string;
  onSubmit: (event: FormEvent<HTMLFormElement>) => void;
  onClose: () => void;
  children: ReactNode;
};

export function FormDialog({
  open,
  title,
  description,
  busy = false,
  submitDisabled = false,
  submitLabel,
  busyLabel = "Guardando…",
  onSubmit,
  onClose,
  children,
}: FormDialogProps) {
  return (
    <Modal open={open} title={title} description={description} busy={busy} onClose={onClose}>
      <form
        onSubmit={onSubmit}
        noValidate
        className="space-y-4"
      >
        {children}
        <div className="flex flex-col-reverse gap-2 pt-2 sm:flex-row sm:justify-end">
          <button
            type="button"
            disabled={busy}
            onClick={onClose}
            className="inline-flex min-h-11 items-center justify-center rounded-lg border border-border px-4 text-sm font-medium text-foreground hover:bg-surface-2 disabled:cursor-not-allowed disabled:opacity-60"
          >
            Cancelar
          </button>
          <button
            type="submit"
            disabled={busy || submitDisabled}
            aria-busy={busy}
            className="inline-flex min-h-11 items-center justify-center rounded-lg bg-accent px-4 text-sm font-semibold text-background hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60"
          >
            {busy ? busyLabel : submitLabel}
          </button>
        </div>
      </form>
    </Modal>
  );
}
