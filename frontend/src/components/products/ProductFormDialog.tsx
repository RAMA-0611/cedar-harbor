"use client";

import { FormDialog } from "@/components/admin/crud/FormDialog";
import { helpClassName, inputClassName, labelClassName } from "@/components/admin/crud/fieldStyles";
import { listCategories } from "@/lib/api/categories";
import { fieldErrorsFromDetails, mutationErrorMessage } from "@/lib/api/errors";
import { createProduct, getProduct, updateProduct } from "@/lib/api/products";
import { percentInputToTaxRate, taxRateToPercentInput } from "@/lib/format";
import { ApiClientError, type AdminCategory, type CreateProductPayload } from "@/types/api";
import { useEffect, useState, type FormEvent } from "react";

type ProductFormDialogProps = {
  mode: "create" | "edit";
  productId: string | null;
  onClose: () => void;
  onSaved: (message: string) => void;
};

type FormState = {
  category_id: string;
  sku: string;
  name: string;
  description: string;
  brand: string;
  condition: string;
  unit: string;
  price: string;
  tax_percent: string;
  stock: string;
  is_active: boolean;
};

const EMPTY_FORM: FormState = {
  category_id: "",
  sku: "",
  name: "",
  description: "",
  brand: "",
  condition: "",
  unit: "",
  price: "",
  tax_percent: "",
  stock: "",
  is_active: true,
};

function emptyToNull(value: string): string | null {
  const trimmed = value.trim();
  return trimmed === "" ? null : trimmed;
}

function parseStock(value: string): number | null {
  const trimmed = value.trim();
  if (!/^\d+$/.test(trimmed)) {
    return null;
  }
  const parsed = Number(trimmed);
  if (!Number.isInteger(parsed) || parsed < 0) {
    return null;
  }
  return parsed;
}

