"use client";

import { StorefrontShell } from "@/components/store/StorefrontShell";
import { useAuth } from "@/hooks/useAuth";
import { ApiClientError } from "@/types/api";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { FormEvent, Suspense, useState } from "react";

function CustomerLoginForm() {
  const { login } = useAuth();
  const router = useRouter();
  const search = useSearchParams();
  const next = search.get("next") || "/carrito";
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [busy, setBusy] = useState(false);

  const handleSubmit = async (event: FormEvent) => {
    event.preventDefault();
    setBusy(true);
    setError(null);
    try {
      const user = await login(email.trim(), password);
      if (user.role === "CUSTOMER") {
        router.replace(next.startsWith("/") ? next : "/carrito");
        return;
      }
      if (user.role === "ADMIN") {
        router.replace("/dashboard");
        return;
      }
      setError("Rol no admitido para esta entrada.");
    } catch (caught) {
      setError(
        caught instanceof ApiClientError
          ? caught.error.message
          : "No se pudo iniciar sesión.",
      );
    } finally {
      setBusy(false);
    }
  };

  return (
    <div className="store-panel" style={{ maxWidth: "28rem", margin: "0 auto" }}>
      <h1>Acceso cliente</h1>
      <p className="store-meta">Use una cuenta CUSTOMER para carrito y checkout reales.</p>
      {next.startsWith("/producto/") ? (
        <div className="store-alert store-alert-info">
          Inicie sesión para agregar el producto al carrito y continuar la compra.
        </div>
      ) : null}
      {error ? <div className="store-alert store-alert-error">{error}</div> : null}
      <form onSubmit={(event) => void handleSubmit(event)}>
        <div className="store-field">
          <label htmlFor="email">Correo</label>
          <input
            id="email"
            type="email"
            required
            autoComplete="username"
            value={email}
            onChange={(event) => setEmail(event.target.value)}
          />
        </div>
        <div className="store-field">
          <label htmlFor="password">Contraseña</label>
          <input
            id="password"
            type="password"
            required
            autoComplete="current-password"
            value={password}
            onChange={(event) => setPassword(event.target.value)}
          />
        </div>
        <button type="submit" className="store-btn" disabled={busy}>
          {busy ? "Ingresando…" : "Iniciar sesión"}
        </button>
      </form>
      <p className="store-meta" style={{ marginTop: "1rem" }}>
        ¿Es administrador? <Link href="/login">Ir al panel ADMIN</Link>
      </p>
    </div>
  );
}

export default function CustomerLoginPage() {
  return (
    <StorefrontShell>
      <Suspense fallback={<p>Cargando…</p>}>
        <CustomerLoginForm />
      </Suspense>
    </StorefrontShell>
  );
}
