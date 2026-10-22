"use client";

import { FormDialog } from "@/components/admin/crud/FormDialog";
import { helpClassName, inputClassName, labelClassName } from "@/components/admin/crud/fieldStyles";
import { fieldErrorsFromDetails, mutationErrorMessage } from "@/lib/api/errors";
import { createUser, getUser, updateUser } from "@/lib/api/users";
import { ApiClientError } from "@/types/api";
import type { UserRole } from "@/types/auth";
import { useEffect, useId, useState, type FormEvent } from "react";

type UserFormDialogProps = {
  mode: "create" | "edit";
  userId: number | null;
  onClose: () => void;
  onSaved: (message: string) => void;
};

type FormState = {
  name: string;
  email: string;
  password: string;
  role: UserRole;
};

const EMPTY_FORM: FormState = {
  name: "",
  email: "",
  password: "",
  role: "CUSTOMER",
};

export function UserFormDialog({ mode, userId, onClose, onSaved }: UserFormDialogProps) {
  const passwordId = useId();
  const [form, setForm] = useState<FormState>(EMPTY_FORM);
  const [showPassword, setShowPassword] = useState(false);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [loadingRecord, setLoadingRecord] = useState(mode === "edit");

  useEffect(() => {
    if (mode !== "edit" || userId === null) {
      return;
    }

    let cancelled = false;
    void getUser(userId)
      .then((user) => {
        if (!cancelled) {
          setForm({
            name: user.name,
            email: user.email,
            password: "",
            role: user.role,
          });
        }
      })
      .catch((caught: unknown) => {
        if (!cancelled) {
          setFormError(mutationErrorMessage(caught, "No se pudo cargar el usuario."));
        }
      })
      .finally(() => {
        if (!cancelled) {
          setLoadingRecord(false);
        }
      });

    return () => {
      cancelled = true;
    };
  }, [mode, userId]);

  function validate(): Record<string, string> {
    const errors: Record<string, string> = {};
    const name = form.name.trim();
    const email = form.email.trim();

    if (!name) {
      errors.name = "El nombre es obligatorio.";
    } else if (name.length > 255) {
      errors.name = "El nombre no puede superar 255 caracteres.";
    }

    if (!email) {
      errors.email = "El correo electrónico es obligatorio.";
    } else if (email.length > 255) {
      errors.email = "El correo electrónico no puede superar 255 caracteres.";
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
      errors.email = "Ingrese un correo electrónico válido.";
    }

    if (mode === "create") {
      if (!form.password) {
        errors.password = "La contraseña es obligatoria.";
      } else if (form.password.length < 8) {
        errors.password = "La contraseña debe tener al menos 8 caracteres.";
      }
    } else if (form.password && form.password.length < 8) {
      errors.password = "La contraseña debe tener al menos 8 caracteres.";
    }

    if (form.role !== "ADMIN" && form.role !== "CUSTOMER") {
      errors.role = "Seleccione un rol.";
    }

    return errors;
  }

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (busy || loadingRecord) {
      return;
    }

    const errors = validate();
    setFieldErrors(errors);
    setFormError(null);
    if (Object.keys(errors).length > 0) {
      return;
    }

    setBusy(true);
    try {
      if (mode === "create") {
        await createUser({
          name: form.name.trim(),
          email: form.email.trim(),
          password: form.password,
          role: form.role,
        });
        onSaved("Usuario creado correctamente.");
      } else if (mode === "edit" && userId !== null) {
        const payload: {
          name: string;
          email: string;
          role: UserRole;
          password?: string;
        } = {
          name: form.name.trim(),
          email: form.email.trim(),
          role: form.role,
        };
        if (form.password.trim().length > 0) {
          payload.password = form.password;
        }
        await updateUser(userId, payload);
        onSaved("Usuario actualizado correctamente.");
      }
    } catch (caught) {
      if (caught instanceof ApiClientError && caught.error.status === 422) {
        const mapped = fieldErrorsFromDetails(caught.error.details);
        setFieldErrors(mapped);
        setFormError(mutationErrorMessage(caught, "Revise los datos ingresados."));
      } else {
        setFormError(mutationErrorMessage(caught, "No se pudo guardar el usuario."));
      }
    } finally {
      setBusy(false);
    }
  }

  return (
    <FormDialog
      open
      title={mode === "edit" ? "Editar usuario" : "Nuevo usuario"}
      description={
        mode === "edit"
          ? "Déjelo vacío para conservar la contraseña actual."
          : "Crea una cuenta administrativa o de cliente."
      }
      busy={busy}
      submitDisabled={loadingRecord}
      submitLabel={mode === "edit" ? "Guardar cambios" : "Crear usuario"}
      onSubmit={onSubmit}
      onClose={onClose}
    >
      {loadingRecord ? <p className="text-sm text-muted">Cargando usuario…</p> : null}

      <div>
        <label htmlFor="user-name" className={labelClassName}>
          Nombre
        </label>
        <input
          id="user-name"
          name="name"
          type="text"
          required
          maxLength={255}
          value={form.name}
          disabled={busy || loadingRecord}
          aria-invalid={Boolean(fieldErrors.name)}
          aria-describedby={fieldErrors.name ? "user-name-error" : undefined}
          onChange={(event) => setForm((current) => ({ ...current, name: event.target.value }))}
          className={inputClassName}
        />
        {fieldErrors.name ? (
          <p id="user-name-error" className="mt-1 text-xs text-danger">
            {fieldErrors.name}
          </p>
        ) : null}
      </div>

      <div>
        <label htmlFor="user-email" className={labelClassName}>
          Correo electrónico
        </label>
        <input
          id="user-email"
          name="email"
          type="email"
          required
          maxLength={255}
          autoComplete="off"
          value={form.email}
          disabled={busy || loadingRecord}
          aria-invalid={Boolean(fieldErrors.email)}
          aria-describedby={fieldErrors.email ? "user-email-error" : undefined}
          onChange={(event) => setForm((current) => ({ ...current, email: event.target.value }))}
          className={inputClassName}
        />
        {fieldErrors.email ? (
          <p id="user-email-error" className="mt-1 text-xs text-danger">
            {fieldErrors.email}
          </p>
        ) : null}
      </div>

      <div>
        <label htmlFor={passwordId} className={labelClassName}>
          {mode === "edit" ? "Nueva contraseña" : "Contraseña"}
        </label>
        <div className="mt-2 flex gap-2">
          <input
            id={passwordId}
            name="password"
            type={showPassword ? "text" : "password"}
            autoComplete="new-password"
            minLength={mode === "create" ? 8 : undefined}
            required={mode === "create"}
            value={form.password}
            disabled={busy || loadingRecord}
            aria-invalid={Boolean(fieldErrors.password)}
            aria-describedby={
              fieldErrors.password
                ? "user-password-error"
                : mode === "edit"
                  ? "user-password-help"
                  : undefined
            }
            onChange={(event) => setForm((current) => ({ ...current, password: event.target.value }))}
            className={`${inputClassName} mt-0`}
          />
          <button
            type="button"
            aria-pressed={showPassword}
            aria-controls={passwordId}
            disabled={busy || loadingRecord}
            onClick={() => setShowPassword((current) => !current)}
            className="inline-flex min-h-11 shrink-0 items-center rounded-lg border border-border px-3 text-xs font-medium text-foreground hover:bg-surface-2 disabled:opacity-60"
          >
            {showPassword ? "Ocultar" : "Mostrar"}
          </button>
        </div>
        {mode === "edit" ? (
          <p id="user-password-help" className={helpClassName}>
            Déjelo vacío para conservar la contraseña actual.
          </p>
        ) : null}
        {fieldErrors.password ? (
          <p id="user-password-error" className="mt-1 text-xs text-danger">
            {fieldErrors.password}
          </p>
        ) : null}
      </div>

      <div>
        <label htmlFor="user-role" className={labelClassName}>
          Rol
        </label>
        <select
          id="user-role"
          name="role"
          required
          value={form.role}
          disabled={busy || loadingRecord}
          aria-invalid={Boolean(fieldErrors.role)}
          aria-describedby={fieldErrors.role ? "user-role-error" : undefined}
          onChange={(event) =>
            setForm((current) => ({ ...current, role: event.target.value as UserRole }))
          }
          className={inputClassName}
        >
          <option value="CUSTOMER">CUSTOMER</option>
          <option value="ADMIN">ADMIN</option>
        </select>
        {fieldErrors.role ? (
          <p id="user-role-error" className="mt-1 text-xs text-danger">
            {fieldErrors.role}
          </p>
        ) : null}
      </div>

      {formError ? (
        <p role="alert" className="rounded-lg border border-danger/40 bg-danger/10 px-3 py-2 text-sm text-danger">
          {formError}
        </p>
      ) : null}
    </FormDialog>
  );
}
