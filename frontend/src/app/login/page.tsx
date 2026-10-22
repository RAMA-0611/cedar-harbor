"use client";

import { LoginForm } from "@/components/auth/LoginForm";
import { SessionLoading, SessionState } from "@/components/ui/SessionState";
import { useAuth } from "@/hooks/useAuth";
import { useRouter } from "next/navigation";
import { useEffect } from "react";

export default function LoginPage() {
  const { user, loading, error, refreshUser } = useAuth();
  const router = useRouter();

  useEffect(() => {
    if (loading || !user) {
      return;
    }
    router.replace(user.role === "ADMIN" ? "/dashboard" : "/acceso-denegado");
  }, [loading, router, user]);

  if (loading) {
    return <SessionLoading />;
  }

  if (error && !user) {
    return (
      <SessionState
        title="No se pudo comprobar la sesión"
        message="Intente recargar el estado de autenticación antes de ingresar."
        actionLabel="Reintentar"
        onAction={() => {
          void refreshUser().catch(() => undefined);
        }}
      />
    );
  }

  if (user) {
    return <SessionLoading />;
  }

  return (
    <div className="flex min-h-dvh items-center justify-center bg-background px-4 py-10">
      <div className="w-full max-w-md rounded-2xl border border-border bg-surface p-8 shadow-xl">
        <p className="text-xs font-semibold uppercase tracking-[0.2em] text-accent">ByteBlade</p>
        <h1 className="mt-3 text-2xl font-semibold text-foreground">Proyecto Rotor</h1>
        <p className="mt-2 text-sm leading-6 text-muted">
          Acceso al panel administrativo. Use una cuenta con rol ADMIN.
        </p>
        <LoginForm />
      </div>
    </div>
  );
}
