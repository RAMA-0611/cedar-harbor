"use client";

import { StorefrontShell } from "@/components/store/StorefrontShell";
import { useAuth } from "@/hooks/useAuth";
import { ContactFields } from "@/components/store/ContactFields";
import { validateContact, validateEmail, validatePassword, type FieldErrors } from "@/lib/validation";
import { ApiClientError } from "@/types/api";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { FormEvent, Suspense, useState } from "react";
import { PasswordInput } from "@/components/ui/PasswordInput";

const safeNextPath = (raw: string | null): string => {
  if (!raw || !raw.startsWith("/") || raw.startsWith("//")) {
    return "/carrito";
  }
  return raw;
};

function CustomerRegisterForm() {
  const { register } = useAuth();
  const router = useRouter();
  const search = useSearchParams();
  const next = safeNextPath(search.get("next"));
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [phone, setPhone] = useState("");
  const [documentType, setDocumentType] = useState("CC");
  const [documentNumber, setDocumentNumber] = useState("");
  const [password, setPassword] = useState("");
  const [passwordConfirmation, setPasswordConfirmation] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<FieldErrors>({});
  const [busy, setBusy] = useState(false);

  const handleSubmit = async (event: FormEvent) => {
    event.preventDefault();
    const errors: FieldErrors = validateContact({
      name,
      phone,
      document_type: documentType,
      document_number: documentNumber,
    });
    const emailError = validateEmail(email);
    if (emailError) errors.email = emailError;
    const passwordError = validatePassword(password);
    if (passwordError) errors.password = passwordError;
    if (password !== passwordConfirmation) errors.password_confirmation = "Las contraseñas no coinciden.";
    setFieldErrors(errors);
    if (Object.keys(errors).length > 0) {
      setError("Revisa los campos marcados antes de continuar.");
      return;
    }
    setBusy(true);
    setError(null);
    try {
      const user = await register({
        name: name.trim(),
        email: email.trim(),
        phone: phone.trim(),
        document_type: documentType.trim(),
        document_number: documentNumber.trim(),
        password,
        password_confirmation: passwordConfirmation,
      });
      if (user.role !== "CUSTOMER") {
        setError("La cuenta creada no es de cliente.");
        return;
      }
      router.replace(next);
    } catch (caught) {
      if (caught instanceof ApiClientError && caught.error.details && typeof caught.error.details === "object") {
        const raw = (caught.error.details as { errors?: Record<string, string[]> }).errors ?? {};
        const mapped: FieldErrors = {};
        for (const [key, messages] of Object.entries(raw)) {
          if (Array.isArray(messages) && messages[0]) mapped[key] = messages[0];
        }
        setFieldErrors(mapped);
      }
      setError(
        caught instanceof ApiClientError
          ? caught.error.message
          : "No se pudo crear la cuenta.",
      );
    } finally {
      setBusy(false);
    }
  };

  const loginHref = `/cliente/login?next=${encodeURIComponent(next)}`;

  return (
    <div className="store-panel" style={{ maxWidth: "28rem", margin: "0 auto" }}>
      <h1>Crear cuenta</h1>
      <p className="store-meta">Crea tu cuenta para comprar y consultar tus pedidos.</p>
      {error ? <div className="store-alert store-alert-error">{error}</div> : null}
      <form noValidate onSubmit={(event) => void handleSubmit(event)}>
        <ContactFields
          values={{ name, phone, document_type: documentType, document_number: documentNumber }}
          errors={fieldErrors}
          onChange={(next) => {
            if (next.name !== undefined) setName(next.name);
            if (next.phone !== undefined) setPhone(next.phone);
            if (next.document_type !== undefined) setDocumentType(next.document_type);
            if (next.document_number !== undefined) setDocumentNumber(next.document_number);
          }}
        />
        <div className="store-field">
          <label htmlFor="email">Correo</label>
          <input
            id="email"
            name="email"
            type="email"
            required
            maxLength={254}
            autoComplete="email"
            value={email}
            aria-invalid={fieldErrors.email ? true : undefined}
            onChange={(event) => setEmail(event.target.value)}
          />
          {fieldErrors.email ? <p className="store-field-error" role="alert">{fieldErrors.email}</p> : null}
        </div>
        <div className="store-field">
          <label htmlFor="password">Contraseña</label>
          <PasswordInput
            id="password"
            name="password"
            required
                        autoComplete="new-password"
            value={password}
            maxLength={72}
            aria-invalid={fieldErrors.password ? true : undefined}
            onChange={(event) => setPassword(event.target.value)}
          />
          <p className="store-hint">Mínimo 8 caracteres, con al menos una letra y un número.</p>
          {fieldErrors.password ? <p className="store-field-error" role="alert">{fieldErrors.password}</p> : null}
        </div>
        <div className="store-field">
          <label htmlFor="password_confirmation">Confirmar contraseña</label>
          <PasswordInput
            id="password_confirmation"
            name="password_confirmation"
            required
                        autoComplete="new-password"
            value={passwordConfirmation}
            maxLength={72}
            aria-invalid={fieldErrors.password_confirmation ? true : undefined}
            onChange={(event) => setPasswordConfirmation(event.target.value)}
          />
          {fieldErrors.password_confirmation ? (
            <p className="store-field-error" role="alert">{fieldErrors.password_confirmation}</p>
          ) : null}
        </div>
        <button type="submit" className="store-btn" disabled={busy}>
          {busy ? "Creando…" : "Crear cuenta"}
        </button>
      </form>
      <p className="store-meta" style={{ marginTop: "1rem" }}>
        ¿Ya tienes cuenta? <Link href={loginHref}>Iniciar sesión</Link>
      </p>
    </div>
  );
}

export default function CustomerRegisterPage() {
  return (
    <StorefrontShell>
      <Suspense fallback={<p>Cargando…</p>}>
        <CustomerRegisterForm />
      </Suspense>
    </StorefrontShell>
  );
}
