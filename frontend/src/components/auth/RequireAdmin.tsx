"use client";

import { SessionLoading, SessionState } from "@/components/ui/SessionState";
import { useAuth } from "@/hooks/useAuth";
import { useRouter } from "next/navigation";
import { useEffect, type ReactNode } from "react";

export function RequireAdmin({ children }: { children: ReactNode }) {
  const { user, loading, error, refreshUser } = useAuth();
  const router = useRouter();

  useEffect(() => {
    if (loading) {
      return;
    }
    if (!user) {
      if (!error) {
        router.replace("/login");
      }
      return;
    }
    if (user.role !== "ADMIN") {
      router.replace("/acceso-denegado");
    }
  }, [error, loading, router, user]);

  if (loading) {
    return <SessionLoading />;
  }

  if (error && !user) {
    return (
      <SessionState
        title="No se pudo comprobar la sesión"
        message="El panel administrativo no se muestra hasta confirmar la autenticación."
        actionLabel="Reintentar"
        onAction={() => {
          void refreshUser().catch(() => undefined);
        }}
      />
    );
  }

  if (!user || user.role !== "ADMIN") {
    return <SessionLoading />;
  }

  return children;
}
