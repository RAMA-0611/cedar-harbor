"use client";

import { FormDialog } from "@/components/admin/crud/FormDialog";
import { helpClassName, inputClassName, labelClassName } from "@/components/admin/crud/fieldStyles";
import { createCategory, getCategory, updateCategory } from "@/lib/api/categories";
import { fieldErrorsFromDetails, mutationErrorMessage } from "@/lib/api/errors";
import { isValidSlug, SLUG_HELP, SLUG_PATTERN_SOURCE, slugifyName } from "@/lib/slug";
import { ApiClientError } from "@/types/api";
import { useEffect, useState, type FormEvent } from "react";

type CategoryFormDialogProps = {
  mode: "create" | "edit";
  categoryId: string | null;
  onClose: () => void;
  onSaved: (message: string) => void;
};

type FormState = {
  name: string;
  slug: string;
  description: string;
  is_active: boolean;
};

const EMPTY_FORM: FormState = {
  name: "",
  slug: "",
  description: "",
  is_active: true,
};

export function CategoryFormDialog({
  mode,
  categoryId,
  onClose,
  onSaved,
}: CategoryFormDialogProps) {
  const [form, setForm] = useState<FormState>(EMPTY_FORM);
  const [slugTouched, setSlugTouched] = useState(mode === "edit");
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [loadingRecord, setLoadingRecord] = useState(mode === "edit");

  useEffect(() => {
    if (mode !== "edit" || !categoryId) {
      return;
    }

    let cancelled = false;
    void getCategory(categoryId)
      .then((category) => {
        if (!cancelled) {
          setForm({
            name: category.name,
            slug: category.slug,
            description: category.description ?? "",
            is_active: category.is_active,
          });
        }
      })
      .catch((caught: unknown) => {
        if (!cancelled) {
          setFormError(mutationErrorMessage(caught, "No se pudo cargar la categoría."));
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
  }, [mode, categoryId]);

  function validate(): Record<string, string> {
    const errors: Record<string, string> = {};
    const name = form.name.trim();
    const slug = form.slug.trim();

    if (!name) {
      errors.name = "El nombre es obligatorio.";
    } else if (name.length > 160) {
      errors.name = "El nombre no puede superar 160 caracteres.";
    }

    if (!slug) {
      errors.slug = "El slug es obligatorio.";
    } else if (slug.length > 180) {
      errors.slug = "El slug no puede superar 180 caracteres.";
    } else if (!isValidSlug(slug)) {
      errors.slug = SLUG_HELP;
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

    const payload = {
      name: form.name.trim(),
      slug: form.slug.trim(),
      description: form.description.trim() ? form.description.trim() : null,
      is_active: form.is_active,
    };

    setBusy(true);
    try {
      if (mode === "create") {
        await createCategory(payload);
        onSaved("Categoría creada correctamente.");
      } else if (mode === "edit" && categoryId) {
        await updateCategory(categoryId, payload);
        onSaved("Categoría actualizada correctamente.");
      }
    } catch (caught) {
      if (caught instanceof ApiClientError && caught.error.status === 422) {
        const mapped = fieldErrorsFromDetails(caught.error.details);
        setFieldErrors(mapped);
        setFormError(mutationErrorMessage(caught, "Revise los datos ingresados."));
      } else {
        setFormError(mutationErrorMessage(caught, "No se pudo guardar la categoría."));
      }
    } finally {
      setBusy(false);
    }
  }

  return (
    <FormDialog
      open
      title={mode === "edit" ? "Editar categoría" : "Nueva categoría"}
      description="Los cambios se persisten en PostgreSQL mediante la API administrativa."
      busy={busy}
      submitDisabled={loadingRecord}
      submitLabel={mode === "edit" ? "Guardar cambios" : "Crear categoría"}
      onSubmit={onSubmit}
      onClose={onClose}
    >
      {loadingRecord ? <p className="text-sm text-muted">Cargando categoría…</p> : null}

      <div>
        <label htmlFor="category-name" className={labelClassName}>
          Nombre
        </label>
        <input
          id="category-name"
          name="name"
          type="text"
          required
          maxLength={160}
          value={form.name}
          disabled={busy || loadingRecord}
          aria-invalid={Boolean(fieldErrors.name)}
          aria-describedby={fieldErrors.name ? "category-name-error" : undefined}
          onChange={(event) => {
            const name = event.target.value;
            setForm((current) => ({
              ...current,
              name,
              slug: slugTouched ? current.slug : slugifyName(name),
            }));
          }}
          className={inputClassName}
        />
        {fieldErrors.name ? (
          <p id="category-name-error" className="mt-1 text-xs text-danger">
            {fieldErrors.name}
          </p>
        ) : null}
      </div>

      <div>
        <label htmlFor="category-slug" className={labelClassName}>
          Slug
        </label>
        <input
          id="category-slug"
          name="slug"
          type="text"
          required
          maxLength={180}
          pattern={SLUG_PATTERN_SOURCE}
          value={form.slug}
          disabled={busy || loadingRecord}
          aria-invalid={Boolean(fieldErrors.slug)}
          aria-describedby={fieldErrors.slug ? "category-slug-error" : "category-slug-help"}
          onChange={(event) => {
            setSlugTouched(true);
            setForm((current) => ({ ...current, slug: event.target.value }));
          }}
          className={inputClassName}
        />
        <p id="category-slug-help" className={helpClassName}>
          {SLUG_HELP}
        </p>
        {fieldErrors.slug ? (
          <p id="category-slug-error" className="mt-1 text-xs text-danger">
            {fieldErrors.slug}
          </p>
        ) : null}
      </div>

      <div>
        <label htmlFor="category-description" className={labelClassName}>
          Descripción
        </label>
        <textarea
          id="category-description"
          name="description"
          rows={3}
          value={form.description}
          disabled={busy || loadingRecord}
          onChange={(event) =>
            setForm((current) => ({ ...current, description: event.target.value }))
          }
          className={`${inputClassName} py-2`}
        />
      </div>

      <div className="flex items-center gap-3">
        <input
          id="category-active"
          name="is_active"
          type="checkbox"
          checked={form.is_active}
          disabled={busy || loadingRecord}
          onChange={(event) =>
            setForm((current) => ({ ...current, is_active: event.target.checked }))
          }
          className="size-4 rounded border-border accent-accent"
        />
        <label htmlFor="category-active" className="text-sm text-foreground">
          Categoría activa
        </label>
      </div>
      <p className="text-xs text-muted">{form.is_active ? "Estado: Activa" : "Estado: Inactiva"}</p>

      {formError ? (
        <p role="alert" className="rounded-lg border border-danger/40 bg-danger/10 px-3 py-2 text-sm text-danger">
          {formError}
        </p>
      ) : null}
    </FormDialog>
  );
}
