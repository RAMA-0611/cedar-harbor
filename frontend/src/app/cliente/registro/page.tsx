"use client";

import { StorefrontShell } from "@/components/store/StorefrontShell";
import { useAuth } from "@/hooks/useAuth";
import { ApiClientError } from "@/types/api";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { FormEvent, Suspense, useState } from "react";

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
  const [busy, setBusy] = useState(false);

  const handleSubmit = async (event: FormEvent) => {
    event.preventDefault();
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
      <form onSubmit={(event) => void handleSubmit(event)}>
        <div className="store-field">
          <label htmlFor="name">Nombre</label>
          <input
            id="name"
            name="name"
            required
            maxLength={160}
            autoComplete="name"
            value={name}
            onChange={(event) => setName(event.target.value)}
          />
        </div>
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
            onChange={(event) => setEmail(event.target.value)}
          />
        </div>
        <div className="store-field">
          <label htmlFor="phone">Teléfono</label>
          <input
            id="phone"
            name="phone"
            required
            maxLength={30}
            autoComplete="tel"
            value={phone}
            onChange={(event) => setPhone(event.target.value)}
          />
        </div>
        <div className="store-field">
          <label htmlFor="document_type">Tipo documento</label>
          <select
            id="document_type"
            name="document_type"
            required
            value={documentType}
            onChange={(event) => setDocumentType(event.target.value)}
            aria-label="Tipo documento"
          >
            <option value="CC">CC</option>
            <option value="CE">CE</option>
            <option value="NIT">NIT</option>
            <option value="PP">PP</option>
          </select>
        </div>
        <div className="store-field">
          <label htmlFor="document_number">Número documento</label>
          <input
            id="document_number"
            name="document_number"
            required
            maxLength={30}
            value={documentNumber}
            onChange={(event) => setDocumentNumber(event.target.value)}
          />
        </div>
        <div className="store-field">
          <label htmlFor="password">Contraseña</label>
          <input
            id="password"
            name="password"
            type="password"
            required
            minLength={8}
            autoComplete="new-password"
            value={password}
            onChange={(event) => setPassword(event.target.value)}
          />
        </div>
        <div className="store-field">
          <label htmlFor="password_confirmation">Confirmar contraseña</label>
          <input
            id="password_confirmation"
            name="password_confirmation"
            type="password"
            required
            minLength={8}
            autoComplete="new-password"
            value={passwordConfirmation}
            onChange={(event) => setPasswordConfirmation(event.target.value)}
          />
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
