"use client";

import {
  DOCUMENT_TYPES,
  sanitizeDocumentNumber,
  sanitizePhone,
  type ContactValues,
  type FieldErrors,
} from "@/lib/validation";

type Props = {
  values: ContactValues;
  errors: FieldErrors;
  idPrefix?: string;
  showName?: boolean;
  onChange: (next: Partial<ContactValues>) => void;
};

/** Teléfono (10 dígitos, inicia por 3), tipo de documento (lista fija) y número de documento. */
export function ContactFields({ values, errors, idPrefix = "", showName = true, onChange }: Props) {
  const id = (name: string) => `${idPrefix}${name}`;
  const err = (key: string) => errors[key];

  return (
    <>
      {showName ? (
        <div className="store-field">
          <label htmlFor={id("name")}>Nombre</label>
          <input
            id={id("name")}
            name="name"
            required
            maxLength={100}
            autoComplete="name"
            value={values.name}
            aria-invalid={err("name") ? true : undefined}
            aria-describedby={err("name") ? id("name-error") : undefined}
            onChange={(event) => onChange({ name: event.target.value })}
          />
          {err("name") ? <p id={id("name-error")} className="store-field-error" role="alert">{err("name")}</p> : null}
        </div>
      ) : null}
      <div className="store-field">
        <label htmlFor={id("phone")}>Teléfono celular</label>
        <input
          id={id("phone")}
          name="phone"
          type="tel"
          inputMode="numeric"
          required
          autoComplete="tel-national"
          placeholder="3001234567"
          value={values.phone}
          aria-invalid={err("phone") ? true : undefined}
          aria-describedby={err("phone") ? id("phone-error") : id("phone-hint")}
          onChange={(event) => onChange({ phone: sanitizePhone(event.target.value) })}
        />
        <p id={id("phone-hint")} className="store-hint">10 dígitos, solo números, empieza por 3.</p>
        {err("phone") ? <p id={id("phone-error")} className="store-field-error" role="alert">{err("phone")}</p> : null}
      </div>
      <div className="store-field">
        <label htmlFor={id("document_type")}>Tipo de documento</label>
        <select
          id={id("document_type")}
          name="document_type"
          required
          value={values.document_type}
          aria-invalid={err("document_type") ? true : undefined}
          onChange={(event) =>
            onChange({
              document_type: event.target.value,
              document_number: sanitizeDocumentNumber(values.document_number, event.target.value),
            })
          }
        >
          {DOCUMENT_TYPES.map((type) => (
            <option key={type.value} value={type.value}>
              {type.label}
            </option>
          ))}
        </select>
        {err("document_type") ? <p className="store-field-error" role="alert">{err("document_type")}</p> : null}
      </div>
      <div className="store-field">
        <label htmlFor={id("document_number")}>Número de documento</label>
        <input
          id={id("document_number")}
          name="document_number"
          required
          inputMode={values.document_type === "PP" ? "text" : "numeric"}
          value={values.document_number}
          aria-invalid={err("document_number") ? true : undefined}
          aria-describedby={err("document_number") ? id("document_number-error") : undefined}
          onChange={(event) =>
            onChange({ document_number: sanitizeDocumentNumber(event.target.value, values.document_type) })
          }
        />
        {err("document_number") ? (
          <p id={id("document_number-error")} className="store-field-error" role="alert">
            {err("document_number")}
          </p>
        ) : null}
      </div>
    </>
  );
}
