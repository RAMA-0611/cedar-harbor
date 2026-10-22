"use client";

import { ApiClientError } from "@/types/api";
import { useAuth } from "@/hooks/useAuth";
import { useRouter } from "next/navigation";
import { useState, type FormEvent } from "react";

function fieldErrorsFromDetails(details: Record<string, unknown>): string[] {
  const errors = details.errors;
  if (!errors || typeof errors !== "object") {
    return [];
  }

  return Object.values(errors as Record<string, unknown>).flatMap((value) => {
    if (Array.isArray(value)) {
      return value.filter((item): item is string => typeof item === "string");
    }
    return typeof value === "string" ? [value] : [];
  });
}

function loginErrorMessage(caught: unknown): string {
  if (!(caught instanceof ApiClientError)) {
    return "No se pudo iniciar sesión. Inténtelo de nuevo.";
  }

  const { status, details, message } = caught.error;

  if (status === 401) {
    return "Correo electrónico o contraseña incorrectos.";
  }
  if (status === 422) {
    const fields = fieldErrorsFromDetails(details);
    return fields[0] ?? "Revise los datos ingresados.";
  }
  if (status === 429) {
    return "Demasiados intentos. Inténtelo de nuevo más tarde.";
  }
  if (status === 0 || status >= 500) {
    return caught.error.code === "NETWORK_ERROR" || caught.error.code === "TIMEOUT"
      ? message
      : "El servidor no está disponible en este momento.";
  }
  return "No se pudo iniciar sesión. Inténtelo de nuevo.";
}

export function LoginForm() {
  const { login } = useAuth();
  const router = useRouter();
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function onSubmit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError(null);

    const trimmedEmail = email.trim();
    if (!trimmedEmail || !password) {
      setError("Ingrese correo electrónico y contraseña.");
      return;
    }

    setSubmitting(true);
    try {
      const user = await login(trimmedEmail, password);
      if (user.role === "ADMIN") {
        router.replace("/dashboard");
        return;
      }
      router.replace("/acceso-denegado");
    } catch (caught) {
      setError(loginErrorMessage(caught));
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <form onSubmit={onSubmit} className="mt-8 space-y-5" noValidate>
      <div>
        <label htmlFor="email" className="block text-sm font-medium text-foreground">
          Correo electrónico
        </label>
        <input
          id="email"
          name="email"
          type="email"
          autoComplete="email"
          required
          maxLength={254}
          value={email}
          onChange={(event) => setEmail(event.target.value)}
          className="mt-2 min-h-11 w-full rounded-lg border border-border bg-surface-2 px-3 text-sm text-foreground outline-none placeholder:text-muted"
          placeholder="correo@dominio.com"
        />
      </div>

      <div>
        <label htmlFor="password" className="block text-sm font-medium text-foreground">
          Contraseña
        </label>
        <input
          id="password"
          name="password"
          type="password"
          autoComplete="current-password"
          required
          value={password}
          onChange={(event) => setPassword(event.target.value)}
          className="mt-2 min-h-11 w-full rounded-lg border border-border bg-surface-2 px-3 text-sm text-foreground outline-none"
        />
      </div>

      {error ? (
        <p role="alert" className="rounded-lg border border-danger/40 bg-danger/10 px-3 py-2 text-sm text-danger">
          {error}
        </p>
      ) : null}

      <button
        type="submit"
        disabled={submitting}
        aria-busy={submitting}
        className="inline-flex min-h-11 w-full items-center justify-center rounded-lg bg-accent px-4 text-sm font-semibold text-background transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-60"
      >
        {submitting ? "Iniciando sesión…" : "Iniciar sesión"}
      </button>
    </form>
  );
}
