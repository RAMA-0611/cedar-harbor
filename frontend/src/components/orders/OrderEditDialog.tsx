"use client";

import { FormDialog } from "@/components/admin/crud/FormDialog";
import { helpClassName, inputClassName, labelClassName } from "@/components/admin/crud/fieldStyles";
import { fieldErrorsFromDetails, mutationErrorMessage } from "@/lib/api/errors";
import { getOrder, updateOrder } from "@/lib/api/orders";
import { ApiClientError, type AdminOrderUpdatePayload } from "@/types/api";
import { useEffect, useState, type FormEvent } from "react";

type OrderEditDialogProps = {
  orderId: string;
  onClose: () => void;
  onSaved: (message: string) => void;
};

type FormState = {
  customer_name: string;
  customer_email: string;
  customer_phone: string;
  customer_document_type: string;
  customer_document_number: string;
  delivery_mode: "PICKUP" | "COORDINATED_SHIPPING";
  delivery_address: string;
};

const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

export function OrderEditDialog({ orderId, onClose, onSaved }: OrderEditDialogProps) {
  const [form, setForm] = useState<FormState | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [formError, setFormError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);
  const [loadingRecord, setLoadingRecord] = useState(true);

  useEffect(() => {
    let cancelled = false;
    void getOrder(orderId)
      .then((order) => {
        if (!cancelled) {
          setForm({
            customer_name: order.customer.name,
            customer_email: order.customer.email,
            customer_phone: order.customer.phone,
            customer_document_type: order.customer.document_type,
            customer_document_number: order.customer.document_number,
            delivery_mode: order.delivery.mode,
            delivery_address: order.delivery.address ?? "",
          });
        }
      })
      .catch((caught: unknown) => {
        if (!cancelled) {
          setFormError(mutationErrorMessage(caught, "No se pudo cargar el pedido."));
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
  }, [orderId]);

  function validate(current: FormState): Record<string, string> {
    const errors: Record<string, string> = {};
    const name = current.customer_name.trim();
    const email = current.customer_email.trim();
    const phone = current.customer_phone.trim();
    const documentType = current.customer_document_type.trim();
    const documentNumber = current.customer_document_number.trim();
    const address = current.delivery_address.trim();

    if (!name) {
      errors.customer_name = "El nombre es obligatorio.";
    } else if (name.length > 160) {
      errors.customer_name = "El nombre no puede superar 160 caracteres.";
    }

    if (!email) {
      errors.customer_email = "El correo es obligatorio.";
    } else if (email.length > 254) {
      errors.customer_email = "El correo no puede superar 254 caracteres.";
    } else if (!EMAIL_PATTERN.test(email)) {
      errors.customer_email = "Ingrese un correo electrónico válido.";
    }

    if (!phone) {
      errors.customer_phone = "El teléfono es obligatorio.";
    } else if (phone.length > 30) {
      errors.customer_phone = "El teléfono no puede superar 30 caracteres.";
    }

    if (!documentType) {
      errors.customer_document_type = "El tipo de documento es obligatorio.";
    } else if (documentType.length > 10) {
      errors.customer_document_type = "El tipo de documento no puede superar 10 caracteres.";
    }

    if (!documentNumber) {
      errors.customer_document_number = "El número de documento es obligatorio.";
    } else if (documentNumber.length > 30) {
      errors.customer_document_number = "El número de documento no puede superar 30 caracteres.";
    }

    if (current.delivery_mode === "COORDINATED_SHIPPING") {
      if (address.length < 5) {
        errors.delivery_address = "La dirección debe tener al menos 5 caracteres.";
      } else if (address.length > 255) {
        errors.delivery_address = "La dirección no puede superar 255 caracteres.";
      }
    }

    return errors;
  }

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (!form || busy || loadingRecord) {
      return;
    }

    const errors = validate(form);
    setFieldErrors(errors);
    setFormError(null);
    if (Object.keys(errors).length > 0) {
      return;
    }

    const payload: AdminOrderUpdatePayload = {
      customer_name: form.customer_name.trim(),
      customer_email: form.customer_email.trim(),
      customer_phone: form.customer_phone.trim(),
      customer_document_type: form.customer_document_type.trim(),
      customer_document_number: form.customer_document_number.trim(),
      delivery_mode: form.delivery_mode,
      delivery_address: form.delivery_mode === "PICKUP" ? null : form.delivery_address.trim(),
    };

    setBusy(true);
    try {
      await updateOrder(orderId, payload);
      onSaved("Pedido actualizado correctamente.");
    } catch (caught) {
      if (caught instanceof ApiClientError && caught.error.status === 422) {
        const mapped = fieldErrorsFromDetails(caught.error.details);
        setFieldErrors(mapped);
        setFormError(mutationErrorMessage(caught, "Revise los datos ingresados."));
      } else {
        setFormError(mutationErrorMessage(caught, "No se pudo actualizar el pedido."));
      }
    } finally {
      setBusy(false);
    }
  }

  const fieldsDisabled = busy || loadingRecord || !form;

  return (
    <FormDialog
      open
      title="Editar pedido"
      description="Solo se actualizan datos de contacto y entrega. Totales, ítems y estado no son editables aquí."
      busy={busy}
      submitDisabled={loadingRecord || !form}
      submitLabel="Guardar cambios"
      onSubmit={onSubmit}
      onClose={onClose}
    >
      {loadingRecord ? <p className="text-sm text-muted">Cargando pedido…</p> : null}

      <div>
        <label htmlFor="order-customer-name" className={labelClassName}>
          Nombre cliente
        </label>
        <input
          id="order-customer-name"
          name="customer_name"
          type="text"
          required
          maxLength={160}
          value={form?.customer_name ?? ""}
          disabled={fieldsDisabled}
          aria-invalid={Boolean(fieldErrors.customer_name)}
          aria-describedby={fieldErrors.customer_name ? "order-customer-name-error" : undefined}
          onChange={(event) =>
            setForm((current) =>
              current ? { ...current, customer_name: event.target.value } : current,
            )
          }
          className={inputClassName}
        />
        {fieldErrors.customer_name ? (
          <p id="order-customer-name-error" className="mt-1 text-xs text-danger">
            {fieldErrors.customer_name}
          </p>
        ) : null}
      </div>

      <div>
        <label htmlFor="order-customer-email" className={labelClassName}>
          Correo cliente
        </label>
        <input
          id="order-customer-email"
          name="customer_email"
          type="email"
          required
          maxLength={254}
          value={form?.customer_email ?? ""}
          disabled={fieldsDisabled}
          aria-invalid={Boolean(fieldErrors.customer_email)}
          aria-describedby={fieldErrors.customer_email ? "order-customer-email-error" : undefined}
          onChange={(event) =>
            setForm((current) =>
              current ? { ...current, customer_email: event.target.value } : current,
            )
          }
          className={inputClassName}
        />
        {fieldErrors.customer_email ? (
          <p id="order-customer-email-error" className="mt-1 text-xs text-danger">
            {fieldErrors.customer_email}
          </p>
        ) : null}
      </div>

      <div>
        <label htmlFor="order-customer-phone" className={labelClassName}>
          Teléfono
        </label>
        <input
          id="order-customer-phone"
          name="customer_phone"
          type="text"
          required
          maxLength={30}
          value={form?.customer_phone ?? ""}
          disabled={fieldsDisabled}
          aria-invalid={Boolean(fieldErrors.customer_phone)}
          aria-describedby={fieldErrors.customer_phone ? "order-customer-phone-error" : undefined}
          onChange={(event) =>
            setForm((current) =>
              current ? { ...current, customer_phone: event.target.value } : current,
            )
          }
          className={inputClassName}
        />
        {fieldErrors.customer_phone ? (
          <p id="order-customer-phone-error" className="mt-1 text-xs text-danger">
            {fieldErrors.customer_phone}
          </p>
        ) : null}
      </div>

      <div className="grid gap-4 sm:grid-cols-2">
        <div>
          <label htmlFor="order-document-type" className={labelClassName}>
            Tipo documento
          </label>
          <input
            id="order-document-type"
            name="customer_document_type"
            type="text"
            required
            maxLength={10}
            value={form?.customer_document_type ?? ""}
            disabled={fieldsDisabled}
            aria-invalid={Boolean(fieldErrors.customer_document_type)}
            aria-describedby={
              fieldErrors.customer_document_type ? "order-document-type-error" : undefined
            }
            onChange={(event) =>
              setForm((current) =>
                current ? { ...current, customer_document_type: event.target.value } : current,
              )
            }
            className={inputClassName}
          />
          {fieldErrors.customer_document_type ? (
            <p id="order-document-type-error" className="mt-1 text-xs text-danger">
              {fieldErrors.customer_document_type}
            </p>
          ) : null}
        </div>
        <div>
          <label htmlFor="order-document-number" className={labelClassName}>
            Número documento
          </label>
          <input
            id="order-document-number"
            name="customer_document_number"
            type="text"
            required
            maxLength={30}
            value={form?.customer_document_number ?? ""}
            disabled={fieldsDisabled}
            aria-invalid={Boolean(fieldErrors.customer_document_number)}
            aria-describedby={
              fieldErrors.customer_document_number ? "order-document-number-error" : undefined
            }
            onChange={(event) =>
              setForm((current) =>
                current ? { ...current, customer_document_number: event.target.value } : current,
              )
            }
            className={inputClassName}
          />
          {fieldErrors.customer_document_number ? (
            <p id="order-document-number-error" className="mt-1 text-xs text-danger">
              {fieldErrors.customer_document_number}
            </p>
          ) : null}
        </div>
      </div>

      <div>
        <label htmlFor="order-delivery-mode" className={labelClassName}>
          Modo de entrega
        </label>
        <select
          id="order-delivery-mode"
          name="delivery_mode"
          value={form?.delivery_mode ?? "PICKUP"}
          disabled={fieldsDisabled}
          onChange={(event) => {
            const mode = event.target.value as FormState["delivery_mode"];
            setForm((current) =>
              current
                ? {
                    ...current,
                    delivery_mode: mode,
                    delivery_address: mode === "PICKUP" ? "" : current.delivery_address,
                  }
                : current,
            );
          }}
          className={inputClassName}
        >
          <option value="PICKUP">Recogida</option>
          <option value="COORDINATED_SHIPPING">Envío coordinado</option>
        </select>
      </div>

      {form?.delivery_mode === "COORDINATED_SHIPPING" ? (
        <div>
          <label htmlFor="order-delivery-address" className={labelClassName}>
            Dirección
          </label>
          <textarea
            id="order-delivery-address"
            name="delivery_address"
            required
            minLength={5}
            maxLength={255}
            rows={3}
            value={form.delivery_address}
            disabled={fieldsDisabled}
            aria-invalid={Boolean(fieldErrors.delivery_address)}
            aria-describedby={
              fieldErrors.delivery_address
                ? "order-delivery-address-error"
                : "order-delivery-address-help"
            }
            onChange={(event) =>
              setForm((current) =>
                current ? { ...current, delivery_address: event.target.value } : current,
              )
            }
            className={`${inputClassName} py-2`}
          />
          <p id="order-delivery-address-help" className={helpClassName}>
            Obligatoria para envío coordinado (mínimo 5 caracteres).
          </p>
          {fieldErrors.delivery_address ? (
            <p id="order-delivery-address-error" className="mt-1 text-xs text-danger">
              {fieldErrors.delivery_address}
            </p>
          ) : null}
        </div>
      ) : (
        <p className="text-xs text-muted">En recogida la dirección se envía vacía al servidor.</p>
      )}

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
