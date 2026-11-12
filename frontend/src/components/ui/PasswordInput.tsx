"use client";

import { useState, type InputHTMLAttributes } from "react";

type PasswordInputProps = Omit<InputHTMLAttributes<HTMLInputElement>, "type"> & {
  wrapperClassName?: string;
};

function EyeIcon({ off }: { off: boolean }) {
  return (
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" focusable="false">
      <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z" />
      <circle cx="12" cy="12" r="3" />
      {off ? <path d="M3 3l18 18" /> : null}
    </svg>
  );
}

/** Campo de contraseña con botón de ojo para mostrar u ocultar lo escrito. */
export function PasswordInput({ wrapperClassName, className, disabled, id, ...rest }: PasswordInputProps) {
  const [visible, setVisible] = useState(false);

  return (
    <div className={`pw-wrap ${wrapperClassName ?? ""}`.trim()}>
      <input {...rest} id={id} disabled={disabled} type={visible ? "text" : "password"} className={`pw-input ${className ?? ""}`.trim()} />
      <button
        type="button"
        className="pw-toggle"
        aria-label={visible ? "Ocultar contraseña" : "Mostrar contraseña"}
        aria-pressed={visible}
        aria-controls={id}
        disabled={disabled}
        onClick={() => setVisible((current) => !current)}
      >
        <EyeIcon off={visible} />
      </button>
    </div>
  );
}
