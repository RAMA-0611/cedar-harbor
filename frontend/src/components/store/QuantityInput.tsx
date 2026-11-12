"use client";

import { clampQuantity } from "@/lib/validation";
import { useId, useState } from "react";

type Props = {
  value: number;
  max: number;
  label: string;
  disabled?: boolean;
  onCommit: (quantity: number) => void;
  id?: string;
};

/**
 * Cantidad entera entre 1 y el stock disponible. Solo acepta dígitos; el valor se confirma al salir
 * del campo o con Enter, y se ajusta (con aviso) si excede el stock.
 */
export function QuantityInput({ value, max, label, disabled, onCommit, id }: Props) {
  const autoId = useId();
  const inputId = id ?? autoId;
  const [draft, setDraft] = useState(String(value));
  const [notice, setNotice] = useState<string | null>(null);

  const [seenValue, setSeenValue] = useState(value);
  if (seenValue !== value) {
    // El valor confirmado cambió desde fuera (p. ej. respuesta del servidor): sincroniza el borrador.
    setSeenValue(value);
    setDraft(String(value));
  }

  const commit = () => {
    const raw = draft.trim();
    const parsed = raw === "" ? 1 : Number.parseInt(raw, 10);
    const next = clampQuantity(parsed, max);
    if (Number.isFinite(parsed) && parsed > max) {
      setNotice(`Solo hay ${max} unidad${max === 1 ? "" : "es"} disponible${max === 1 ? "" : "s"}; se ajustó la cantidad.`);
    } else if (raw === "" || !Number.isFinite(parsed) || parsed < 1) {
      setNotice("La cantidad mínima es 1.");
    } else {
      setNotice(null);
    }
    setDraft(String(next));
    if (next !== value) {
      onCommit(next);
    }
  };

  return (
    <>
      <input
        id={inputId}
        aria-label={label}
        type="text"
        inputMode="numeric"
        pattern="[0-9]*"
        maxLength={3}
        value={draft}
        disabled={disabled}
        aria-invalid={notice ? true : undefined}
        className="store-qty"
        onChange={(event) => setDraft(event.target.value.replace(/\D/g, "").slice(0, 3))}
        onBlur={commit}
        onKeyDown={(event) => {
          if (event.key === "Enter") {
            event.preventDefault();
            commit();
          }
        }}
      />
      {notice ? (
        <p className="store-field-error" role="status">
          {notice}
        </p>
      ) : null}
    </>
  );
}
