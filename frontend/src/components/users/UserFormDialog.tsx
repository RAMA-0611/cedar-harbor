"use client";

import { FormDialog } from "@/components/admin/crud/FormDialog";
import { helpClassName, inputClassName, labelClassName } from "@/components/admin/crud/fieldStyles";
import { fieldErrorsFromDetails, mutationErrorMessage } from "@/lib/api/errors";
import { createUser, getUser, updateUser } from "@/lib/api/users";
import {
  DOCUMENT_TYPES,
  sanitizeDocumentNumber,
  sanitizePhone,
  validateDocumentNumber,
  validateEmail,
  validateName,
  validatePassword,
  validatePhone,
} from "@/lib/validation";
import { ApiClientError } from "@/types/api";
import type { UserRole } from "@/types/auth";
import { useEffect, useId, useState, type FormEvent } from "react";
import { PasswordInput } from "@/components/ui/PasswordInput";

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
  phone: string;
  document_type: string;
  document_number: string;
};

const EMPTY_FORM: FormState = {
  name: "",
  email: "",
  password: "",
  role: "CUSTOMER",
  phone: "",
  document_type: "CC",
  document_number: "",
};

export function UserFormDialog({ mode, userId, onClose, onSaved }: UserFormDialogProps) {
  const passwordId = useId();
  const [form, setForm] = useState<FormState>(EMPTY_FORM);
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
            phone: "",
            document_type: "CC",
            document_number: "",
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

    const nameError = validateName(name);
    if (nameError) errors.name = nameError;

    const emailError = validateEmail(email);
    if (emailError) errors.email = emailError;

    if (mode === "create" || form.password) {
      const passwordError = validatePassword(form.password);
      if (passwordError) errors.password = passwordError;
    }

    const wantsProfile = form.role === "CUSTOMER" && (mode === "create" || form.phone || form.document_number);
    if (wantsProfile) {
      const phoneError = validatePhone(form.phone);
      if (phoneError) errors.phone = phoneError;
      const docError = validateDocumentNumber(form.document_type, form.document_number);
      if (docError) errors.document_number = docError;
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
          ...(form.role === "CUSTOMER"
            ? {
                phone: form.phone,
                document_type: form.document_type,
                document_number: form.document_number,
              }
            : {}),
        });
        onSaved("Usuario creado correctamente.");
      } else if (mode === "edit" && userId !== null) {
        const payload: {
          name: string;
          email: string;
          role: UserRole;
          password?: string;
          phone?: string;
          document_type?: string;
          document_number?: string;
        } = {
          name: form.name.trim(),
          email: form.email.trim(),
          role: form.role,
        };
        if (form.password.trim().length > 0) {
          payload.password = form.password;
        }
        if (form.role === "CUSTOMER" && form.phone && form.document_number) {
          payload.phone = form.phone;
          payload.document_type = form.document_type;
          payload.document_number = form.document_number;
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
          maxLength={100}
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
        <div className="mt-2">
          <PasswordInput
            id={passwordId}
            name="password"
            autoComplete="new-password"
            maxLength={72}
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
            className={inputClassName}
          />
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

      {form.role === "CUSTOMER" ? (
        <>
          <div>
            <label htmlFor="user-phone" className={labelClassName}>
              Teléfono celular
            </label>
            <input
              id="user-phone"
              name="phone"
              type="tel"
              inputMode="numeric"
              placeholder="3001234567"
              value={form.phone}
              disabled={busy || loadingRecord}
              aria-invalid={Boolean(fieldErrors.phone)}
              onChange={(event) => setForm((current) => ({ ...current, phone: sanitizePhone(event.target.value) }))}
              className={inputClassName}
            />
            <p className={helpClassName}>10 dígitos, solo números, empieza por 3.</p>
            {fieldErrors.phone ? <p className="mt-1 text-xs text-danger">{fieldErrors.phone}</p> : null}
          </div>
          <div>
            <label htmlFor="user-document-type" className={labelClassName}>
              Tipo de documento
            </label>
            <select
              id="user-document-type"
              name="document_type"
              value={form.document_type}
              disabled={busy || loadingRecord}
              onChange={(event) =>
                setForm((current) => ({
                  ...current,
                  document_type: event.target.value,
                  document_number: sanitizeDocumentNumber(current.document_number, event.target.value),
                }))
              }
              className={inputClassName}
            >
              {DOCUMENT_TYPES.map((type) => (
                <option key={type.value} value={type.value}>
                  {type.label}
                </option>
              ))}
            </select>
          </div>
          <div>
            <label htmlFor="user-document-number" className={labelClassName}>
              Número de documento
            </label>
            <input
              id="user-document-number"
              name="document_number"
              inputMode={form.document_type === "PP" ? "text" : "numeric"}
              value={form.document_number}
              disabled={busy || loadingRecord}
              aria-invalid={Boolean(fieldErrors.document_number)}
              onChange={(event) =>
                setForm((current) => ({
                  ...current,
                  document_number: sanitizeDocumentNumber(event.target.value, current.document_type),
                }))
              }
              className={inputClassName}
            />
            {fieldErrors.document_number ? (
              <p className="mt-1 text-xs text-danger">{fieldErrors.document_number}</p>
            ) : null}
          </div>
        </>
      ) : null}

      {formError ? (
        <p role="alert" className="rounded-lg border border-danger/40 bg-danger/10 px-3 py-2 text-sm text-danger">
          {formError}
        </p>
      ) : null}
    </FormDialog>
  );
}