export function ProductFormDialog({
  mode,
  productId,
  onClose,
  onSaved,
}: ProductFormDialogProps) {
  const [form, setForm] = useState<FormState>(EMPTY_FORM);
  const [categories, setCategories] = useState<AdminCategory[]>([]);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [loadingRecord, setLoadingRecord] = useState(mode === "edit");
  const [loadingCategories, setLoadingCategories] = useState(true);
  const [categoriesError, setCategoriesError] = useState<string | null>(null);

  useEffect(() => {
    let cancelled = false;

    void listCategories()
      .then((rows) => {
        if (!cancelled) {
          setCategories(rows);
          setCategoriesError(null);
        }
      })
      .catch((caught: unknown) => {
        if (!cancelled) {
          setCategories([]);
          setCategoriesError(
            mutationErrorMessage(caught, "No se pudieron cargar las categorías."),
          );
        }
      })
      .finally(() => {
        if (!cancelled) {
          setLoadingCategories(false);
        }
      });

    if (mode !== "edit" || !productId) {
      return () => {
        cancelled = true;
      };
    }

    void getProduct(productId)
      .then((product) => {
        if (!cancelled) {
          setForm({
            category_id: product.category_id,
            sku: product.sku,
            name: product.name,
            description: product.description ?? "",
            brand: product.brand ?? "",
            condition: product.condition ?? "",
            unit: product.unit ?? "",
            price: product.price,
            tax_percent: taxRateToPercentInput(product.tax_rate),
            stock: String(product.stock_quantity),
            is_active: product.is_active,
          });
        }
      })
      .catch((caught: unknown) => {
        if (!cancelled) {
          setFormError(mutationErrorMessage(caught, "No se pudo cargar el producto."));
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
  }, [mode, productId]);

  function retryCategories() {
    setLoadingCategories(true);
    setCategoriesError(null);
    void listCategories()
      .then((rows) => {
        setCategories(rows);
        setCategoriesError(null);
      })
      .catch((caught: unknown) => {
        setCategories([]);
        setCategoriesError(mutationErrorMessage(caught, "No se pudieron cargar las categorías."));
      })
      .finally(() => {
        setLoadingCategories(false);
      });
  }

  function validate(): Record<string, string> {
    const errors: Record<string, string> = {};
    const sku = form.sku.trim();
    const name = form.name.trim();
    const price = form.price.trim();
    const taxPercent = form.tax_percent.trim();
    const brand = form.brand.trim();
    const condition = form.condition.trim();
    const unit = form.unit.trim();

    if (!form.category_id) {
      errors.category_id = "La categoría es obligatoria.";
    }

    if (!sku) {
      errors.sku = "El SKU es obligatorio.";
    } else if (sku.length > 64) {
      errors.sku = "El SKU no puede superar 64 caracteres.";
    }

    if (!name) {
      errors.name = "El nombre es obligatorio.";
    } else if (name.length > 255) {
      errors.name = "El nombre no puede superar 255 caracteres.";
    }

    if (brand.length > 120) {
      errors.brand = "La marca no puede superar 120 caracteres.";
    }
    if (condition.length > 40) {
      errors.condition = "La condición no puede superar 40 caracteres.";
    }
    if (unit.length > 60) {
      errors.unit = "La unidad no puede superar 60 caracteres.";
    }

    if (!price) {
      errors.price = "El precio es obligatorio.";
    } else {
      const amount = Number(price);
      if (!Number.isFinite(amount) || amount < 0) {
        errors.price = "El precio debe ser un número mayor o igual a 0.";
      }
    }

    if (!taxPercent) {
      errors.tax_rate = "El impuesto es obligatorio.";
    } else {
      const percent = Number(taxPercent);
      if (!Number.isFinite(percent) || percent < 0 || percent > 100) {
        errors.tax_rate = "El impuesto debe estar entre 0 % y 100 %.";
      }
    }

    if (!form.stock.trim()) {
      errors.stock_quantity = "El stock es obligatorio.";
    } else if (parseStock(form.stock) === null) {
      errors.stock_quantity = "El stock debe ser un entero mayor o igual a 0.";
    }

    return errors;
  }

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (busy || loadingRecord || loadingCategories || categoriesError || categories.length === 0) {
      return;
    }

    const errors = validate();
    setFieldErrors(errors);
    setFormError(null);
    if (Object.keys(errors).length > 0) {
      return;
    }

    const stockQuantity = parseStock(form.stock);
    const taxRate = percentInputToTaxRate(form.tax_percent);
    if (stockQuantity === null || taxRate === "") {
      return;
    }

    const payload: CreateProductPayload = {
      category_id: form.category_id,
      sku: form.sku.trim(),
      name: form.name.trim(),
      description: emptyToNull(form.description),
      brand: emptyToNull(form.brand),
      condition: emptyToNull(form.condition),
      unit: emptyToNull(form.unit),
      price: form.price.trim(),
      currency: "COP",
      tax_rate: taxRate,
      stock_quantity: stockQuantity,
      is_active: form.is_active,
    };

    setBusy(true);
    try {
      if (mode === "create") {
        await createProduct(payload);
        onSaved("Producto creado correctamente.");
      } else if (mode === "edit" && productId) {
        await updateProduct(productId, payload);
        onSaved("Producto actualizado correctamente.");
      }
    } catch (caught) {
      if (caught instanceof ApiClientError && caught.error.status === 422) {
        const mapped = fieldErrorsFromDetails(caught.error.details);
        setFieldErrors(mapped);
        setFormError(mutationErrorMessage(caught, "Revise los datos ingresados."));
      } else {
        setFormError(mutationErrorMessage(caught, "No se pudo guardar el producto."));
      }
    } finally {
      setBusy(false);
    }
  }

  const relationBlocked = loadingCategories || Boolean(categoriesError) || categories.length === 0;
  const fieldsDisabled = busy || loadingRecord || relationBlocked;

  return (
    <FormDialog
      open
      title={mode === "edit" ? "Editar producto" : "Nuevo producto"}
      description="Los cambios se persisten en PostgreSQL mediante la API administrativa."
      busy={busy}
      submitDisabled={loadingRecord || relationBlocked}
      submitLabel={mode === "edit" ? "Guardar cambios" : "Crear producto"}
      onSubmit={onSubmit}
      onClose={onClose}
    >
      {loadingRecord ? <p className="text-sm text-muted">Cargando producto…</p> : null}

      <div>
        <label htmlFor="product-category" className={labelClassName}>
          Categoría
        </label>
        {loadingCategories ? (
          <p className="mt-2 text-sm text-muted">Cargando categorías…</p>
        ) : categoriesError ? (
          <div className="mt-2 space-y-2">
            <p role="alert" className="text-sm text-danger">
              No se pudieron cargar las categorías.
            </p>
            <button
              type="button"
              className="inline-flex min-h-9 items-center rounded-lg border border-border px-3 text-xs font-medium text-foreground hover:bg-surface-2"
              onClick={retryCategories}
            >
              Reintentar
            </button>
          </div>
        ) : (
          <select
            id="product-category"
            name="category_id"
            required
            value={form.category_id}
            disabled={fieldsDisabled}
            aria-invalid={Boolean(fieldErrors.category_id)}
            aria-describedby={fieldErrors.category_id ? "product-category-error" : undefined}
            onChange={(event) =>
              setForm((current) => ({ ...current, category_id: event.target.value }))
            }
            className={inputClassName}
          >
            <option value="">Seleccione una categoría</option>
            {categories.map((category) => (
              <option key={category.id} value={category.id}>
                {category.is_active ? category.name : `${category.name} (Inactiva)`}
              </option>
            ))}
          </select>
        )}
        {fieldErrors.category_id ? (
          <p id="product-category-error" className="mt-1 text-xs text-danger">
            {fieldErrors.category_id}
          </p>
        ) : null}
      </div>

      <div className="grid gap-4 sm:grid-cols-2">
        <div>
          <label htmlFor="product-sku" className={labelClassName}>
            SKU
          </label>
          <input
            id="product-sku"
            name="sku"
            type="text"
            required
            maxLength={64}
            value={form.sku}
            disabled={fieldsDisabled}
            aria-invalid={Boolean(fieldErrors.sku)}
            aria-describedby={fieldErrors.sku ? "product-sku-error" : undefined}
            onChange={(event) => setForm((current) => ({ ...current, sku: event.target.value }))}
            className={inputClassName}
          />
          {fieldErrors.sku ? (
            <p id="product-sku-error" className="mt-1 text-xs text-danger">
              {fieldErrors.sku}
            </p>
          ) : null}
        </div>

        <div>
          <label htmlFor="product-name" className={labelClassName}>
            Nombre
          </label>
          <input
            id="product-name"
            name="name"
            type="text"
            required
            maxLength={255}
            value={form.name}
            disabled={fieldsDisabled}
            aria-invalid={Boolean(fieldErrors.name)}
            aria-describedby={fieldErrors.name ? "product-name-error" : undefined}
            onChange={(event) => setForm((current) => ({ ...current, name: event.target.value }))}
            className={inputClassName}
          />
          {fieldErrors.name ? (
            <p id="product-name-error" className="mt-1 text-xs text-danger">
              {fieldErrors.name}
            </p>
          ) : null}
        </div>
      </div>

      <div>
        <label htmlFor="product-description" className={labelClassName}>
          Descripción
        </label>
        <textarea
          id="product-description"
          name="description"
          rows={3}
          value={form.description}
          disabled={fieldsDisabled}
          onChange={(event) =>
            setForm((current) => ({ ...current, description: event.target.value }))
          }
          className={`${inputClassName} py-2`}
        />
      </div>

      <div className="grid gap-4 sm:grid-cols-2">
        <div>
          <label htmlFor="product-brand" className={labelClassName}>
            Marca
          </label>
          <input
            id="product-brand"
            name="brand"
            type="text"
            maxLength={120}
            value={form.brand}
            disabled={fieldsDisabled}
            aria-invalid={Boolean(fieldErrors.brand)}
            aria-describedby={fieldErrors.brand ? "product-brand-error" : undefined}
            onChange={(event) => setForm((current) => ({ ...current, brand: event.target.value }))}
            className={inputClassName}
          />
          {fieldErrors.brand ? (
            <p id="product-brand-error" className="mt-1 text-xs text-danger">
              {fieldErrors.brand}
            </p>
          ) : null}
        </div>

        <div>
          <label htmlFor="product-condition" className={labelClassName}>
            Condición
          </label>
          <input
            id="product-condition"
            name="condition"
            type="text"
            maxLength={40}
            value={form.condition}
            disabled={fieldsDisabled}
            aria-invalid={Boolean(fieldErrors.condition)}
            aria-describedby={fieldErrors.condition ? "product-condition-error" : undefined}
            onChange={(event) =>
              setForm((current) => ({ ...current, condition: event.target.value }))
            }
            className={inputClassName}
          />
          {fieldErrors.condition ? (
            <p id="product-condition-error" className="mt-1 text-xs text-danger">
              {fieldErrors.condition}
            </p>
          ) : null}
        </div>
      </div>

      <div>
        <label htmlFor="product-unit" className={labelClassName}>
          Unidad
        </label>
        <input
          id="product-unit"
          name="unit"
          type="text"
          maxLength={60}
          value={form.unit}
          disabled={fieldsDisabled}
          aria-invalid={Boolean(fieldErrors.unit)}
          aria-describedby={fieldErrors.unit ? "product-unit-error" : undefined}
          onChange={(event) => setForm((current) => ({ ...current, unit: event.target.value }))}
          className={inputClassName}
        />
        {fieldErrors.unit ? (
          <p id="product-unit-error" className="mt-1 text-xs text-danger">
            {fieldErrors.unit}
          </p>
        ) : null}
      </div>

      <div className="grid gap-4 sm:grid-cols-2">
        <div>
          <label htmlFor="product-price" className={labelClassName}>
            Precio (COP)
          </label>
          <input
            id="product-price"
            name="price"
            type="number"
            inputMode="decimal"
            required
            min={0}
            step="0.01"
            value={form.price}
            disabled={fieldsDisabled}
            aria-invalid={Boolean(fieldErrors.price)}
            aria-describedby={fieldErrors.price ? "product-price-error" : "product-price-help"}
            onChange={(event) => setForm((current) => ({ ...current, price: event.target.value }))}
            className={inputClassName}
          />
          <p id="product-price-help" className={helpClassName}>
            Moneda: COP
          </p>
          {fieldErrors.price ? (
            <p id="product-price-error" className="mt-1 text-xs text-danger">
              {fieldErrors.price}
            </p>
          ) : null}
        </div>

        <div>
          <label htmlFor="product-tax" className={labelClassName}>
            Impuesto (%)
          </label>
          <input
            id="product-tax"
            name="tax_percent"
            type="number"
            inputMode="decimal"
            required
            min={0}
            max={100}
            step="0.01"
            value={form.tax_percent}
            disabled={fieldsDisabled}
            aria-invalid={Boolean(fieldErrors.tax_rate)}
            aria-describedby={fieldErrors.tax_rate ? "product-tax-error" : "product-tax-help"}
            onChange={(event) =>
              setForm((current) => ({ ...current, tax_percent: event.target.value }))
            }
            className={inputClassName}
          />
          <p id="product-tax-help" className={helpClassName}>
            Ejemplo: 19 para IVA 19 %.
          </p>
          {fieldErrors.tax_rate ? (
            <p id="product-tax-error" className="mt-1 text-xs text-danger">
              {fieldErrors.tax_rate}
            </p>
          ) : null}
        </div>
      </div>

      <div>
        <label htmlFor="product-stock" className={labelClassName}>
          Stock
        </label>
        <input
          id="product-stock"
          name="stock_quantity"
          type="number"
          inputMode="numeric"
          required
          min={0}
          step={1}
          value={form.stock}
          disabled={fieldsDisabled}
          aria-invalid={Boolean(fieldErrors.stock_quantity)}
          aria-describedby={fieldErrors.stock_quantity ? "product-stock-error" : undefined}
          onChange={(event) => setForm((current) => ({ ...current, stock: event.target.value }))}
          className={inputClassName}
        />
        {fieldErrors.stock_quantity ? (
          <p id="product-stock-error" className="mt-1 text-xs text-danger">
            {fieldErrors.stock_quantity}
          </p>
        ) : null}
      </div>

      <div className="flex items-center gap-3">
        <input
          id="product-active"
          name="is_active"
          type="checkbox"
          checked={form.is_active}
          disabled={fieldsDisabled}
          onChange={(event) =>
            setForm((current) => ({ ...current, is_active: event.target.checked }))
          }
          className="size-4 rounded border-border accent-accent"
        />
        <label htmlFor="product-active" className="text-sm text-foreground">
          Producto activo
        </label>
      </div>
      <p className="text-xs text-muted">{form.is_active ? "Estado: Activo" : "Estado: Inactivo"}</p>

      {formError ? (
        <p
          role="alert"
          className="rounded-lg border border-danger/40 bg-danger/10 px-3 py-2 text-sm text-danger"
        >
          {formError}
        </p>
      ) : null}
    </FormDialog>
  );
}
